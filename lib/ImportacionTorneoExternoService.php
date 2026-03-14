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
