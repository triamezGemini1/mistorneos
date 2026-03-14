<?php
/**
 * Importación histórica desde otra plataforma: fase 1 (cedula→id_usuario), fase 2 (partiresul).
 */
declare(strict_types=1);

final class ImportacionTorneoExternoService
{
    /**
     * @return list<list<string>>
     */
    public static function leerExcelOCsv(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx'], true)) {
            require_once __DIR__ . '/CargaMasivaXlsxReader.php';
            $r = CargaMasivaXlsxReader::leerHojas($path);
            if ($r !== []) {
                return $r;
            }
        }
        if (in_array($ext, ['csv', 'txt', 'xls'], true)) {
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                return [];
            }
            if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
                $raw = substr($raw, 3);
            }
            $lines = preg_split('/\r\n|\r|\n/', $raw);
            $lines = array_values(array_filter($lines, static fn ($l) => trim((string)$l) !== ''));
            if ($lines === []) {
                return [];
            }
            $delim = substr_count((string)$lines[0], "\t") >= 2 ? "\t" : (substr_count((string)$lines[0], ';') >= 2 ? ';' : ',');
            $rows = [];
            foreach ($lines as $line) {
                $row = $delim === "\t" ? array_map('trim', explode("\t", $line)) : str_getcsv($line, $delim, '"', '\\');
                $rows[] = array_map(static fn ($c) => trim((string)$c), $row);
            }
            return $rows;
        }
        require_once __DIR__ . '/CargaMasivaXlsxReader.php';
        return CargaMasivaXlsxReader::leerHojas($path);
    }

    /**
     * Fase 1: añade columna id_usuario por cédula (usuarios.cedula).
     *
     * @return array{filas: list<list<string>>, no_encontradas: list<string>}
     */
    public static function fase1Enriquecer(PDO $pdo, array $rows): array
    {
        if ($rows === []) {
            return ['filas' => [], 'no_encontradas' => []];
        }
        $header = $rows[0];
        $map = self::mapearIndices($header, ['pareja' => ['pareja', 'id_pareja', 'parejas'], 'cedula' => ['cedula', 'cedula1', 'ci', 'documento', 'c_dula']]);
        if ($map['cedula'] < 0) {
            return ['filas' => $rows, 'no_encontradas' => ['No se encontró columna de cédula en la primera fila.']];
        }
        $out = [];
        $out[] = array_merge($header, ['id_usuario']);
        $noEnc = [];
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? LIMIT 1');
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            while (count($row) < count($header)) {
                $row[] = '';
            }
            $ced = self::normalizarCedula($row[$map['cedula']] ?? '');
            if ($ced === '') {
                $out[] = array_merge($row, ['']);
                continue;
            }
            $stmt->execute([$ced]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                $stmt->execute([preg_replace('/\D/', '', $ced)]);
                $id = $stmt->fetchColumn();
            }
            if ($id) {
                $out[] = array_merge($row, [(string)(int)$id]);
            } else {
                $noEnc[] = $ced;
                $out[] = array_merge($row, ['']);
            }
        }
        return ['filas' => $out, 'no_encontradas' => array_values(array_unique($noEnc))];
    }

    /**
     * Fase 2: INSERT partiresul. Columnas esperadas (cabecera): partida, mesa, id_usuario (o cedula), secuencia, resultado1, resultado2, ff (opcional).
     *
     * @return array{insertados: int, errores: list<string>}
     */
    public static function fase2InsertarPartiresul(
        PDO $pdo,
        int $torneo_id,
        int $registrado_por,
        string $fechaTorneoYmd,
        array $rows
    ): array {
        if ($rows === []) {
            return ['insertados' => 0, 'errores' => ['Archivo vacío']];
        }
        $stmtT = $pdo->prepare('SELECT puntos FROM tournaments WHERE id = ?');
        $stmtT->execute([$torneo_id]);
        $puntosTorneo = (int)($stmtT->fetchColumn() ?: 100);

        $header = array_map(static fn ($h) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$h))), $rows[0]);
        $idx = static function (array $h, array $names): int {
            foreach ($names as $n) {
                $n = strtolower($n);
                foreach ($h as $i => $col) {
                    if (str_contains((string)$col, $n) || $col === $n) {
                        return $i;
                    }
                }
            }
            return -1;
        };
        $iPart = $idx($header, ['partida', 'ronda', 'partida_']);
        $iMesa = $idx($header, ['mesa']);
        $iUsr = $idx($header, ['id_usuario', 'idusuario']);
        $iCed = $idx($header, ['cedula', 'cedula1']);
        $iSeq = $idx($header, ['secuencia', 'seq']);
        $iR1 = $idx($header, ['resultado1', 'r1', 'pts1']);
        $iR2 = $idx($header, ['resultado2', 'r2', 'pts2']);
        $iFf = $idx($header, ['ff', 'forfait']);
        if ($iPart < 0 || $iMesa < 0 || $iSeq < 0 || $iR1 < 0 || $iR2 < 0 || ($iUsr < 0 && $iCed < 0)) {
            return ['insertados' => 0, 'errores' => ['Faltan columnas: partida, mesa, secuencia, resultado1, resultado2 e id_usuario o cédula.']];
        }

        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? OR cedula = ? LIMIT 1');
        $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
        $tieneObs = in_array('observaciones', $cols, true);

        $sql = "INSERT INTO partiresul (id_torneo, partida, mesa, secuencia, id_usuario, resultado1, resultado2, efectividad, ff, tarjeta, sancion, chancleta, zapato, fecha_partida, registrado_por, registrado"
            . ($tieneObs ? ", observaciones" : '') . ") VALUES (?,?,?,?,?,?,?,?,0,0,0,?,?,?,?,1"
            . ($tieneObs ? ",?" : '') . ")";

        $insertados = 0;
        $errores = [];
        $pdo->beginTransaction();
        try {
            $stmtI = $pdo->prepare($sql);
            for ($r = 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                $partida = (int)($row[$iPart] ?? 0);
                $mesa = (int)($row[$iMesa] ?? 0);
                $secuencia = (int)($row[$iSeq] ?? 0);
                $idUsuario = $iUsr >= 0 ? (int)($row[$iUsr] ?? 0) : 0;
                if ($idUsuario <= 0 && $iCed >= 0) {
                    $ced = self::normalizarCedula($row[$iCed] ?? '');
                    $stmtU->execute([$ced, preg_replace('/\D/', '', $ced)]);
                    $idUsuario = (int)($stmtU->fetchColumn() ?: 0);
                }
                if ($partida < 1 || $mesa < 1 || $secuencia < 1 || $idUsuario < 1) {
                    continue;
                }
                $r1 = (int)($row[$iR1] ?? 0);
                $r2 = (int)($row[$iR2] ?? 0);
                $ff = ($iFf >= 0 && (int)($row[$iFf] ?? 0) === 1) ? 1 : 0;
                $efect = self::efectividad($r1, $r2, $puntosTorneo, $ff);
                $zap = ($r1 === 0 && $ff === 0) ? 1 : 0;
                $chan = $zap;
                $fecha = $fechaTorneoYmd . ' 12:00:00';
                $params = [$torneo_id, $partida, $mesa, $secuencia, $idUsuario, $r1, $r2, $efect, $ff, $chan, $zap, $fecha, $registrado_por];
                if ($tieneObs) {
                    $params[] = '';
                }
                try {
                    $stmtI->execute($params);
                    $insertados++;
                } catch (Throwable $e) {
                    $errores[] = 'Fila ' . ($r + 1) . ': ' . $e->getMessage();
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['insertados' => 0, 'errores' => [$e->getMessage()]];
        }
        return ['insertados' => $insertados, 'errores' => $errores];
    }

    /**
     * Dos archivos. Archivo 1: cédula → id_usuario (usuarios); opcional pareja; opcional columna usuario (id del otro
     * sistema) para mapear resultado.usuario → id_usuario. Archivo 2: partida, mesa, secuencia, r1/r2, usuario (externo)
     * o cédula o pareja+jugador. El valor "usuario" del export NO es id de Mistorneos: se traduce con el archivo 1.
     *
     * @return array{insertados: int, errores: list<string>, homologacion_sin_usuario: int, resultados_sin_resolver: int, cedulas_no_encontradas: list<string>}
     */
    public static function importarDosArchivosPartiresul(
        PDO $pdo,
        int $torneo_id,
        int $registrado_por,
        string $fechaTorneoYmd,
        array $rowsHomologacion,
        array $rowsResultados
    ): array {
        $stats = [
            'insertados' => 0,
            'errores' => [],
            'homologacion_sin_usuario' => 0,
            'resultados_sin_resolver' => 0,
            'cedulas_no_encontradas' => [],
            'filas_bloque_cedulas' => max(0, count($rowsHomologacion) - 1),
            'filas_bloque_resultados' => max(0, count($rowsResultados) - 1),
            'mapeos_usuario_externo' => 0,
            'columna_usuario_homolog' => false,
            'columna_usuario_resultados' => false,
        ];
        if ($rowsHomologacion === [] || $rowsResultados === []) {
            $stats['errores'][] = 'Faltan filas en archivo de homologación o de resultados.';
            return $stats;
        }

        $h0raw = $rowsHomologacion[0];
        $maxCols = 0;
        for ($k = 0; $k < min(5, count($rowsHomologacion)); $k++) {
            $maxCols = max($maxCols, count($rowsHomologacion[$k] ?? []));
        }
        $hNormHom = array_map(static function ($x) {
            $s = strtolower(trim((string)$x));
            $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'], ['a', 'e', 'i', 'o', 'u', 'n', 'u'], $s);
            return strtolower(preg_replace('/[^a-z0-9]+/i', '_', $s));
        }, $h0raw);
        $mapHom = self::mapearIndices($h0raw, ['pareja' => ['pareja', 'id_pareja', 'parejas'], 'cedula' => ['cedula', 'cedula1', 'ci', 'documento', 'c_dula']]);
        if ($mapHom['cedula'] < 0) {
            foreach ($hNormHom as $hi => $col) {
                if ($col === 'cedula' || strpos($col, 'cedul') !== false || $col === 'ci' || strpos($col, 'documento') !== false) {
                    $mapHom['cedula'] = $hi;
                    break;
                }
            }
        }
        $iExtHom = -1;
        $candidatosExt = ['usuario', 'id_externo', 'cod_jugador', 'id_jugador', 'id', 'codigo', 'cod', 'externo', 'jugador_id', 'idjugador', 'nro', 'numero'];
        foreach ($hNormHom as $hi => $col) {
            if ($hi === $mapHom['cedula'] || $hi === $mapHom['pareja']) {
                continue;
            }
            if (in_array($col, $candidatosExt, true) || ($col !== '' && strpos($col, 'id_') === 0)) {
                $iExtHom = $hi;
                break;
            }
        }
        if ($iExtHom < 0 && $mapHom['cedula'] >= 0) {
            foreach ($hNormHom as $hi => $col) {
                if ($hi !== $mapHom['cedula'] && $hi !== $mapHom['pareja']) {
                    $iExtHom = $hi;
                    break;
                }
            }
        }
        $homologRows = $rowsHomologacion;
        $dataStartIdx = 1;
        if ($mapHom['cedula'] < 0 || $iExtHom < 0) {
            if ($maxCols >= 2) {
                $r0 = $rowsHomologacion[0] ?? [];
                $r1 = $rowsHomologacion[1] ?? $r0;
                $a0 = trim((string)($r0[0] ?? ''));
                $b0 = trim((string)($r0[1] ?? ''));
                $lenA = strlen(preg_replace('/\D/', '', $a0));
                $lenB = strlen(preg_replace('/\D/', '', $b0));
                $digitsA = preg_replace('/\D/', '', $a0);
                $digitsB = preg_replace('/\D/', '', $b0);
                if ($lenA >= 5 && $lenB < $lenA) {
                    $mapHom['cedula'] = 0;
                    $iExtHom = 1;
                } else {
                    $mapHom['cedula'] = 1;
                    $iExtHom = 0;
                }
                $soloDatosNumericosFila0 = $a0 !== '' && $b0 !== ''
                    && ctype_digit($digitsA) && ctype_digit($digitsB)
                    && strlen($digitsA) <= 12 && strlen($digitsB) <= 12;
                if ($soloDatosNumericosFila0) {
                    if ($lenA >= 5 && $lenB < $lenA) {
                        $mapHom['cedula'] = 0;
                        $iExtHom = 1;
                    } else {
                        $mapHom['cedula'] = 1;
                        $iExtHom = 0;
                    }
                    $homologRows = array_merge([['id_externo', 'cedula']], $rowsHomologacion);
                    $dataStartIdx = 1;
                }
            }
        }
        if ($mapHom['cedula'] < 0 || $iExtHom < 0 || $mapHom['cedula'] === $iExtHom) {
            $stats['errores'][] = 'Homologación: hoja 1 con al menos 2 columnas. Orden recomendado: id externo | cédula (ej. 37 y 4906763). Puede poner fila títulos usuario + cedula, o solo filas de datos.';
            $stats['filas_bloque_cedulas'] = max(0, count($rowsHomologacion) - 1);
            return $stats;
        }

        $stmtCed = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? OR cedula = ? LIMIT 1');
        $cedulaToId = [];
        $parejaToIds = [];
        $extUsuarioToId = [];
        $filasHomologConId = 0;
        $noEncCed = [];
        for ($i = $dataStartIdx; $i < count($homologRows); $i++) {
            $row = $homologRows[$i];
            while (count($row) <= max($mapHom['cedula'], $iExtHom)) {
                $row[] = '';
            }
            $ced = self::normalizarCedula($row[$mapHom['cedula']] ?? '');
            $extKey = trim((string)($row[$iExtHom] ?? ''));
            if ($ced === '' || $extKey === '') {
                continue;
            }
            $stmtCed->execute([$ced, preg_replace('/\D/', '', $ced)]);
            $idU = (int)($stmtCed->fetchColumn() ?: 0);
            if ($idU > 0) {
                $filasHomologConId++;
                $cedulaToId[$ced] = $idU;
                $cedulaToId[preg_replace('/\D/', '', $ced)] = $idU;
                $extUsuarioToId[$extKey] = $idU;
                if (is_numeric($extKey)) {
                    $extUsuarioToId[(string)(int)$extKey] = $idU;
                }
            } else {
                $noEncCed[] = $ced;
            }
            if ($mapHom['pareja'] >= 0 && $idU > 0) {
                $pkey = trim((string)($row[$mapHom['pareja']] ?? ''));
                if ($pkey !== '') {
                    $parejaToIds[$pkey][] = $idU;
                }
            }
        }
        $stats['homologacion_sin_usuario'] = count(array_unique($noEncCed));
        $stats['cedulas_no_encontradas'] = array_values(array_unique($noEncCed));

        $enr = self::fase1Enriquecer($pdo, $homologRows);
        $filasHom = $enr['filas'];
        if (count($filasHom) < 2) {
            $stats['errores'][] = 'Homologación sin filas de datos.';
            return $stats;
        }
        $hHom = $filasHom[0];
        $idxIdUsuarioHom = count($hHom) - 1;
        $stats['filas_bloque_cedulas'] = count($filasHom) - 1;
        $stats['mapeos_usuario_externo'] = count($extUsuarioToId);
        $stats['columna_usuario_homolog'] = $iExtHom >= 0;
        $stats['cedulas_con_usuario_mistorneos'] = $filasHomologConId;

        $headerRes = $rowsResultados[0];
        $hNorm = array_map(static fn ($x) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$x))), $headerRes);
        $find = static function (array $h, array $names): int {
            foreach ($names as $n) {
                $n = strtolower($n);
                foreach ($h as $i => $col) {
                    if (str_contains((string)$col, $n) || $col === $n) {
                        return $i;
                    }
                }
            }
            return -1;
        };
        $iPart = $find($hNorm, ['partida', 'ronda']);
        $iMesa = $find($hNorm, ['mesa']);
        $iSeq = $find($hNorm, ['secuencia', 'seq']);
        $iR1 = $find($hNorm, ['resultado1', 'r1', 'pts1']);
        $iR2 = $find($hNorm, ['resultado2', 'r2', 'pts2']);
        $iFf = $find($hNorm, ['ff', 'forfait']);
        $iCed = $find($hNorm, ['cedula', 'cedula1', 'ci', 'documento']);
        $iPareja = $find($hNorm, ['pareja', 'id_pareja', 'parejas']);
        $iJug = $find($hNorm, ['jugador', 'miembro', 'pos_pareja', 'jp', 'slot']);
        $iExtRes = -1;
        foreach ($hNorm as $ri => $col) {
            if ($col === 'usuario') {
                $iExtRes = $ri;
                break;
            }
        }

        if ($iPart < 0 || $iMesa < 0 || $iSeq < 0 || $iR1 < 0 || $iR2 < 0) {
            $stats['errores'][] = 'Resultados: faltan partida, mesa, secuencia, r1/resultado1 o r2/resultado2.';
            $stats['columna_usuario_resultados'] = $iExtRes >= 0;
            return $stats;
        }
        $stats['columna_usuario_resultados'] = $iExtRes >= 0;
        $puedePorExt = $iExtRes >= 0 && $extUsuarioToId !== [];
        if ($iExtRes >= 0 && $extUsuarioToId === []) {
            $muestra = array_slice($noEncCed, 0, 15);
            $stats['errores'][] = 'Mapa vacío: ninguna cédula del bloque homologación existe en usuarios (o filas vacías). Cédulas no encontradas (muestra): ' . implode(', ', $muestra) . '. — Formato hoja 1: columna A id externo (37), columna B cédula (4906763), o al revés; fila 1 = títulos.';
            return $stats;
        }
        if ($iCed < 0 && ($iPareja < 0 || $iJug < 0) && !$puedePorExt) {
            $stats['errores'][] = 'En resultados hace falta la columna usuario (mismo id externo que en homologación). No hace falta cédula ahí: partida, mesa, secuencia, usuario, r1, r2…';
            return $stats;
        }

        $stmtU = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? OR cedula = ? LIMIT 1');
        $nuevasFilas = [];
        $nuevasFilas[] = ['partida', 'mesa', 'secuencia', 'id_usuario', 'resultado1', 'resultado2', 'ff'];

        for ($r = 1; $r < count($rowsResultados); $r++) {
            $row = $rowsResultados[$r];
            $idUsuario = 0;
            if ($iExtRes >= 0 && $extUsuarioToId !== []) {
                $uk = trim((string)($row[$iExtRes] ?? ''));
                if ($uk !== '') {
                    $idUsuario = (int)($extUsuarioToId[$uk] ?? $extUsuarioToId[(string)(int)$uk] ?? 0);
                }
            }
            if ($idUsuario <= 0 && $iCed >= 0) {
                $ced = self::normalizarCedula($row[$iCed] ?? '');
                if ($ced !== '') {
                    $idUsuario = (int)($cedulaToId[$ced] ?? $cedulaToId[preg_replace('/\D/', '', $ced)] ?? 0);
                    if ($idUsuario <= 0) {
                        $stmtU->execute([$ced, preg_replace('/\D/', '', $ced)]);
                        $idUsuario = (int)($stmtU->fetchColumn() ?: 0);
                    }
                }
            }
            if ($idUsuario <= 0 && $iPareja >= 0) {
                $pkey = trim((string)($row[$iPareja] ?? ''));
                $j = $iJug >= 0 ? max(1, (int)($row[$iJug] ?? 1)) : 1;
                if ($pkey !== '' && $iJug >= 0 && isset($parejaToIds[$pkey][$j - 1])) {
                    $idUsuario = (int)$parejaToIds[$pkey][$j - 1];
                }
            }
            if ($idUsuario <= 0) {
                $stats['resultados_sin_resolver']++;
                continue;
            }
            $ff = ($iFf >= 0 && (int)($row[$iFf] ?? 0) === 1) ? 1 : 0;
            $nuevasFilas[] = [
                (string)(int)($row[$iPart] ?? 0),
                (string)(int)($row[$iMesa] ?? 0),
                (string)(int)($row[$iSeq] ?? 0),
                (string)$idUsuario,
                (string)(int)($row[$iR1] ?? 0),
                (string)(int)($row[$iR2] ?? 0),
                (string)$ff,
            ];
        }

        $resInsert = self::fase2InsertarPartiresul($pdo, $torneo_id, $registrado_por, $fechaTorneoYmd, $nuevasFilas);
        $stats['insertados'] = $resInsert['insertados'];
        $stats['errores'] = $resInsert['errores'];
        $stats['filas_listas_para_insertar'] = max(0, count($nuevasFilas) - 1);

        return $stats;
    }

    /**
     * Un solo Excel/CSV:
     * - Opción A: dos hojas — Hoja1 = homologación (cédula + usuario id externo), Hoja2 = resultados.
     * - Opción B: una hoja — arriba bloque homologación (1ª fila encabezados cédula + usuario), debajo fila de encabezados
     *   de resultados (partida, mesa, secuencia, usuario, r1, r2…) y el resto de filas.
     *
     * @return array{0: list<list<string>>, 1: list<list<string>>, error: string}
     */
    public static function dividirArchivoUnico(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx' && is_readable($path)) {
            require_once __DIR__ . '/CargaMasivaXlsxReader.php';
            $sheets = CargaMasivaXlsxReader::leerTodasHojasEnOrden($path);
            if (count($sheets) >= 2) {
                return [$sheets[0], $sheets[1], ''];
            }
            if (count($sheets) === 1) {
                [$a, $b] = self::dividirUnaTablaEnDosBloques($sheets[0]);
                return [$a, $b, ($a === [] || $b === []) ? 'En una sola hoja no se encontró el bloque de resultados (fila con columnas partida, mesa, secuencia) después del bloque cédula+usuario.' : ''];
            }
            return [[], [], 'No se pudo leer el Excel.'];
        }
        $rows = self::leerExcelOCsv($path, $originalName);
        [$a, $b] = self::dividirUnaTablaEnDosBloques($rows);
        return [$a, $b, ($a === [] || $b === []) ? 'No se detectaron dos bloques: arriba cédula+usuario, abajo cabecera partida/mesa/secuencia.' : ''];
    }

    /**
     * @return array{0: list<list<string>>, 1: list<list<string>>}
     */
    private static function dividirUnaTablaEnDosBloques(array $rows): array
    {
        if (count($rows) < 2) {
            return [[], []];
        }
        for ($i = 0; $i < count($rows); $i++) {
            $h = array_map(static fn ($x) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$x))), $rows[$i]);
            $part = $mesa = $seq = false;
            foreach ($h as $c) {
                if ($c === 'partida' || str_contains((string)$c, 'partida')) {
                    $part = true;
                }
                if ($c === 'mesa') {
                    $mesa = true;
                }
                if ($c === 'secuencia' || str_contains((string)$c, 'secuencia') || $c === 'seq') {
                    $seq = true;
                }
            }
            if ($part && $mesa && $seq) {
                $homolog = array_slice($rows, 0, $i);
                if ($homolog === []) {
                    return [[], []];
                }
                return [$homolog, array_slice($rows, $i)];
            }
        }
        return [[], []];
    }

    /**
     * @return array{insertados: int, errores: list<string>, homologacion_sin_usuario: int, resultados_sin_resolver: int, cedulas_no_encontradas: list<string>, split_error: string}
     */
    public static function importarUnSoloArchivoPartiresul(
        PDO $pdo,
        int $torneo_id,
        int $registrado_por,
        string $fechaTorneoYmd,
        string $path,
        string $originalName
    ): array {
        [$hom, $res, $err] = self::dividirArchivoUnico($path, $originalName);
        $stats = self::importarDosArchivosPartiresul($pdo, $torneo_id, $registrado_por, $fechaTorneoYmd, $hom, $res);
        $stats['split_error'] = $err;
        $stats['filas_hoja_homolog_raw'] = max(0, count($hom) - 1);
        $stats['filas_hoja_resultados_raw'] = max(0, count($res) - 1);
        return $stats;
    }

    private static function normalizarCedula(string $c): string
    {
        return trim(preg_replace('/\s+/', '', $c));
    }

    /**
     * @param list<string> $header
     * @param array<string, list<string>> $keys
     * @return array<string, int>
     */
    private static function mapearIndices(array $header, array $keys): array
    {
        $h = array_map(static fn ($x) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$x))), $header);
        $out = ['pareja' => -1, 'cedula' => -1];
        foreach ($keys as $name => $aliases) {
            foreach ($aliases as $a) {
                $a = strtolower($a);
                foreach ($h as $i => $col) {
                    if ($col === $a || str_contains($col, $a)) {
                        $out[$name] = $i;
                        break 2;
                    }
                }
            }
        }
        return $out;
    }

    private static function efectividad(int $r1, int $r2, int $puntosTorneo, int $ff): int
    {
        if ($ff === 1) {
            return -$puntosTorneo;
        }
        $max = max($r1, $r2);
        if ($max >= $puntosTorneo) {
            if ($r1 === $r2) {
                return 0;
            }
            return $r1 > $r2 ? ($puntosTorneo - $r2) : -($puntosTorneo - $r1);
        }
        if ($r1 === $r2) {
            return 0;
        }
        return $r1 > $r2 ? ($r1 - $r2) : -($r2 - $r1);
    }
}
