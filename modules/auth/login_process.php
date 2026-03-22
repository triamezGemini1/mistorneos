<?php

declare(strict_types=1);

/**
 * Procesamiento de login administrador.
 * Capa de datos pendiente: aquí solo validación, CSRF y saneamiento.
 */

$root = dirname(__DIR__, 2);
require $root . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php', true, 303);
    exit;
}

$token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null;
if (!csrf_validate($token)) {
    header('Location: index.php?auth=csrf', true, 303);
    exit;
}

$usuario = isset($_POST['usuario']) ? trim((string) $_POST['usuario']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

$errors = [];
if ($usuario === '' || strlen($usuario) > 128) {
    $errors[] = 'usuario';
}
if (strlen($password) < 8) {
    $errors[] = 'password';
}

if ($errors !== []) {
    $q = 'auth=invalid&fields=' . rawurlencode(implode(',', $errors));
    header('Location: index.php?' . $q, true, 303);
    exit;
}

// TODO: comprobar credenciales contra almacén (consultas preparadas + password_verify).
// No registrar contraseñas ni datos sensibles en logs.

header('Location: index.php?auth=pending', true, 303);
exit;
