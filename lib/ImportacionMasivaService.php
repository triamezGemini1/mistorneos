<?php
declare(strict_types=1);

/**
 * Servicio de Importación Masiva para Torneos.
 * Validación previa, procesamiento en cascada (inscritos → usuarios → crear usuario/club), log de errores.
 * Cumple: .cursorruless (PSR-12, type hinting, prepared statements, try-catch).
 */

require_once __DIR__ . '/InscritosHelper.php';
require_once __DIR__ . '/UserActivationHelper.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/Repository/ClubRepository.php';

use Lib\Repository\ClubRepository;
use PDO;

class ImportacionMasivaService
{
    public const ESTADO_OMITIR = 'omitir';           // AZUL: ya inscrito
    public const ESTADO_INSCRIBIR = 'inscribir';     // AMARILLO: usuario existe, solo inscribir
    public const ESTADO_CREAR_INSCRIBIR = 'crear_inscribir'; // VERDE: todo nuevo
    public const ESTADO_ERROR = 'error';             // ROJO: datos inválidos

    public const TAMANO_LOTE = 20;

    /**
     * Normaliza y valida una fila para importación.
     * @return array{normalized: array, error: string|null}
     */
    public static function normalizarFila(array $fila, int $indiceFila): array
    {
        $trim = static function ($v) {
            return is_string($v) ? trim($v) : (string) $v;
        };
        $nacionalidad = strtoupper($trim($fila['nacionalidad'] ?? ''));
        if (!in_array($nacionalidad, ['V', 'E', 'J', 'P'], true)) {
            $nacionalidad = 'V';
        }
        $cedula = preg_replace('/\D/', '', $trim($fila['cedula'] ?? ''));
        $nombre = $trim($fila['nombre'] ?? '');
        $sexo = strtoupper($trim($fila['sexo'] ?? 'M'));
        if (!in_array($sexo, ['M', 'F', 'O'], true)) {
            $sexo = 'M';
        }
        $fechnac = $trim($fila['fecha_nac'] ?? $fila['fechnac'] ?? '');
        $telefono = $trim($fila['telefono'] ?? $fila['celular'] ?? '');
        $email = $trim($fila['email'] ?? '');
        $clubNombre = $trim($fila['club'] ?? $fila['club_nombre'] ?? '');
        $entidad = isset($fila['entidad']) ? (int) $fila['entidad'] : null;

        if ($cedula === '' || strlen($cedula) < 4) {
            return ['normalized' => [], 'error' => 'Cédula inválida o faltante (mín. 4 dígitos)'];
        }
        if ($nombre === '' || strlen($nombre) < 2) {
            return ['normalized' => [], 'error' => 'Nombre obligatorio (mín. 2 caracteres)'];
        }

        $normalized = [
            'nacionalidad' => $nacionalidad,
            'cedula' => $cedula,
            'nombre' => $nombre,
            'sexo' => $sexo,
            'fechnac' => $fechnac,
            'telefono' => $telefono,
            'email' => $email,
            'club_nombre' => $clubNombre,
            'entidad' => $entidad,
        ];
        return ['normalized' => $normalized, 'error' => null];
    }

    /**
     * Valida filas y devuelve estado por cada una (semáforo).
     * @param array<int, array> $filas Array de filas con keys normalizados
     * @return list<array{fila: int, estado: string, mensaje: string}>
     */
    public static function validarFilas(PDO $pdo, int $torneoId, array $filas): array
    {
        $resultado = [];
        $torneoId = (int) $torneoId;
        foreach ($filas as $idx => $fila) {
            $filaNum = $idx + 1;
            $norm = self::normalizarFila($fila, $filaNum);
            if ($norm['error'] !== null) {
                $resultado[] = ['fila' => $filaNum, 'estado' => self::ESTADO_ERROR, 'mensaje' => $norm['error']];
                continue;
            }
            $n = $norm['normalized'];
            $cedula = $n['cedula'];
            $nacionalidad = $n['nacionalidad'];

            // a) ¿Ya inscrito?
            $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE torneo_id = ? AND nacionalidad = ? AND cedula = ? AND estatus != 4 LIMIT 1");
            $stmt->execute([$torneoId, $nacionalidad, $cedula]);
            if ($stmt->fetch()) {
                $resultado[] = ['fila' => $filaNum, 'estado' => self::ESTADO_OMITIR, 'mensaje' => 'Ya inscrito en este torneo'];
                continue;
            }

            // b) ¿Existe en usuarios?
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cedula = ? LIMIT 1");
            $stmt->execute([$cedula]);
            if ($stmt->fetch()) {
                $resultado[] = ['fila' => $filaNum, 'estado' => self::ESTADO_INSCRIBIR, 'mensaje' => 'Usuario existe; se inscribirá'];
                continue;
            }

            // c) Todo nuevo: crear usuario e inscribir (requiere club)
            if ($n['club_nombre'] === '') {
                $resultado[] = ['fila' => $filaNum, 'estado' => self::ESTADO_ERROR, 'mensaje' => 'Club obligatorio para registro nuevo'];
                continue;
            }
            $resultado[] = ['fila' => $filaNum, 'estado' => self::ESTADO_CREAR_INSCRIBIR, 'mensaje' => 'Se creará usuario e inscribirá'];
        }
        return $resultado;
    }

    /**
     * Procesa la importación en lotes. Transacciones por lote.
     * @return array{procesados: int, nuevos: int, omitidos: int, errores: list<array{fila: int, cedula: string, motivo: string}>, csv_errores: string}
     */
    public static function procesarImportacion(PDO $pdo, int $torneoId, array $filas, int $inscritoPor): array
    {
        $procesados = 0;
        $nuevos = 0;
        $omitidos = 0;
        $errores = [];
        $clubRepo = new ClubRepository($pdo);

        $lotes = array_chunk($filas, self::TAMANO_LOTE, true);
        foreach ($lotes as $lote) {
            $pdo->beginTransaction();
            try {
                foreach ($lote as $idx => $fila) {
                    $filaNum = $idx + 1;
                    $norm = self::normalizarFila($fila, $filaNum);
                    if ($norm['error'] !== null) {
                        $errores[] = ['fila' => $filaNum, 'cedula' => $fila['cedula'] ?? '', 'motivo' => $norm['error']];
                        continue;
                    }
                    $n = $norm['normalized'];
                    $cedula = $n['cedula'];
                    $nacionalidad = $n['nacionalidad'];

                    // a) Ya inscrito → omitir
                    $stmt = $pdo->prepare("SELECT id FROM inscritos WHERE torneo_id = ? AND nacionalidad = ? AND cedula = ? AND estatus != 4 LIMIT 1");
                    $stmt->execute([$torneoId, $nacionalidad, $cedula]);
                    if ($stmt->fetch()) {
                        $omitidos++;
                        continue;
                    }

                    // b) Buscar en usuarios
                    $stmt = $pdo->prepare("SELECT id, club_id FROM usuarios WHERE cedula = ? LIMIT 1");
                    $stmt->execute([$cedula]);
                    $rowUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    $idUsuario = $rowUser ? (int) $rowUser['id'] : null;
                    $idClubInscrito = $rowUser && !empty($rowUser['club_id']) ? (int) $rowUser['club_id'] : null;

                    if ($idUsuario === null) {
                        // c) Crear club si no existe
                        $clubNombre = $n['club_nombre'];
                        if ($clubNombre === '') {
                            $errores[] = ['fila' => $filaNum, 'cedula' => $cedula, 'motivo' => 'Club obligatorio para registro nuevo'];
                            continue;
                        }
                        $club = $clubRepo->findByName($clubNombre);
                        if ($club === null) {
                            try {
                                $idClub = $clubRepo->create(['nombre' => $clubNombre, 'siglas' => null, 'ciudad' => null, 'estado' => null]);
                            } catch (Throwable $e) {
                                $errores[] = ['fila' => $filaNum, 'cedula' => $cedula, 'motivo' => 'No se pudo crear club: ' . $e->getMessage()];
                                continue;
                            }
                        } else {
                            $idClub = (int) $club['id'];
                        }

                        $email = $n['email'] !== '' ? $n['email'] : ('user' . $cedula . '@inscrito.local');
                        $username = $nacionalidad . $cedula;
                        $sufijo = '';
                        $idxU = 0;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
                            $stmt->execute([$username . $sufijo]);
                            if (!$stmt->fetch()) {
                                break;
                            }
                            $idxU++;
                            $sufijo = '_' . $idxU;
                        }
                        $username = $username . $sufijo;
                        $password = strlen($cedula) >= 6 ? $cedula : str_pad($cedula, 6, '0', STR_PAD_LEFT);
                        $fechnac = $n['fechnac'] !== '' ? $n['fechnac'] : null;

                        $create = Security::createUser([
                            'username' => $username,
                            'password' => $password,
                            'role' => 'usuario',
                            'nombre' => $n['nombre'],
                            'cedula' => $cedula,
                            'nacionalidad' => $nacionalidad,
                            'sexo' => $n['sexo'],
                            'fechnac' => $fechnac,
                            'email' => $email,
                            'celular' => $n['telefono'],
                            'club_id' => $idClub,
                            '_allow_club_for_usuario' => true,
                        ]);
                        if (!empty($create['errors'])) {
                            $errores[] = ['fila' => $filaNum, 'cedula' => $cedula, 'motivo' => implode(', ', $create['errors'])];
                            continue;
                        }
                        $idUsuario = (int) ($create['user_id'] ?? 0);
                        if ($idUsuario <= 0) {
                            $errores[] = ['fila' => $filaNum, 'cedula' => $cedula, 'motivo' => 'No se pudo crear el usuario'];
                            continue;
                        }
                        UserActivationHelper::activateUser($pdo, $idUsuario);
                        $nuevos++;
                        $idClubInscrito = $idClub;
                    }

                    try {
                        InscritosHelper::insertarInscrito($pdo, [
                            'id_usuario' => $idUsuario,
                            'torneo_id' => $torneoId,
                            'id_club' => $idClubInscrito,
                            'estatus' => 1,
                            'inscrito_por' => $inscritoPor,
                            'numero' => 0,
                            'nacionalidad' => $nacionalidad,
                            'cedula' => $cedula,
                        ]);
                        $procesados++;
                    } catch (Throwable $e) {
                        $errores[] = ['fila' => $filaNum, 'cedula' => $cedula, 'motivo' => $e->getMessage()];
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                foreach ($lote as $idx => $fila) {
                    $filaNum = $idx + 1;
                    $errores[] = ['fila' => $filaNum, 'cedula' => $fila['cedula'] ?? '', 'motivo' => 'Error en lote: ' . $e->getMessage()];
                }
            }
        }

        $csvErrores = '';
        if (!empty($errores)) {
            $csvErrores = "Fila;Cédula;Motivo\n";
            foreach ($errores as $err) {
                $csvErrores .= sprintf("%d;%s;%s\n", $err['fila'], $err['cedula'], str_replace(["\r", "\n", ";"], [' ', ' ', ','], $err['motivo']));
            }
            $csvErrores = mb_convert_encoding($csvErrores, 'UTF-8', 'UTF-8');
        }

        return [
            'procesados' => $procesados,
            'nuevos' => $nuevos,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'csv_errores' => $csvErrores,
        ];
    }
}
