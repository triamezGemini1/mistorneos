<?php
/**
 * Lista un usuario con role usuario/admin_club que esté inscrito en algún torneo.
 * Sirve para obtener cedula + torneo_id y probar el flujo "ya_inscrito" en la web.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();
$stmt = $pdo->query("
    SELECT u.id, u.cedula, u.nombre, u.role, i.torneo_id, t.nombre as torneo_nombre
    FROM inscritos i
    INNER JOIN usuarios u ON u.id = i.id_usuario
    LEFT JOIN tournaments t ON t.id = i.torneo_id
    WHERE u.role IN ('usuario','admin_club')
    ORDER BY i.torneo_id DESC
    LIMIT 10
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Usuarios inscritos (role usuario/admin_club) para probar 'ya_inscrito':\n";
echo str_repeat('-', 60) . "\n";
if (empty($rows)) {
    echo "No hay ninguno. Inscribe a un jugador con role 'usuario' en un torneo y vuelve a ejecutar.\n";
    exit(0);
}
foreach ($rows as $r) {
    echo "  cedula={$r['cedula']} torneo_id={$r['torneo_id']} ({$r['torneo_nombre']}) — {$r['nombre']}\n";
}
echo "\nEjemplo: php scripts/validar_cedula_inscrita.php {$rows[0]['torneo_id']} V {$rows[0]['cedula']}\n";
