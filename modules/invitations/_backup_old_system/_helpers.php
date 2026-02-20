<?php
function inv_token() { return bin2hex(random_bytes(24)); }
// La función app_base_url() ya está definida en bootstrap.php
function inv_url($token) { 
    // Si hay token, usar el sistema público
    if (!empty($token)) {
        return app_base_url() . '/modules/invitations/open.php?token=' . urlencode($token); 
    }
    // Si no hay token, usar el sistema público directo
    return app_base_url() . '/modules/invitations/public_access.php';
}

function inv_public_url($torneo_id, $club_id, $token = '') {
    $url = app_base_url() . '/modules/invitations/public_access.php?torneo=' . urlencode($torneo_id) . '&club=' . urlencode($club_id);
    if (!empty($token)) {
        $url .= '&token=' . urlencode($token);
    }
    return $url;
}
