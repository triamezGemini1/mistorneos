<?php

declare(strict_types=1);

/**
 * Configuración de la BD auxiliar de personas (validación de identidad al registrar usuarios).
 * No usar en el buscador del landing ni en el Top 5: eso vive en la tabla maestra `usuarios`.
 */
final class PersonaAuxConfig
{
    public static function tableName(): string
    {
        $t = getenv('DB_PERSONA_TABLE');
        if (!is_string($t) || $t === '') {
            return 'dbo_persona';
        }
        $t = trim($t);
        if (!preg_match('/^[A-Za-z0-9_.]+$/', $t)) {
            return 'dbo_persona';
        }

        return $t;
    }

    public static function quotedTable(): string
    {
        $parts = array_filter(explode('.', self::tableName()));
        $safe = [];
        foreach ($parts as $p) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $p)) {
                return '`dbo_persona`';
            }
            $safe[] = '`' . $p . '`';
        }

        return $safe !== [] ? implode('.', $safe) : '`dbo_persona`';
    }
}
