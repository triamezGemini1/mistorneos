<?php
/**
 * Depuración: muestra todos los registros de jugadores (tabla usuarios) de la base SQLite local.
 * Solo para desarrollo; en producción conviene restringir el acceso.
 */
declare(strict_types=1);

require_once __DIR__ . '/db_local.php';

$error = null;
$rows = [];
$tableName = 'usuarios'; // En SQLite los jugadores están en la tabla "usuarios"

try {
    $pdo = DB_Local::pdo();
    $stmt = $pdo->query("SELECT * FROM {$tableName} ORDER BY id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug — Base SQLite (jugadores)</title>
    <style>
        body { font-family: sans-serif; margin: 1rem; background: #f5f5f5; }
        h1 { color: #333; }
        .info { background: #e3f2fd; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        table { border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th, td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #1976d2; color: white; font-weight: 600; }
        tr:hover { background: #fafafa; }
        .alert { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .empty { color: #666; padding: 2rem; text-align: center; }
        .back { display: inline-block; margin-bottom: 1rem; color: #1976d2; text-decoration: none; }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <a href="registro_jugador.php" class="back">← Volver al registro de jugador</a>

    <h1>Debug — Base de datos SQLite</h1>
    <p class="info">Tabla <strong>jugadores</strong> (en la BD: <code>usuarios</code>). Archivo: <code>data/mistorneos_local.db</code></p>

    <?php if ($error): ?>
        <div class="alert">Error al conectar o leer: <?= htmlspecialchars($error) ?></div>
    <?php elseif (count($rows) === 0): ?>
        <p class="empty">No hay registros en la tabla. Registra un jugador desde <a href="registro_jugador.php">registro_jugador.php</a> o sincroniza desde la web.</p>
    <?php else: ?>
        <p><strong><?= count($rows) ?></strong> registro(s) encontrado(s).</p>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td><?= htmlspecialchars((string)$val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>
