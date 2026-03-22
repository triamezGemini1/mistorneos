<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_validate(?string $token): bool
{
    $stored = $_SESSION['_csrf_token'] ?? '';

    return is_string($token) && $stored !== '' && hash_equals($stored, $token);
}
