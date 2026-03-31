<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/csrf.php';

Auth::requireRole(['admin_general']);

$action = $_GET['action'] ?? 'index';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($csrf === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrf)) {
        header('Location: index.php?page=entidades&error=' . urlencode('Token CSRF inválido'));
        exit;
    }

    $postAction = (string)($_POST['crud_action'] ?? '');
    $pdo = DB::pdo();

    try {
        if ($postAction === 'create') {
            $codigo = (int)($_POST['codigo'] ?? 0);
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $estado = !empty($_POST['estado']) ? 1 : 0;
            if ($codigo <= 0 || $nombre === '') {
                throw new RuntimeException('Código y nombre son obligatorios');
            }
            $stmt = $pdo->prepare("INSERT INTO entidad (id, nombre, estado) VALUES (?, ?, ?)");
            $stmt->execute([$codigo, $nombre, $estado]);
            header('Location: index.php?page=entidades&success=' . urlencode('Entidad creada'));
            exit;
        }

        if ($postAction === 'update') {
            $codigo = (int)($_POST['codigo'] ?? 0);
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $estado = !empty($_POST['estado']) ? 1 : 0;
            if ($codigo <= 0 || $nombre === '') {
                throw new RuntimeException('Código y nombre son obligatorios');
            }
            $stmt = $pdo->prepare("UPDATE entidad SET nombre = ?, estado = ? WHERE id = ?");
            $stmt->execute([$nombre, $estado, $codigo]);
            header('Location: index.php?page=entidades&success=' . urlencode('Entidad actualizada'));
            exit;
        }

        if ($postAction === 'delete') {
            $codigo = (int)($_POST['codigo'] ?? 0);
            if ($codigo <= 0) {
                throw new RuntimeException('Código inválido');
            }
            $stmtOrg = $pdo->prepare("SELECT COUNT(*) FROM organizaciones WHERE entidad = ?");
            $stmtOrg->execute([$codigo]);
            $usada = (int)$stmtOrg->fetchColumn() > 0;
            if ($usada) {
                throw new RuntimeException('No se puede eliminar: la entidad tiene organizaciones asociadas');
            }
            $stmt = $pdo->prepare("DELETE FROM entidad WHERE id = ?");
            $stmt->execute([$codigo]);
            header('Location: index.php?page=entidades&success=' . urlencode('Entidad eliminada'));
            exit;
        }
    } catch (Throwable $e) {
        header('Location: index.php?page=entidades&error=' . urlencode($e->getMessage()));
        exit;
    }
}

if ($action === 'detail') {
    include_once __DIR__ . '/entidades/detail.php';
    return;
}

include_once __DIR__ . '/admin_general/entidades/actions/index.php';
