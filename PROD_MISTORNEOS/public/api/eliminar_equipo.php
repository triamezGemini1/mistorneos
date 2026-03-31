<?php
/**
 * API: Retiro completo de equipo del torneo
 * Elimina la fila en equipos y los registros de inscritos de los integrantes,
 * más partiresul, mesas_asignacion e historial de parejas asociados a esos jugadores en el torneo.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Auth::requireRoleJson(['admin_general', 'admin_torneo', 'admin_club']);

    // No usar CSRF::validate() aquí: hace die() con texto plano y rompe response.json() en el cliente.
    $posted = (string) ($_POST['csrf_token'] ?? '');
    $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $token = $posted !== '' ? $posted : $header;
    $sess = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sess === '' || !hash_equals($sess, $token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Token de seguridad inválido o expirado. Actualice la página e intente de nuevo.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $equipo_id = (int) ($_POST['equipo_id'] ?? 0);

    if ($equipo_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de equipo inválido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = DB::pdo();

    $stmt = $pdo->prepare('SELECT * FROM equipos WHERE id = ?');
    $stmt->execute([$equipo_id]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $torneo_id = (int) ($equipo['id_torneo'] ?? 0);
    if ($torneo_id <= 0 || !Auth::canAccessTournament($torneo_id)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para modificar este torneo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT i.id_usuario FROM inscritos i
         INNER JOIN equipos e ON e.id_torneo = i.torneo_id AND e.codigo_equipo = i.codigo_equipo
         WHERE e.id = ?'
    );
    $stmt->execute([$equipo_id]);
    $ids_usuario = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uid = (int) ($row['id_usuario'] ?? 0);
        if ($uid > 0) {
            $ids_usuario[] = $uid;
        }
    }
    $ids_usuario = array_values(array_unique($ids_usuario));

    $pdo->beginTransaction();
    try {
        if ($ids_usuario !== []) {
            $placeholders = implode(',', array_fill(0, count($ids_usuario), '?'));
            $paramsTorneoUsuarios = array_merge([$torneo_id], $ids_usuario);

            $pdo->prepare(
                "DELETE FROM partiresul WHERE id_torneo = ? AND id_usuario IN ($placeholders)"
            )->execute($paramsTorneoUsuarios);

            try {
                $pdo->prepare(
                    "DELETE FROM mesas_asignacion WHERE tournament_id = ? AND id_usuario IN ($placeholders)"
                )->execute($paramsTorneoUsuarios);
            } catch (Throwable $e) {
                // Tabla opcional
            }

            try {
                $pdo->prepare(
                    "DELETE FROM historial_parejas WHERE torneo_id = ? AND (jugador_1_id IN ($placeholders) OR jugador_2_id IN ($placeholders))"
                )->execute(array_merge([$torneo_id], $ids_usuario, $ids_usuario));
            } catch (Throwable $e) {
                // Tabla opcional
            }
        }

        $pdo->prepare(
            'DELETE i FROM inscritos i
             INNER JOIN equipos e ON e.id_torneo = i.torneo_id AND e.codigo_equipo = i.codigo_equipo
             WHERE e.id = ?'
        )->execute([$equipo_id]);

        $pdo->prepare('DELETE FROM equipos WHERE id = ?')->execute([$equipo_id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Equipo retirado: se eliminaron el equipo y los inscritos del torneo.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
