<?php
/**
 * Transferencia total: directorio_clubes → clubes.
 * Crea en clubes un registro por cada entrada del directorio (estatus = 9, id_directorio_club enlazado).
 * Opcionalmente actualiza invitaciones que tenían solo id_directorio_club para asignarles club_id.
 *
 * Requisito previo: ejecutar sql/clubes_id_directorio_estatus9.sql
 * Uso: php scripts/transferir_directorio_a_clubes.php [--actualizar-invitaciones]
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

const CLUB_ESTATUS_DIRECTORIO = 9;

$actualizar_invitaciones = in_array('--actualizar-invitaciones', $argv ?? [], true);

echo "=== Transferencia directorio_clubes → clubes (estatus " . CLUB_ESTATUS_DIRECTORIO . ") ===\n\n";

try {
    $pdo = DB::pdo();

    $cols_clubes = $pdo->query("SHOW COLUMNS FROM clubes")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id_directorio_club', $cols_clubes, true)) {
        echo "ERROR: La tabla clubes no tiene la columna id_directorio_club.\n";
        echo "Ejecute primero: sql/clubes_id_directorio_estatus9.sql\n";
        exit(1);
    }

    $stmt = $pdo->query("SELECT id, nombre, direccion, delegado, telefono, email, logo FROM directorio_clubes ORDER BY id ASC");
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($filas);

    if ($total === 0) {
        echo "No hay registros en directorio_clubes. Nada que transferir.\n";
        exit(0);
    }

    echo "Registros en directorio_clubes: {$total}\n";

    $creados = 0;
    $ya_existentes = 0;
    $errores = [];

    $has_entidad = in_array('entidad', $cols_clubes, true);
    $check = $pdo->prepare("SELECT id FROM clubes WHERE id_directorio_club = ? LIMIT 1");
    $insert_cols = ['nombre', 'direccion', 'delegado', 'telefono', 'email', 'logo', 'estatus', 'id_directorio_club'];
    if ($has_entidad) {
        $insert_cols[] = 'entidad';
    }
    $placeholders = array_map(function ($c) { return ':' . $c; }, $insert_cols);
    $insert_sql = "INSERT INTO clubes (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $insert_stmt = $pdo->prepare($insert_sql);

    foreach ($filas as $dir) {
        $id_dc = (int) $dir['id'];
        $nombre = trim($dir['nombre'] ?? '');
        if ($nombre === '') {
            $errores[] = "Directorio id={$id_dc}: nombre vacío, omitido.";
            continue;
        }

        $check->execute([$id_dc]);
        if ($check->fetch()) {
            $ya_existentes++;
            continue;
        }

        $params = [
            ':nombre' => $nombre,
            ':direccion' => trim($dir['direccion'] ?? '') ?: null,
            ':delegado' => trim($dir['delegado'] ?? '') ?: null,
            ':telefono' => trim($dir['telefono'] ?? '') ?: null,
            ':email' => trim($dir['email'] ?? '') ?: null,
            ':logo' => !empty($dir['logo']) ? $dir['logo'] : null,
            ':estatus' => CLUB_ESTATUS_DIRECTORIO,
            ':id_directorio_club' => $id_dc,
        ];
        if ($has_entidad) {
            $params[':entidad'] = 0;
        }

        try {
            $insert_stmt->execute($params);
            $creados++;
            echo "  Creado club para directorio id={$id_dc}: " . substr($nombre, 0, 50) . "\n";
        } catch (PDOException $e) {
            $errores[] = "Directorio id={$id_dc}: " . $e->getMessage();
        }
    }

    echo "\n--- Resumen ---\n";
    echo "Creados en clubes: {$creados}\n";
    echo "Ya existían (id_directorio_club ya vinculado): {$ya_existentes}\n";
    if (!empty($errores)) {
        echo "Errores:\n";
        foreach ($errores as $err) {
            echo "  - {$err}\n";
        }
    }

    if ($actualizar_invitaciones) {
        echo "\n--- Actualizando invitaciones (club_id desde id_directorio_club) ---\n";
        $tb_inv = defined('TABLE_INVITATIONS') ? TABLE_INVITATIONS : 'invitaciones';
        $cols_inv = $pdo->query("SHOW COLUMNS FROM {$tb_inv}")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('id_directorio_club', $cols_inv, true) || !in_array('club_id', $cols_inv, true)) {
            echo "  La tabla invitaciones no tiene club_id o id_directorio_club. Omitido.\n";
        } else {
            $upd = $pdo->prepare("
                UPDATE {$tb_inv} i
                INNER JOIN clubes c ON c.id_directorio_club = i.id_directorio_club
                SET i.club_id = c.id
                WHERE i.id_directorio_club IS NOT NULL AND (i.club_id IS NULL OR i.club_id = 0)
            ");
            $upd->execute();
            $actualizadas = $upd->rowCount();
            echo "  Invitaciones actualizadas con club_id: {$actualizadas}\n";
        }
    } else {
        echo "\nPara enlazar invitaciones existentes (id_directorio_club sin club_id): php " . basename(__FILE__) . " --actualizar-invitaciones\n";
    }

    echo "\nListo.\n";
    exit(empty($errores) ? 0 : 1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
