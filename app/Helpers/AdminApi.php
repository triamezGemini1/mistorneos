<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>|null
 */
function mn_admin_session(): ?array
{
    $a = $_SESSION['admin_user'] ?? null;

    return is_array($a) && !empty($a['id']) ? $a : null;
}

/**
 * @return array<string, mixed>
 */
function mn_admin_require_json(): array
{
    $a = mn_admin_session();
    if ($a === null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Sesión de administrador requerida.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $a;
}

/**
 * ID de organización para filtrar consultas de torneos/clubes (null = admin_general, sin filtro estricto).
 */
function mn_admin_organizacion_scope(): ?int
{
    $a = mn_admin_session();
    if ($a === null) {
        return null;
    }
    if ((string) ($a['role'] ?? '') === 'admin_general') {
        return null;
    }
    $id = isset($a['organizacion_id']) ? (int) $a['organizacion_id'] : 0;

    return $id > 0 ? $id : null;
}

/**
 * Ámbito SQL para leer torneos: null = admin_general (sin filtro); int = organización; false = sesión inválida.
 *
 * @return int|null|false
 */
function mn_admin_torneo_query_scope()
{
    $a = mn_admin_session();
    if ($a === null) {
        return false;
    }
    if ((string) ($a['role'] ?? '') === 'admin_general') {
        return null;
    }
    $id = isset($a['organizacion_id']) ? (int) $a['organizacion_id'] : 0;

    return $id > 0 ? $id : false;
}
