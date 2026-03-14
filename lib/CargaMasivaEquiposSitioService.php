<?php
/**
 * Carga masiva equipos (4 integrantes): validación previa, borrado inscritos/equipos del torneo con confirmación, GuardarEquipoSitioService.
 */
declare(strict_types=1);

final class CargaMasivaEquiposSitioService
{
    public const JUGADORES_REQUERIDOS = 4;

    /** Frase exacta que el operador debe enviar para ejecutar el reemplazo total. */
    public const CONFIRMACION_REEMPLAZO = 'SI_REEMPLAZAR_INSCRITOS_Y_EQUIPOS';

    /**
     * CSV de plantilla (una línea de encabezados + ejemplo).
     */
    public static function contenidoPlantillaCsv(): string
    {
        $enc = 'NAC,Cedula,,N1,,sexo,fecha_nac,telefono,email,equipo,club,organizacion';
        $ejR = 'R,,,,,,,,,EQUIPO EJEMPLO,MI CLUB,NOMBRE ORG';
        $ej1 = ',V12345678,,JUAN PEREZ,,M,1990-05-10,04140000000,juan@mail.com,,,';
        $ej2 = ',V87654321,,MARIA LOPEZ,,F,1992-01-20,,maria@mail.com,,,';
        $ej3 = ',V11111111,,PEDRO RUIZ,,M,,,,,,,';
        $ej4 = ',V22222222,,ANA GOMEZ,,F,,,,,,,';
        return "\xEF\xBB\xBF" . implode("\n", [$enc, $ejR, $ej1, $ej2, $ej3, $ej4]) . "\n";
    }

    /**
     * @return array{filas: list<array<int,string>>, map: array<string,int>, bloques: list<array>, error?: string}
     */
    public static function parseArchivo(string $tmpPath, string $originalName): array
    {
        require_once __DIR__ . '/EquiposHelper.php';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = self::leerFilas($tmpPath, $ext);
        if ($rows === []) {
            return ['filas' => [], 'map' => [], 'bloques' => [], 'error' => 'Archivo vacío o ilegible.'];
        }
        $header = array_shift($rows);
        $map = self::mapearCabeceras($header);
        $bloques = self::agruparPorEquipo($rows, $map);
        return ['filas' => $rows, 'map' => $map, 'bloques' => $bloques];
    }

    /**
     * @param list<array> $bloques
     * @return array{
     *   puede_proceder: bool,
     *   cedulas_duplicadas: list<array{cedula: string, apariciones: list<array{equipo: string, linea: int}>}>,
     *   equipos_incompletos: list<array{equipo: string, linea_inicio: int, integrantes: int, requeridos: int, detalle: string}>,
     *   bloques_sin_r: list<string>,
     *   resumen: array{equipos_en_archivo: int, total_inscritos_torneo: int, total_equipos_torneo: int}
     * }
     */
    public static function validarPrevio(PDO $pdo, int $torneo_id, array $bloques): array
    {
        $cedulaApariciones = [];
        $equiposIncompletos = [];
        $bloquesSinR = [];

        foreach ($bloques as $bloque) {
            $nombreEquipo = $bloque['nombre_equipo'];
            $linea = $bloque['linea_inicio'];
            $miembros = $bloque['miembros'];
            if ($nombreEquipo === '' && count($miembros) === 0) {
                continue;
            }
            if ($nombreEquipo === '') {
                $bloquesSinR[] = "Bloque sin fila de equipo (R) cerca de línea {$linea}";
                continue;
            }
            $validos = 0;
            foreach ($miembros as $m) {
                $c = trim((string)($m['cedula'] ?? ''));
                $n = trim((string)($m['n1'] ?? ''));
                if ($c !== '' && $n !== '') {
                    $validos++;
                    $cNorm = self::normalizarCedula($c);
                    if (!isset($cedulaApariciones[$cNorm])) {
                        $cedulaApariciones[$cNorm] = [];
                    }
                    $cedulaApariciones[$cNorm][] = ['cedula' => $c, 'equipo' => $nombreEquipo, 'linea' => $linea];
                }
            }
            if ($validos !== self::JUGADORES_REQUERIDOS) {
                $faltan = self::JUGADORES_REQUERIDOS - $validos;
                $equiposIncompletos[] = [
                    'equipo' => $nombreEquipo,
                    'linea_inicio' => $linea,
                    'integrantes' => $validos,
                    'requeridos' => self::JUGADORES_REQUERIDOS,
                    'detalle' => $validos < self::JUGADORES_REQUERIDOS
                        ? "Faltan {$faltan} jugador(es) con Cedula y N1 completos."
                        : "Sobran " . ($validos - self::JUGADORES_REQUERIDOS) . " fila(s) con datos válidos (máximo " . self::JUGADORES_REQUERIDOS . ").",
                ];
            }
        }

        $duplicadas = [];
        foreach ($cedulaApariciones as $norm => $aps) {
            if (count($aps) > 1) {
                $duplicadas[] = [
                    'cedula' => $aps[0]['cedula'],
                    'apariciones' => array_map(static fn ($a) => ['equipo' => $a['equipo'], 'linea' => $a['linea']], $aps),
                ];
            }
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?');
        $stmt->execute([$torneo_id]);
        $nInsc = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE id_torneo = ?');
        $stmt->execute([$torneo_id]);
        $nEq = (int)$stmt->fetchColumn();

        if (count($bloques) === 0) {
            $bloquesSinR[] = 'No se encontró ningún bloque de equipo (fila con NAC=R, equipo y club).';
        }
        $puede = $duplicadas === [] && $equiposIncompletos === [] && $bloquesSinR === [] && count($bloques) > 0;

        return [
            'puede_proceder' => $puede,
            'cedulas_duplicadas' => $duplicadas,
            'equipos_incompletos' => $equiposIncompletos,
            'bloques_sin_r' => $bloquesSinR,
            'resumen' => [
                'equipos_en_archivo' => count($bloques),
                'total_inscritos_torneo' => $nInsc,
                'total_equipos_torneo' => $nEq,
            ],
        ];
    }

    /**
     * @return array{success:bool,message:string,...}
     */
    public static function ejecutarDesdeArchivo(
        PDO $pdo,
        int $torneo_id,
        string $tmpPath,
        string $originalName,
        ?int $creado_por,
        string $confirmacion
    ): array {
        require_once __DIR__ . '/GuardarEquipoSitioService.php';
        require_once __DIR__ . '/EquiposHelper.php';
        require_once __DIR__ . '/security.php';

        if (!hash_equals(self::CONFIRMACION_REEMPLAZO, $confirmacion)) {
            return [
                'success' => false,
                'message' => 'Debe confirmar el reemplazo total con la frase indicada en pantalla.',
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }

        $stmt = $pdo->prepare('SELECT id, modalidad, organizacion_id FROM tournaments WHERE id = ?');
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$torneo || (int)$torneo['modalidad'] !== EquiposHelper::MODALIDAD_EQUIPOS) {
            return [
                'success' => false,
                'message' => 'Torneo no existe o no es modalidad equipos.',
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }
        $orgTorneo = (int)($torneo['organizacion_id'] ?? 0);

        $parsed = self::parseArchivo($tmpPath, $originalName);
        if (isset($parsed['error'])) {
            return [
                'success' => false,
                'message' => $parsed['error'],
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }
        $bloques = $parsed['bloques'];
        $val = self::validarPrevio($pdo, $torneo_id, $bloques);
        if (!$val['puede_proceder']) {
            return [
                'success' => false,
                'message' => 'Validación fallida: corrija cédulas duplicadas o equipos incompletos y vuelva a validar.',
                'validacion' => $val,
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM inscritos WHERE torneo_id = ?')->execute([$torneo_id]);
            $pdo->prepare('DELETE FROM equipos WHERE id_torneo = ?')->execute([$torneo_id]);

            $detalles = [];
            $ok = 0;
            $err = 0;
            foreach ($bloques as $bloque) {
                $nombreEquipo = $bloque['nombre_equipo'];
                $clubNombre = $bloque['club'];
                $orgNombre = $bloque['organizacion'];
                $linea = $bloque['linea_inicio'];
                $miembros = $bloque['miembros'];

                $club_id = self::resolverClubId($pdo, $clubNombre, $orgNombre, $orgTorneo);
                if ($club_id <= 0) {
                    $err++;
                    $detalles[] = ['equipo' => $nombreEquipo, 'ok' => false, 'message' => 'No se pudo resolver club.', 'linea_inicio' => $linea];
                    continue;
                }

                $jugadores = [];
                foreach ($miembros as $m) {
                    $cedula = trim((string)($m['cedula'] ?? ''));
                    $nombre = trim((string)($m['n1'] ?? ''));
                    if ($cedula === '' || $nombre === '') {
                        continue;
                    }
                    self::asegurarUsuarioAfiliado($pdo, $cedula, $nombre, $club_id, $m);
                    $jugadores[] = ['cedula' => $cedula, 'nombre' => $nombre, 'id_usuario' => 0, 'id_inscrito' => 0];
                }

                $input = [
                    'torneo_id' => $torneo_id,
                    'equipo_id' => 0,
                    'nombre_equipo' => $nombreEquipo,
                    'club_id' => $club_id,
                    'jugadores' => $jugadores,
                ];
                try {
                    $out = GuardarEquipoSitioService::ejecutar($pdo, $input, $creado_por);
                    if (!empty($out['success'])) {
                        $ok++;
                        $detalles[] = ['equipo' => $nombreEquipo, 'ok' => true, 'message' => $out['message'] ?? 'OK', 'linea_inicio' => $linea];
                    } else {
                        $err++;
                        $detalles[] = ['equipo' => $nombreEquipo, 'ok' => false, 'message' => $out['message'] ?? 'Error', 'linea_inicio' => $linea];
                    }
                } catch (Throwable $e) {
                    $err++;
                    $detalles[] = ['equipo' => $nombreEquipo, 'ok' => false, 'message' => $e->getMessage(), 'linea_inicio' => $linea];
                }
            }
            $pdo->commit();
            $total = count($bloques);
            return [
                'success' => $ok > 0 && $err === 0,
                'message' => "Reemplazo ejecutado. Equipos en archivo: {$total}. OK: {$ok}. Errores: {$err}.",
                'equipos_procesados' => $total,
                'equipos_ok' => $ok,
                'equipos_error' => $err,
                'detalles' => $detalles,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }
    }

    private static function normalizarCedula(string $c): string
    {
        return strtoupper(preg_replace('/\s+/', '', $c));
    }

    private static function codigoOrganizacion(PDO $pdo, int $orgId): string
    {
        if ($orgId <= 0) {
            return 'ORG0';
        }
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM organizaciones')->fetchAll(PDO::FETCH_COLUMN);
            if (is_array($cols) && in_array('codigo', $cols, true)) {
                $stmt = $pdo->prepare('SELECT codigo FROM organizaciones WHERE id = ?');
                $stmt->execute([$orgId]);
                $c = trim((string)($stmt->fetchColumn() ?: ''));
                if ($c !== '') {
                    return $c;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        return 'ORG-' . $orgId;
    }

    /**
     * Club por nombre; si no existe en la org del torneo, club ficticio con nombre = código organización.
     */
    private static function resolverClubId(PDO $pdo, string $clubNombre, string $orgNombre, int $orgTorneo): int
    {
        if ($orgNombre !== '') {
            $stmt = $pdo->prepare(
                'SELECT c.id FROM clubes c
                 INNER JOIN organizaciones o ON o.id = c.organizacion_id
                 WHERE c.estatus = 1 AND UPPER(TRIM(c.nombre)) = UPPER(TRIM(?))
                 AND UPPER(TRIM(o.nombre)) = UPPER(TRIM(?)) LIMIT 1'
            );
            $stmt->execute([$clubNombre, $orgNombre]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }
        if ($orgTorneo > 0) {
            $stmt = $pdo->prepare(
                'SELECT c.id FROM clubes c
                 WHERE c.estatus = 1 AND c.organizacion_id = ? AND UPPER(TRIM(c.nombre)) = UPPER(TRIM(?)) LIMIT 1'
            );
            $stmt->execute([$orgTorneo, $clubNombre]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }
        $stmt = $pdo->prepare('SELECT id FROM clubes WHERE estatus = 1 AND UPPER(TRIM(nombre)) = UPPER(TRIM(?)) LIMIT 1');
        $stmt->execute([$clubNombre]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        if ($orgTorneo <= 0) {
            return 0;
        }
        $codigo = self::codigoOrganizacion($pdo, $orgTorneo);
        $stmt = $pdo->prepare(
            'SELECT id FROM clubes WHERE organizacion_id = ? AND UPPER(TRIM(nombre)) = UPPER(TRIM(?)) LIMIT 1'
        );
        $stmt->execute([$orgTorneo, $codigo]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        return self::crearClubDefectoOrganizacion($pdo, $orgTorneo, $codigo);
    }

    private static function crearClubDefectoOrganizacion(PDO $pdo, int $orgId, string $nombreClub): int
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO clubes (nombre, organizacion_id, estatus, direccion, delegado, telefono, email)
                 VALUES (?, ?, 1, \'\', \'\', \'\', \'\')'
            );
            $stmt->execute([$nombreClub, $orgId]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            $stmt = $pdo->prepare('SELECT id FROM clubes WHERE organizacion_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$orgId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : 0;
        }
    }

    private static function leerFilas(string $path, string $ext): array
    {
        if ($ext === 'csv') {
            $rows = [];
            $fh = fopen($path, 'rb');
            if ($fh === false) {
                return [];
            }
            while (($line = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                $rows[] = array_map(static fn ($c) => (string)$c, $line);
            }
            fclose($fh);
            if ($rows === [] || (count($rows) === 1 && trim(implode('', $rows[0])) === '')) {
                $rows = [];
                $fh = fopen($path, 'rb');
                if ($fh !== false) {
                    while (($line = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
                        $rows[] = array_map(static fn ($c) => (string)$c, $line);
                    }
                    fclose($fh);
                }
            }
            return $rows;
        }
        if (in_array($ext, ['xlsx', 'xls'], true) && is_file(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = [];
                foreach ($sheet->toArray(null, true, true, false) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $rows[] = array_map(static fn ($c) => $c === null ? '' : (string)$c, $row);
                }
                return $rows;
            } catch (Throwable $e) {
                return [];
            }
        }
        return [];
    }

    private static function mapearCabeceras(array $header): array
    {
        $map = [];
        foreach ($header as $i => $h) {
            $key = strtolower(trim(preg_replace('/\s+/', '_', (string)$h)));
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $i;
            }
        }
        $defaults = ['nac' => 0, 'cedula' => 1, 'n1' => 3, 'sexo' => 5, 'fecha_nac' => 6, 'telefono' => 7, 'email' => 8, 'equipo' => 9, 'club' => 10, 'organizacion' => 11];
        foreach ($defaults as $k => $i) {
            if (!isset($map[$k])) {
                $map[$k] = $i;
            }
        }
        return $map;
    }

    private static function agruparPorEquipo(array $rows, array $map): array
    {
        $bloques = [];
        $current = null;
        $lineNum = 2;
        foreach ($rows as $row) {
            $nac = strtoupper(trim(self::cel($row, $map['nac'] ?? 0)));
            $equipo = trim(self::cel($row, $map['equipo'] ?? 9));
            $club = trim(self::cel($row, $map['club'] ?? 10));
            $org = trim(self::cel($row, $map['organizacion'] ?? 11));
            if ($nac === 'R' && $equipo !== '' && $club !== '') {
                if ($current !== null) {
                    $bloques[] = $current;
                }
                $current = [
                    'nombre_equipo' => $equipo,
                    'club' => $club,
                    'organizacion' => $org,
                    'linea_inicio' => $lineNum,
                    'miembros' => [],
                ];
            } elseif ($current !== null && $nac !== 'R') {
                $cedula = trim(self::cel($row, $map['cedula'] ?? 1));
                if ($cedula !== '') {
                    $current['miembros'][] = [
                        'cedula' => $cedula,
                        'n1' => trim(self::cel($row, $map['n1'] ?? 3)),
                        'sexo' => trim(self::cel($row, $map['sexo'] ?? 5)),
                        'fecha_nac' => trim(self::cel($row, $map['fecha_nac'] ?? 6)),
                        'telefono' => trim(self::cel($row, $map['telefono'] ?? 7)),
                        'email' => trim(self::cel($row, $map['email'] ?? 8)),
                    ];
                }
            }
            $lineNum++;
        }
        if ($current !== null) {
            $bloques[] = $current;
        }
        return $bloques;
    }

    private static function cel(array $row, int $idx): string
    {
        return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
    }

    private static function asegurarUsuarioAfiliado(PDO $pdo, string $cedula, string $nombre, int $club_id, array $m): void
    {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? LIMIT 1');
        $stmt->execute([$cedula]);
        if ($stmt->fetchColumn()) {
            return;
        }
        $email = trim($m['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'bulk_' . preg_replace('/\W/', '', $cedula) . '@carga-masiva.local';
        }
        $username = 'cm_' . preg_replace('/\W/', '_', $cedula) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $sexo = strtoupper(substr(trim($m['sexo'] ?? ''), 0, 1));
        if (!in_array($sexo, ['M', 'F'], true)) {
            $sexo = 'M';
        }
        $fechnac = trim($m['fecha_nac'] ?? '');
        if ($fechnac !== '' && preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', $fechnac)) {
            $parts = preg_split('/[\/\-]/', $fechnac);
            if (count($parts) === 3) {
                $d = (int)$parts[0];
                $mo = (int)$parts[1];
                $y = (int)$parts[2];
                if ($y < 100) {
                    $y += 2000;
                }
                $fechnac = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        } elseif ($fechnac === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechnac)) {
            $fechnac = '1990-01-01';
        }
        $hash = Security::hashPassword(bin2hex(random_bytes(8)));
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, cedula, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'usuario\', ?, 0, \'approved\')'
            );
            $stmt->execute([$nombre, $cedula, $sexo, $fechnac, $email, $username, $hash, $club_id]);
        } catch (Throwable $e) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nombre, cedula, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, \'usuario\', ?, 0, 0)'
                );
                $stmt->execute([$nombre, $cedula, $sexo, $fechnac, $email, $username, $hash, $club_id]);
            } catch (Throwable $e2) {
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? LIMIT 1');
                $stmt->execute([$cedula]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException('No se pudo crear usuario para cédula ' . $cedula . ': ' . $e2->getMessage());
                }
            }
        }
    }
}
