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
        $map = self::mapearIndices($header, ['pareja' => ['pareja', 'id_pareja', 'parejas'], 'cedula' => ['cedula', 'cedula1', 'ci', 'documento']]);
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
        ];
        if ($rowsHomologacion === [] || $rowsResultados === []) {
            $stats['errores'][] = 'Faltan filas en archivo de homologación o de resultados.';
            return $stats;
        }

        $enr = self::fase1Enriquecer($pdo, $rowsHomologacion);
        $filasHom = $enr['filas'];
        if (count($filasHom) < 2) {
            $stats['errores'][] = 'Homologación sin datos.';
            return $stats;
        }
        $hHom = $filasHom[0];
        $mapHom = self::mapearIndices($hHom, ['pareja' => ['pareja', 'id_pareja', 'parejas'], 'cedula' => ['cedula', 'cedula1', 'ci', 'documento']]);
        $idxIdUsuarioHom = count($hHom) - 1;
        $hNormHom = array_map(static fn ($x) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string)$x))), $hHom);
        $iExtHom = -1;
        foreach ($hNormHom as $hi => $col) {
            if ($col === 'usuario' || $col === 'id_externo' || $col === 'cod_jugador' || $col === 'id_jugador') {
                $iExtHom = $hi;
                break;
            }
        }

        $cedulaToId = [];
        $parejaToIds = [];
        /** @var array<string, int> usuario externo (otra plataforma) → id_usuario Mistorneos */
        $extUsuarioToId = [];
        for ($i = 1; $i < count($filasHom); $i++) {
            $row = $filasHom[$i];
            while (count($row) < count($hHom)) {
                $row[] = '';
            }
            $idU = (int)($row[$idxIdUsuarioHom] ?? 0);
            if ($mapHom['cedula'] >= 0) {
                $ced = self::normalizarCedula($row[$mapHom['cedula']] ?? '');
                if ($ced !== '' && $idU > 0) {
                    $cedulaToId[$ced] = $idU;
                    $cedulaToId[preg_replace('/\D/', '', $ced)] = $idU;
                }
            }
            if ($iExtHom >= 0 && $idU > 0) {
                $extKey = trim((string)($row[$iExtHom] ?? ''));
                if ($extKey !== '') {
                    $extUsuarioToId[$extKey] = $idU;
                    if (is_numeric($extKey)) {
                        $extUsuarioToId[(string)(int)$extKey] = $idU;
                    }
                }
            }
            if ($mapHom['pareja'] >= 0 && $idU > 0) {
                $pkey = trim((string)($row[$mapHom['pareja']] ?? ''));
                if ($pkey !== '') {
                    $parejaToIds[$pkey][] = $idU;
                }
            }
            if ($idU <= 0 && $mapHom['cedula'] >= 0 && trim((string)($row[$mapHom['cedula']] ?? '')) !== '') {
                $stats['homologacion_sin_usuario']++;
            }
        }
        $stats['cedulas_no_encontradas'] = $enr['no_encontradas'];

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
            return $stats;
        }
        $puedePorExt = $iExtRes >= 0 && $extUsuarioToId !== [];
        if ($iExtRes >= 0 && $extUsuarioToId === [] && $iExtHom < 0) {
            $stats['errores'][] = 'El archivo de resultados usa columna usuario (id del otro sistema). En el archivo de homologación debe haber las mismas columnas usuario + cédula por fila, para traducir a id_usuario de Mistorneos.';
            return $stats;
        }
        if ($iCed < 0 && ($iPareja < 0 || $iJug < 0) && !$puedePorExt) {
            $stats['errores'][] = 'En resultados: columna usuario (con homologación usuario+cédula), o cédula, o pareja+jugador.';
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
