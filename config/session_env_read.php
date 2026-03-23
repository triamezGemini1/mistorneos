<?php
/**
 * Lee variables mínimas de sesión desde .env antes de bootstrap (usado por session_start_early).
 * @return array{gc:int, cookie:int, name:string}
 */
function session_read_lifetime_from_env(): array {
    $defaults = ['gc' => 28800, 'cookie' => 28800, 'name' => 'mistorneos_session']; // 8 h por defecto
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_readable($envFile)) {
        return $defaults;
    }
    $gc = 0;
    $cookie = 0;
    $sessionName = '';
    $appEnv = '';
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^APP_ENV\s*=\s*(\w+)/i', $line, $m)) {
            $appEnv = strtolower(trim((string) $m[1]));
        }
        if (preg_match('/^SESSION_NAME\s*=\s*([A-Za-z0-9_\-]+)/', $line, $m)) {
            $sessionName = trim((string) $m[1]);
        }
        if (preg_match('/^SESSION_GC_MAXLIFETIME\s*=\s*(\d+)/', $line, $m)) {
            $gc = max(300, (int) $m[1]);
        }
        if (preg_match('/^SESSION_LIFETIME\s*=\s*(\d+)/', $line, $m)) {
            // Minutos (estilo común). Solo aplica si no hay SESSION_GC_MAXLIFETIME.
            $min = (int) $m[1];
            if ($min > 0 && $min <= 10080) {
                $gc = max($gc, $min * 60);
            }
        }
        if (preg_match('/^SESSION_COOKIE_LIFETIME\s*=\s*(\d+)/', $line, $m)) {
            $cookie = max(0, (int) $m[1]);
        }
    }
    if ($gc <= 0) {
        $gc = $defaults['gc'];
    }
    if ($cookie <= 0) {
        $cookie = $gc;
    }
    if ($sessionName === '') {
        // Mantener compatibilidad con defaults por entorno de config/development|production.
        if ($appEnv === 'development') {
            $sessionName = 'mistorneos_session_dev';
        } elseif ($appEnv === 'production') {
            $sessionName = 'mistorneos_session_prod';
        } else {
            $sessionName = $defaults['name'];
        }
    }
    return ['gc' => $gc, 'cookie' => $cookie, 'name' => $sessionName];
}
