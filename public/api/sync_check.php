<?php
/**
 * Comprobación de ruta: devuelve JSON si este archivo se ejecuta.
 * Sube este archivo y fetch_jugadores.php a public/api/ en el servidor.
 * Si al abrir https://tudominio.com/api/sync_check.php ves {"ok":true,"msg":"api ok"},
 * entonces la URL de jugadores será https://tudominio.com/api/fetch_jugadores.php
 */
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'msg' => 'api ok', 'path' => __FILE__]);
