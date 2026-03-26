<?php

declare(strict_types=1);

/**
 * Compatibilidad mínima con UrlHelper del monolito (landing público).
 */
final class UrlHelper
{
    public static function resultadosUrl(int $torneoId, string $nombre = ''): string
    {
        unset($nombre);
        $p = $GLOBALS['publicPrefix'] ?? '';
        if (!is_string($p)) {
            $p = '';
        }

        return $p . 'resultado_torneo.php?torneo_id=' . $torneoId;
    }
}
