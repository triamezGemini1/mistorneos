<?php
/**
 * Carga masiva parejas (2 integrantes): Excel con cabecera de columnas y bloques de 2 filas (jugador 1 / jugador 2).
 * Nacionalidad B: solo usuarios locales; si no existe se crea con datos de la hoja (sin BD externa).
 * V/E/J/P: usuarios → BD externa persona (si existe) → alta con datos de hoja.
 */
declare(strict_types=1);

final class CargaMasivaParejasSitioService
{
    public const JUGADORES_REQUERIDOS = 2;

    public const CONFIRMACION_REEMPLAZO = 'SI_REEMPLAZAR_INSCRITOS_Y_EQUIPOS';

    public static function contenidoPlantillaCsv(): string
    {
        $bom = "\xEF\xBB\xBF";
        $r0 = 'Torneo parejas — fila 1 título (opcional); fila 2 encabezados; luego parejas (2 filas por pareja). El código de pareja en el sistema será id_club (formulario) + consecutivo, formato 001-001. Columna «equipo» numérica (si existe) no altera ese código.';
        $r1 = 'Número;Nombre del equipo;Nombre y Apellido;nacionalidad;Ficha;Número de telefono';
        $r2 = '1;Pinky y Cerebro;Patricia Guerrera;B;10075198;4125587832';
        $r3 = ';;Issa Mansur;B;10079935;4128483524';
        return $bom . implode("\n", [$r0, $r1, $r2, $r3]) . "\n";
    }

    /**
     * @return array{bloques: list<array>, error?: string}
     */
    public static function parseArchivo(string $tmpPath, string $originalName): array
    {
        $err = null;
        $rows = self::leerFilasParaParejas($tmpPath, $originalName, $err);
        if ($rows === []) {
            return [
                'bloques' => [],
                'error' => $err ? 'No se pudieron leer filas. ' . $err : 'No se pudieron leer filas del archivo.',
            ];
        }
        $hdrIdx = self::encontrarFilaCabecera($rows);
        if ($hdrIdx < 0) {
            return [
                'bloques' => [],
                'error' => 'No se encontró la fila de encabezados de datos. Debe existir una fila con columnas de identificación (Ficha, Cédula, etc.), nacionalidad y nombre del jugador. Si usa dos filas de título encima, la fila de columnas debe incluir esas palabras (con o sin tilde).',
            ];
        }
        $map = self::mapearColumnas($rows[$hdrIdx]);
        if ($map['ficha'] === null || $map['nombre'] === null || $map['nac'] === null || $map['equipo'] === null) {
            $det = self::describirMapaFaltante($map);
            return [
                'bloques' => [],
                'error' => 'No se pudieron identificar todas las columnas obligatorias. ' . $det . ' Cabeceras leídas: «' . implode('» | «', array_map(static fn ($c) => (string) $c, $rows[$hdrIdx])) . '».',
            ];
        }
        $dataRows = array_slice($rows, $hdrIdx + 1);
        $bloques = [];
        $baseLine = $hdrIdx + 2;
        $n = count($dataRows);
        $pos = 0;
        while ($pos < $n) {
            while ($pos < $n && self::filaVacia($dataRows[$pos])) {
                $pos++;
            }
            if ($pos >= $n) {
                break;
            }
            $idx1 = $pos;
            $r1 = $dataRows[$pos++];
            while ($pos < $n && self::filaVacia($dataRows[$pos])) {
                $pos++;
            }
            if ($pos >= $n) {
                return [
                    'bloques' => [],
                    'error' => 'Falta la segunda fila de la pareja (jugador 2) antes del final del archivo. Última fila de jugador 1 detectada cerca de la línea ' . ($baseLine + $idx1) . ' del Excel (tras ignorar filas vacías).',
                ];
            }
            $idx2 = $pos;
            $r2 = $dataRows[$pos++];
            if (self::filaVacia($r1) && self::filaVacia($r2)) {
                continue;
            }
            $nombreEquipo = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim(self::cel($r1, $map['equipo'])));
            if ($nombreEquipo === '' && $map['equipo'] !== null) {
                $nombreEquipo = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim(self::cel($r2, $map['equipo'])));
            }
            $jug1 = self::extraerJugador($r1, $map);
            $jug2 = self::extraerJugador($r2, $map);
            $clubIdx = $map['club'] ?? null;
            $orgIdx = $map['organizacion'] ?? null;
            $bloques[] = [
                'nombre_equipo' => $nombreEquipo,
                'linea_inicio' => $baseLine + $idx1,
                'jugadores' => [$jug1, $jug2],
                'club_id_excel' => self::celEnteroDesdeColumna($r1, $clubIdx),
                'organizacion_excel' => self::celEnteroDesdeColumna($r1, $orgIdx),
            ];
        }
        return ['bloques' => $bloques];
    }

    private static function celEnteroDesdeColumna(array $row, ?int $idx): int
    {
        if ($idx === null) {
            return 0;
        }
        $d = preg_replace('/\D/', '', self::cel($row, $idx));
        return $d !== '' ? (int) $d : 0;
    }

    /**
     * .xlsx: prueba cada hoja en orden hasta encontrar cabeceras válidas (no solo la hoja con más filas).
     *
     * @return list<list<string>>
     */
    private static function leerFilasParaParejas(string $tmpPath, string $originalName, ?string &$err): array
    {
        require_once __DIR__ . '/CargaMasivaEquiposSitioService.php';
        $err = null;
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            if (class_exists('ZipArchive', false)) {
                require_once __DIR__ . '/CargaMasivaXlsxReader.php';
                $hojas = CargaMasivaXlsxReader::leerTodasHojasEnOrden($tmpPath);
                foreach ($hojas as $filas) {
                    if ($filas === []) {
                        continue;
                    }
                    $norm = CargaMasivaEquiposSitioService::normalizarFilasUtf8($filas);
                    if (self::encontrarFilaCabecera($norm) >= 0) {
                        return $norm;
                    }
                }
            }
            foreach ([__DIR__ . '/../vendor/autoload.php', dirname(__DIR__, 2) . '/vendor/autoload.php'] as $auto) {
                if (!is_file($auto)) {
                    continue;
                }
                require_once $auto;
                if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                    break;
                }
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
                    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                        $chunk = [];
                        foreach ($sheet->toArray(null, true, true, false) as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $chunk[] = array_map(static fn ($c) => $c === null ? '' : (string) $c, $row);
                        }
                        if ($chunk === []) {
                            continue;
                        }
                        $norm = CargaMasivaEquiposSitioService::normalizarFilasUtf8($chunk);
                        if (self::encontrarFilaCabecera($norm) >= 0) {
                            return $norm;
                        }
                    }
                } catch (Throwable $e) {
                    error_log('CargaMasivaParejas PhpSpreadsheet: ' . $e->getMessage());
                }
                break;
            }
        }
        return CargaMasivaEquiposSitioService::leerFilasDesdeArchivo($tmpPath, $originalName, $err);
    }

    /**
     * @param array{num: ?int, equipo: ?int, nombre: ?int, nac: ?int, ficha: ?int, tel: ?int, club: ?int, organizacion: ?int, equipo_codigo: ?int} $map
     */
    private static function describirMapaFaltante(array $map): string
    {
        $f = [];
        if ($map['ficha'] === null) {
            $f[] = 'Ficha / Cédula / documento';
        }
        if ($map['nombre'] === null) {
            $f[] = 'Nombre y apellido del jugador';
        }
        if ($map['nac'] === null) {
            $f[] = 'Nacionalidad';
        }
        if ($map['equipo'] === null) {
            $f[] = 'Nombre del equipo / pareja';
        }
        return 'Falta reconocer: ' . implode(', ', $f) . '.';
    }

    /**
     * @param list<array> $bloques
     * @return array{puede_proceder: bool, cedulas_duplicadas: list, equipos_incompletos: list, bloques_sin_r: list, clubs_invalidos: list, resumen: array, reporte_detallado: array}
     */
    public static function validarPrevio(PDO $pdo, int $torneo_id, array $bloques, int $club_id): array
    {
        $cedulaApariciones = [];
        $incompletos = [];
        $bloquesSin = [];
        $clubsInvalidos = [];
        $reporteEquipos = [];

        if ($club_id <= 0) {
            $clubsInvalidos[] = ['equipo' => '(general)', 'linea_inicio' => 0, 'detalle' => 'Seleccione un club válido en el formulario.'];
        } else {
            $st = $pdo->prepare('SELECT id FROM clubes WHERE id = ? AND (estatus = 1 OR estatus = \'1\' OR estatus = \'activo\') LIMIT 1');
            $st->execute([$club_id]);
            if (!$st->fetchColumn()) {
                $clubsInvalidos[] = ['equipo' => '(general)', 'linea_inicio' => 0, 'detalle' => 'El club indicado no existe o no está activo.'];
            }
        }

        foreach ($bloques as $bloque) {
            $nombreEquipo = $bloque['nombre_equipo'];
            $linea = $bloque['linea_inicio'];
            $jugadores = $bloque['jugadores'];
            $erroresEquipo = [];
            $clubExcel = (int) ($bloque['club_id_excel'] ?? 0);
            if ($club_id > 0 && $clubExcel > 0 && $clubExcel !== $club_id) {
                $clubsInvalidos[] = [
                    'equipo' => $nombreEquipo !== '' ? $nombreEquipo : '(pareja)',
                    'linea_inicio' => $linea,
                    'detalle' => "La columna «club» del Excel (id {$clubExcel}) no coincide con el club seleccionado en el formulario (id {$club_id}). Deje «club» vacío o use el mismo id.",
                ];
                $erroresEquipo[] = [
                    'tipo' => 'club',
                    'detalle' => 'Club del Excel distinto al seleccionado.',
                    'como_resolver' => 'Use el mismo id de club que en el desplegable o vacíe la columna club en el archivo.',
                ];
            }

            if ($nombreEquipo === '') {
                $incompletos[] = [
                    'equipo' => '(sin nombre)',
                    'linea_inicio' => $linea,
                    'integrantes' => 0,
                    'requeridos' => self::JUGADORES_REQUERIDOS,
                    'detalle' => 'La primera fila de cada pareja debe incluir el nombre del equipo/pareja.',
                ];
                $erroresEquipo[] = [
                    'tipo' => 'equipo',
                    'detalle' => 'Falta nombre de pareja en la primera fila del bloque.',
                    'como_resolver' => 'Complete la columna «Nombre del equipo» en la primera línea de cada pareja.',
                ];
            }

            $validos = 0;
            $integrantesReporte = [];
            foreach ($jugadores as $m) {
                $v = self::validarJugadorFila($m);
                $integrantesReporte[] = [
                    'cedula' => $m['cedula'],
                    'nombre' => $m['nombre'],
                    'nacionalidad' => $m['nacionalidad_raw'],
                    'completo' => $v === null,
                    'id_usuario' => self::idUsuarioPorCedula($pdo, $m['cedula']),
                    'numfvd' => self::numfvdPorCedula($pdo, $m['cedula']),
                    'detalle_val' => $v,
                ];
                if ($v === null) {
                    $validos++;
                    $cNorm = self::normalizarCedula($m['cedula']);
                    if (!isset($cedulaApariciones[$cNorm])) {
                        $cedulaApariciones[$cNorm] = [];
                    }
                    $cedulaApariciones[$cNorm][] = ['cedula' => $m['cedula'], 'equipo' => $nombreEquipo ?: '(sin nombre)', 'linea' => $linea];
                } elseif ($v !== '') {
                    $erroresEquipo[] = [
                        'tipo' => 'jugador',
                        'detalle' => $v,
                        'como_resolver' => 'Revise nombre, nacionalidad (B, V, E, J, P), ficha/cédula y formato de la pareja (2 filas).',
                    ];
                }
            }

            if ($nombreEquipo !== '' && $validos !== self::JUGADORES_REQUERIDOS) {
                $faltan = self::JUGADORES_REQUERIDOS - $validos;
                $incompletos[] = [
                    'equipo' => $nombreEquipo,
                    'linea_inicio' => $linea,
                    'integrantes' => $validos,
                    'requeridos' => self::JUGADORES_REQUERIDOS,
                    'detalle' => $validos < self::JUGADORES_REQUERIDOS
                        ? "Faltan {$faltan} jugador(es) válidos (nombre, nacionalidad y ficha)."
                        : 'Hay más datos inválidos de los esperados.',
                ];
            }

            $reporteEquipos[] = [
                'equipo' => $nombreEquipo ?: '(sin nombre)',
                'linea_inicio' => $linea,
                'integrantes' => $integrantesReporte,
                'ok' => $erroresEquipo === [] && $nombreEquipo !== '' && $validos === self::JUGADORES_REQUERIDOS,
                'errores' => $erroresEquipo,
            ];
        }

        $duplicadas = [];
        foreach ($cedulaApariciones as $aps) {
            if (count($aps) > 1) {
                $duplicadas[] = [
                    'cedula' => $aps[0]['cedula'],
                    'apariciones' => array_map(static fn ($a) => ['equipo' => $a['equipo'], 'linea' => $a['linea']], $aps),
                ];
            }
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inscritos WHERE torneo_id = ?');
        $stmt->execute([$torneo_id]);
        $nInsc = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM equipos WHERE id_torneo = ?');
        $stmt->execute([$torneo_id]);
        $nEq = (int) $stmt->fetchColumn();

        if (count($bloques) === 0) {
            $bloquesSin[] = 'No se detectaron parejas: revise cabeceras y que cada bloque tenga 2 filas de datos.';
        }

        $puede = $duplicadas === [] && $incompletos === [] && $bloquesSin === [] && $clubsInvalidos === [] && count($bloques) > 0;

        return [
            'puede_proceder' => $puede,
            'cedulas_duplicadas' => $duplicadas,
            'equipos_incompletos' => $incompletos,
            'bloques_sin_r' => $bloquesSin,
            'clubs_excel_invalidos' => $clubsInvalidos,
            'resumen' => [
                'equipos_en_archivo' => count($bloques),
                'total_inscritos_torneo' => $nInsc,
                'total_equipos_torneo' => $nEq,
            ],
            'reporte_detallado' => [
                'equipos' => $reporteEquipos,
                'recomendaciones_generales' => [
                    'Cada pareja: 2 filas; la primera lleva número/nombre de pareja y primer jugador; la segunda solo el segundo jugador.',
                    'Nacionalidad B: solo tabla local; otras letras consultan también la base externa de personas si está configurada.',
                    'Sin cédulas duplicadas en todo el archivo.',
                ],
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string, equipos_procesados?: int, equipos_ok?: int, equipos_error?: int, detalles?: list, validacion?: array, reporte_proceso?: array}
     */
    public static function ejecutarDesdeArchivo(
        PDO $pdo,
        int $torneo_id,
        string $tmpPath,
        string $originalName,
        int $club_id,
        ?int $creado_por,
        string $confirmacion
    ): array {
        require_once __DIR__ . '/GuardarEquipoSitioService.php';
        require_once __DIR__ . '/EquiposHelper.php';

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

        $stmt = $pdo->prepare('SELECT id, modalidad FROM tournaments WHERE id = ?');
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$torneo || (int) $torneo['modalidad'] !== EquiposHelper::MODALIDAD_PAREJAS) {
            return [
                'success' => false,
                'message' => 'Torneo no existe o no es modalidad parejas.',
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }

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
        $val = self::validarPrevio($pdo, $torneo_id, $bloques, $club_id);
        if (!$val['puede_proceder']) {
            return [
                'success' => false,
                'message' => 'Validación fallida: corrija el archivo o el club seleccionado.',
                'validacion' => $val,
                'equipos_procesados' => 0,
                'equipos_ok' => 0,
                'equipos_error' => 0,
                'detalles' => [],
            ];
        }

        $stClub = $pdo->prepare('SELECT COALESCE(entidad, 0) FROM clubes WHERE id = ? LIMIT 1');
        $stClub->execute([$club_id]);
        $entidad_club = (int) $stClub->fetchColumn();

        $detalles = [];
        $reporteProceso = [];
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM inscritos WHERE torneo_id = ?')->execute([$torneo_id]);
            $pdo->prepare('DELETE FROM equipos WHERE id_torneo = ?')->execute([$torneo_id]);
            $ok = 0;
            $err = 0;
            foreach ($bloques as $bloque) {
                $nombreEquipo = $bloque['nombre_equipo'];
                $linea = $bloque['linea_inicio'];
                $jugadoresRows = $bloque['jugadores'];
                $integrantesReporte = [];

                $jugadoresPayload = [];
                foreach ($jugadoresRows as $m) {
                    $uid = self::asegurarUsuarioPareja($pdo, $m, $club_id, $entidad_club);
                    $nombreN = CargaMasivaEquiposSitioService::normalizarTextoUtf8($m['nombre']);
                    $ced = $m['cedula'];
                    $integrantesReporte[] = [
                        'cedula' => $ced,
                        'nombre' => $nombreN,
                        'completo' => true,
                        'id_usuario' => $uid,
                        'numfvd' => self::numfvdPorCedula($pdo, $ced),
                    ];
                    $jugadoresPayload[] = ['cedula' => $ced, 'nombre' => $nombreN, 'id_usuario' => 0, 'id_inscrito' => 0];
                }

                // codigo_equipo = id del club (3 dígitos) + número de pareja en el torneo (consecutivo por club), p. ej. 001-001.
                // No se usa la columna «equipo» del Excel como prefijo: siempre el club elegido en el formulario.
                $input = [
                    'torneo_id' => $torneo_id,
                    'equipo_id' => 0,
                    'nombre_equipo' => $nombreEquipo,
                    'club_id' => $club_id,
                    'codigo_club_prefijo' => (string) $club_id,
                    'jugadores' => $jugadoresPayload,
                ];
                try {
                    $out = GuardarEquipoSitioService::ejecutar($pdo, $input, $creado_por);
                    if (!empty($out['success'])) {
                        $ok++;
                        $detalles[] = ['equipo' => $nombreEquipo, 'ok' => true, 'message' => $out['message'] ?? 'OK', 'linea_inicio' => $linea];
                        $verif = self::verificarDosInscritos($pdo, $torneo_id, array_column($integrantesReporte, 'id_usuario'));
                        if (!$verif['ok']) {
                            throw new RuntimeException('Verificación post-grabado: ' . $verif['detalle']);
                        }
                        $reporteProceso[] = [
                            'equipo' => $nombreEquipo,
                            'linea_inicio' => $linea,
                            'integrantes' => $integrantesReporte,
                            'ok' => true,
                            'error' => '',
                            'como_resolver' => '',
                        ];
                    } else {
                        $err++;
                        $msgErr = $out['message'] ?? 'Error';
                        $detalles[] = ['equipo' => $nombreEquipo, 'ok' => false, 'message' => $msgErr, 'linea_inicio' => $linea];
                        $reporteProceso[] = [
                            'equipo' => $nombreEquipo,
                            'linea_inicio' => $linea,
                            'integrantes' => $integrantesReporte,
                            'ok' => false,
                            'error' => $msgErr,
                            'como_resolver' => 'Revise cédulas duplicadas en el torneo o datos de jugadores.',
                        ];
                    }
                } catch (Throwable $e) {
                    $err++;
                    $msgErr = $e->getMessage();
                    $detalles[] = ['equipo' => $nombreEquipo, 'ok' => false, 'message' => $msgErr, 'linea_inicio' => $linea];
                    $reporteProceso[] = [
                        'equipo' => $nombreEquipo,
                        'linea_inicio' => $linea,
                        'integrantes' => $integrantesReporte,
                        'ok' => false,
                        'error' => $msgErr,
                        'como_resolver' => 'Corrija el problema indicado y vuelva a ejecutar.',
                    ];
                }
            }
            if ($err > 0) {
                throw new RuntimeException('Se detectaron errores durante la carga. La transacción fue revertida.');
            }
            $pdo->commit();
            $total = count($bloques);
            return [
                'success' => $ok > 0 && $err === 0,
                'message' => "Reemplazo ejecutado. Parejas en archivo: {$total}. OK: {$ok}. Errores: {$err}.",
                'equipos_procesados' => $total,
                'equipos_ok' => $ok,
                'equipos_error' => $err,
                'detalles' => $detalles,
                'reporte_proceso' => [
                    'resumen' => ['total' => $total, 'ok' => $ok, 'error' => $err],
                    'equipos' => $reporteProceso,
                    'recomendaciones_generales' => [],
                ],
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
                'detalles' => $detalles,
                'reporte_proceso' => [
                    'resumen' => ['total' => count($bloques ?? []), 'ok' => 0, 'error' => max(1, count($detalles))],
                    'equipos' => $reporteProceso,
                    'recomendaciones_generales' => [],
                ],
            ];
        }
    }

    /**
     * Localiza la fila de encabezados probando el mismo mapeo que usarán los datos (evita fallos con tildes o textos en varias celdas).
     */
    private static function encontrarFilaCabecera(array $rows): int
    {
        $max = min(25, count($rows));
        for ($i = 0; $i < $max; $i++) {
            $row = $rows[$i];
            if (!is_array($row)) {
                continue;
            }
            $nonEmpty = 0;
            foreach ($row as $c) {
                if (trim((string) $c) !== '') {
                    $nonEmpty++;
                }
            }
            if ($nonEmpty < 4) {
                continue;
            }
            $map = self::mapearColumnas($row);
            if ($map['ficha'] !== null && $map['nombre'] !== null && $map['nac'] !== null && $map['equipo'] !== null) {
                return $i;
            }
        }
        return -1;
    }

    private static function normalizarCabeceraCelda(string $cell): string
    {
        $s = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim($cell));
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private static function quitarAcentos(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $d = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($d !== false) {
                $s = preg_replace('/\pM/u', '', $d) ?? $s;
            }
        }
        return $s;
    }

    /**
     * Reconoce cabeceras como:
     * - Nombre equipo | n1 | nacionalidad | cedula | telefono | equipo | club | organizacion
     *   (aquí «equipo» suelto = prefijo/código numérico; el nombre va en «nombre equipo»).
     * - Formatos previos: número de pareja, «Nombre del equipo», columna «equipo» = nombre, etc.
     *
     * @return array{num: ?int, equipo: ?int, nombre: ?int, nac: ?int, ficha: ?int, tel: ?int, club: ?int, organizacion: ?int, equipo_codigo: ?int}
     */
    private static function mapearColumnas(array $headerRow): array
    {
        $map = [
            'num' => null,
            'equipo' => null,
            'nombre' => null,
            'nac' => null,
            'ficha' => null,
            'tel' => null,
            'club' => null,
            'organizacion' => null,
            'equipo_codigo' => null,
        ];
        foreach ($headerRow as $idx => $cell) {
            $h = self::normalizarCabeceraCelda((string) $cell);
            if ($h === '' || $h === '0') {
                continue;
            }
            $ha = self::quitarAcentos($h);
            if ($ha === 'equipo') {
                continue;
            }

            if (preg_match('/tel[eé]fono|telefono|celular|móvil|movil|whatsapp|telf\.?/u', $ha)) {
                $map['tel'] = $idx;
                continue;
            }
            if (preg_match('/^n[uú]mero\s+de\s+tel|^numero\s+de\s+tel/u', $ha)) {
                $map['tel'] = $idx;
                continue;
            }
            if (preg_match('/^organizaci[oó]n$/u', $ha)) {
                if ($map['organizacion'] === null) {
                    $map['organizacion'] = $idx;
                }
                continue;
            }
            if ($ha === 'club' || preg_match('/^id\s+club$/u', $ha) || preg_match('/^club\s+id$/u', $ha)) {
                if ($map['club'] === null) {
                    $map['club'] = $idx;
                }
                continue;
            }
            if (preg_match('/nacionalidad|^nac\.?$|pa[ií]s(?!\/)/u', $ha)) {
                $map['nac'] = $idx;
                continue;
            }
            if (preg_match('/ficha|^cedula$|^c[eé]dula$|^c[eé]dula|c\.?\s*i\.?|documento|identificaci[oó]n|(^|\s)id(\s|$)|rif|pasaporte|\bdni\b|no\.?\s*afiliaci|matr[ií]cula/u', $ha)) {
                $map['ficha'] = $idx;
                continue;
            }

            if (preg_match('/^equipo\/pareja$/u', $h)
                || preg_match('/^equipo\s*\/\s*pareja$/u', $h)
                || preg_match('/n[uú]mero\s*(de\s*)?pareja|^n[º°]\s*pareja$/u', $ha)
                || preg_match('/^(n[uú]mero|numero|num\.?|nro\.?|n[º°]|#)$/u', $ha)) {
                if ($map['num'] === null) {
                    $map['num'] = $idx;
                }
                continue;
            }
            if (preg_match('/^n[uú]mero\s*$/u', $ha) || preg_match('/^numero\s*$/u', $ha)) {
                if ($map['num'] === null) {
                    $map['num'] = $idx;
                }
                continue;
            }

            if (preg_match('/nombre\s+equipo\b|nombre\s+del\s+equipo|nombre\s+de\s+equipo|nombre\s+de\s+la\s+pareja|denominaci[oó]n\s+(del\s+)?(equipo|pareja)/u', $ha)) {
                $map['equipo'] = $idx;
                continue;
            }

            if (preg_match('/^n1$/u', $ha) || preg_match('/^n\s*1$/u', $ha) || preg_match('/^n2$/u', $ha) || preg_match('/^n\s*2$/u', $ha)) {
                if ($map['nombre'] === null) {
                    $map['nombre'] = $idx;
                }
                continue;
            }

            if (preg_match('/nombre\s+y\s+apellido|apellidos?\s+y\s+nombre|jugador|integrante|deportista|participante|miembro/u', $ha)) {
                if ($map['nombre'] === null) {
                    $map['nombre'] = $idx;
                }
                continue;
            }
            if (str_contains($ha, 'nombre') && !str_contains($ha, 'equipo') && !str_contains($ha, 'pareja') && $map['nombre'] === null) {
                $map['nombre'] = $idx;
            }
        }

        foreach ($headerRow as $idx => $cell) {
            $h = self::normalizarCabeceraCelda((string) $cell);
            if ($h === '' || $h === '0') {
                continue;
            }
            $ha = self::quitarAcentos($h);
            if ($ha !== 'equipo') {
                continue;
            }
            if ($map['equipo'] !== null) {
                if ($map['equipo_codigo'] === null) {
                    $map['equipo_codigo'] = $idx;
                }
            } elseif ($map['equipo_codigo'] === null) {
                $map['equipo'] = $idx;
            }
        }

        return $map;
    }

    /**
     * @param array{num: ?int, equipo: ?int, nombre: ?int, nac: ?int, ficha: ?int, tel: ?int, club: ?int, organizacion: ?int, equipo_codigo: ?int} $map
     * @return array{nombre: string, nacionalidad_raw: string, cedula: string, telefono: string}
     */
    private static function extraerJugador(array $row, array $map): array
    {
        $iNom = (int) ($map['nombre'] ?? 0);
        $iNac = (int) ($map['nac'] ?? 0);
        $iFicha = (int) ($map['ficha'] ?? 0);
        $nombre = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim(self::cel($row, $iNom)));
        $nac = strtoupper(trim(self::cel($row, $iNac)));
        $ced = self::celDocumento($row, $iFicha);
        $telIdx = $map['tel'];
        $tel = $telIdx !== null ? CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim(self::celTelefono($row, (int) $telIdx))) : '';
        return [
            'nombre' => $nombre,
            'nacionalidad_raw' => $nac,
            'cedula' => $ced,
            'telefono' => $tel,
        ];
    }

    private static function cel(array $row, int $idx): string
    {
        if (!isset($row[$idx])) {
            return '';
        }
        $v = $row[$idx];
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }
        if (is_int($v)) {
            return (string) $v;
        }
        return trim((string) $v);
    }

    /**
     * Cédula/ficha en Excel a menudo viene como número (float); evita notación científica y pierde caracteres no numéricos al final.
     */
    private static function celDocumento(array $row, int $idx): string
    {
        if (!isset($row[$idx])) {
            return '';
        }
        $v = $row[$idx];
        if (is_int($v)) {
            return preg_replace('/\D/', '', (string) $v);
        }
        if (is_float($v)) {
            $s = sprintf('%.0f', $v);
            return preg_replace('/\D/', '', $s);
        }
        $s = trim((string) $v);
        if ($s !== '' && preg_match('/^[+-]?\d+\.?\d*E[+-]?\d+$/i', $s)) {
            $s = sprintf('%.0f', (float) $s);
        } elseif (preg_match('/^\d+\.\d+$/', $s)) {
            $s = sprintf('%.0f', (float) $s);
        }
        return preg_replace('/\D/', '', $s);
    }

    private static function celTelefono(array $row, int $idx): string
    {
        if (!isset($row[$idx])) {
            return '';
        }
        $v = $row[$idx];
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }
        if (is_int($v)) {
            return (string) $v;
        }
        return trim((string) $v);
    }

    private static function filaVacia(array $row): bool
    {
        foreach ($row as $c) {
            if (trim((string) $c) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @return string|null null = OK; string = error; '' skip counting (should not happen) */
    private static function validarJugadorFila(array $m): ?string
    {
        $nac = strtoupper(trim($m['nacionalidad_raw']));
        if ($nac === '' || !in_array($nac, ['B', 'V', 'E', 'J', 'P'], true)) {
            return 'Nacionalidad inválida (use B, V, E, J o P).';
        }
        $ced = $m['cedula'];
        if ($ced === '' || strlen($ced) < 4) {
            return 'Ficha/cédula ausente o demasiado corta (mín. 4 dígitos).';
        }
        $nom = trim($m['nombre']);
        if ($nom === '' || mb_strlen($nom) < 2) {
            return 'Nombre y apellido obligatorio.';
        }
        return null;
    }

    private static function normalizarCedula(string $c): string
    {
        return strtoupper(preg_replace('/\s+/', '', $c));
    }

    private static function idUsuarioPorCedula(PDO $pdo, string $cedula): int
    {
        $ced = preg_replace('/\D/', '', trim($cedula));
        if ($ced === '') {
            return 0;
        }
        try {
            foreach ([$ced, 'V' . $ced, 'E' . $ced] as $valor) {
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? LIMIT 1');
                $stmt->execute([$valor]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return (int) ($row['id'] ?? 0);
                }
            }
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE REPLACE(REPLACE(cedula, "-", ""), " ", "") = ? LIMIT 1');
            $stmt->execute([$ced]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) ($row['id'] ?? 0) : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private static function numfvdPorCedula(PDO $pdo, string $cedula): int
    {
        $id = self::idUsuarioPorCedula($pdo, $cedula);
        if ($id <= 0) {
            return 0;
        }
        try {
            $stmt = $pdo->prepare('SELECT COALESCE(numfvd, 0) AS numfvd FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * @param list<int> $ids
     * @return array{ok: bool, detalle: string}
     */
    private static function verificarDosInscritos(PDO $pdo, int $torneoId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($x) => $x > 0)));
        if (count($ids) !== self::JUGADORES_REQUERIDOS) {
            return ['ok' => false, 'detalle' => 'No se resolvieron 2 usuarios distintos para la pareja.'];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT COUNT(*) FROM inscritos WHERE torneo_id = ? AND id_usuario IN ($ph)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$torneoId], $ids));
        $cnt = (int) $stmt->fetchColumn();
        if ($cnt !== self::JUGADORES_REQUERIDOS) {
            return ['ok' => false, 'detalle' => 'No coinciden los registros en inscritos (se esperaban 2).'];
        }
        return ['ok' => true, 'detalle' => ''];
    }

    /**
     * @param array{nombre: string, nacionalidad_raw: string, cedula: string, telefono: string} $jugador
     */
    private static function asegurarUsuarioPareja(PDO $pdo, array $jugador, int $club_id, int $entidad_club): int
    {
        require_once __DIR__ . '/security.php';

        $cedDigits = $jugador['cedula'];
        $uid = self::idUsuarioPorCedula($pdo, $cedDigits);
        if ($uid > 0) {
            $upd = $pdo->prepare('UPDATE usuarios SET club_id = ?, entidad = ? WHERE id = ?');
            $upd->execute([$club_id, $entidad_club, $uid]);
            return $uid;
        }

        $esB = strtoupper($jugador['nacionalidad_raw']) === 'B';
        $nacStd = $esB ? 'V' : strtoupper(substr($jugador['nacionalidad_raw'], 0, 1));
        if (!in_array($nacStd, ['V', 'E', 'J', 'P'], true)) {
            $nacStd = 'V';
        }

        $nombre = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim($jugador['nombre']));
        $telefono = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim($jugador['telefono']));
        $sexo = 'M';
        $fechnac = '1990-01-01';

        if (!$esB) {
            $persona = self::buscarPersonaExterna($nacStd, $cedDigits);
            if ($persona !== null) {
                $nombreExt = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim((string) ($persona['nombre'] ?? '')));
                if ($nombreExt !== '') {
                    $nombre = $nombreExt;
                }
                $sx = strtoupper(trim((string) ($persona['sexo'] ?? '')));
                if (in_array($sx, ['M', 'F', 'O'], true)) {
                    $sexo = $sx;
                }
                $fn = (string) ($persona['fechnac'] ?? '');
                if ($fn !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $fn)) {
                    $fechnac = substr($fn, 0, 10);
                }
                if ($telefono === '') {
                    $telefono = CargaMasivaEquiposSitioService::normalizarTextoUtf8(trim((string) ($persona['celular'] ?? '')));
                }
                $nacP = strtoupper(trim((string) ($persona['nacionalidad'] ?? '')));
                if (in_array($nacP, ['V', 'E', 'J', 'P'], true)) {
                    $nacStd = $nacP;
                }
            }
        }

        $email = 'parejas_' . preg_replace('/\W/', '', $cedDigits) . '@carga-masiva.local';
        $username = 'cp_' . preg_replace('/\W/', '_', $cedDigits) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $hash = Security::hashPassword(bin2hex(random_bytes(8)));
        $numfvdNuevo = 0;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, cedula, nacionalidad, numfvd, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'usuario\', ?, ?, \'approved\')'
            );
            $stmt->execute([$nombre, $cedDigits, $nacStd, $numfvdNuevo, $sexo, $fechnac, $email, $username, $hash, $club_id, $entidad_club]);
            $newId = (int) $pdo->lastInsertId();
            if ($newId > 0 && $telefono !== '') {
                try {
                    $u = $pdo->prepare('UPDATE usuarios SET celular = ? WHERE id = ?');
                    $u->execute([$telefono, $newId]);
                } catch (Throwable $e3) {
                    // columna opcional
                }
            }
            return $newId;
        } catch (Throwable $e) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nombre, cedula, nacionalidad, numfvd, sexo, fechnac, email, username, password_hash, role, club_id, entidad, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'usuario\', ?, ?, 0)'
                );
                $stmt->execute([$nombre, $cedDigits, $nacStd, $numfvdNuevo, $sexo, $fechnac, $email, $username, $hash, $club_id, $entidad_club]);
                $newId = (int) $pdo->lastInsertId();
                if ($newId > 0 && $telefono !== '') {
                    try {
                        $u = $pdo->prepare('UPDATE usuarios SET celular = ? WHERE id = ?');
                        $u->execute([$telefono, $newId]);
                    } catch (Throwable $e3) {
                    }
                }
                return $newId;
            } catch (Throwable $e2) {
                $uid2 = self::idUsuarioPorCedula($pdo, $cedDigits);
                if ($uid2 > 0) {
                    return $uid2;
                }
                throw new RuntimeException('No se pudo crear usuario para cédula ' . $cedDigits . ': ' . $e2->getMessage());
            }
        }
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function buscarPersonaExterna(string $nac, string $cedulaDigits): ?array
    {
        $path = __DIR__ . '/../config/persona_database.php';
        if (!is_file($path)) {
            return null;
        }
        require_once $path;
        try {
            $db = new PersonaDatabase();
            $res = $db->searchPersonaById($nac, $cedulaDigits);
            if (!empty($res['encontrado']) && !empty($res['persona']) && is_array($res['persona'])) {
                return $res['persona'];
            }
        } catch (Throwable $e) {
            error_log('CargaMasivaParejasSitioService externa: ' . $e->getMessage());
        }
        return null;
    }
}
