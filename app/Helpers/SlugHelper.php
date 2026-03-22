<?php

declare(strict_types=1);

final class SlugHelper
{
    public static function slugify(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');
        $t = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'à', 'è', 'ì', 'ò', 'ù'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u'],
            $t
        );
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        $t = trim($t, '-');

        return $t !== '' ? $t : 'torneo';
    }
}
