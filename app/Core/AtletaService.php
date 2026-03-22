<?php

declare(strict_types=1);

require_once __DIR__ . '/PersonaAuxConfig.php';

/**
 * - Búsqueda en tabla maestra `usuarios` (buscar).
 * - Consulta de identidad en BD auxiliar de personas / padrón (consultarPadron).
 */
final class AtletaService
{
    private PDO $pdo;

    private ?PDO $pdoPadron;

    private string $table;

    public function __construct(PDO $pdoOperativa, ?PDO $pdoPadron = null)
    {
        $this->pdo = $pdoOperativa;
        $this->pdoPadron = $pdoPadron;
        $t = strtolower(trim((string) (getenv('DB_AUTH_TABLE') ?: 'usuarios')));
        $this->table = in_array($t, ['usuarios', 'users'], true) ? $t : 'usuarios';
    }

    public function tablaMaestra(): string
    {
        return $this->table;
    }

    /**
     * Busca en el padrón (tabla auxiliar, millones de filas). No toca la maestra operativa.
     *
     * @return array{
     *   cedula:string,
     *   nacionalidad:string,
     *   nombre_completo:string,
     *   nombres:string,
     *   apellidos:string,
     *   fecha_nacimiento:?string,
     *   sexo:string
     * }|null
     */
    public function consultarPadron(string $cedula): ?array
    {
        if ($this->pdoPadron === null) {
            return null;
        }

        $raw = trim($cedula);
        if ($raw === '') {
            return null;
        }

        $digits = self::normalizarDocumentoNumerico($raw);
        if (strlen($digits) < 4 || strlen($digits) > 20) {
            return null;
        }

        $nacEntrada = self::inferirNacionalidadDesdeEntrada($raw);
        $t = PersonaAuxConfig::quotedTable();

        if ($nacEntrada !== null) {
            $row = $this->consultarPadronFilas($t, $digits, $raw, $nacEntrada);
            if ($row !== null) {
                return $this->mapearFilaPadron($row, $digits, $nacEntrada);
            }
        }

        $row = $this->consultarPadronFilas($t, $digits, $raw, null);
        if ($row === null) {
            return null;
        }

        $nacFila = strtoupper(trim((string) ($row['Nac'] ?? 'V')));
        if (!in_array($nacFila, ['V', 'E', 'J', 'P'], true)) {
            $nacFila = 'V';
        }

        return $this->mapearFilaPadron($row, $digits, $nacFila);
    }

    /**
     * @return list<array{id:int|string,nombre:string,cedula:string,nacionalidad:string,username:string}>
     */
    public function buscar(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        if (mb_strlen($q) > 120) {
            $q = mb_substr($q, 0, 120);
        }

        $digits = self::normalizarDocumentoNumerico($q);
        $isDoc = strlen($digits) >= 3;

        if ($isDoc) {
            return $this->buscarPorCedula($q, $digits);
        }

        if (mb_strlen($q) < 2) {
            return [];
        }

        return $this->buscarPorNombrePrefijo($q);
    }

    public static function normalizarDocumentoNumerico(string $raw): string
    {
        $s = preg_replace('/^[VEJP]/i', '', trim($raw)) ?? '';
        $s = preg_replace('/\D/', '', $s) ?? '';

        return $s;
    }

    private static function inferirNacionalidadDesdeEntrada(string $raw): ?string
    {
        if (preg_match('/^([VEJP])/iu', trim($raw), $m) === 1) {
            $c = strtoupper($m[1]);

            return in_array($c, ['V', 'E', 'J', 'P'], true) ? $c : null;
        }

        return null;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function consultarPadronFilas(string $quotedTable, string $digits, string $raw, ?string $nac): ?array
    {
        $candidates = array_values(array_unique(array_filter([
            $digits,
            'V' . $digits,
            'E' . $digits,
            $raw,
        ], static fn ($v) => is_string($v) && $v !== '' && strlen($v) <= 32)));

        if ($nac !== null && in_array($nac, ['V', 'E', 'J', 'P'], true)) {
            foreach ($candidates as $cand) {
                $sql = <<<SQL
                    SELECT IDUsuario, Nac, Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
                    FROM {$quotedTable}
                    WHERE IDUsuario = :id AND Nac = :nac
                    LIMIT 1
                    SQL;
                try {
                    $st = $this->pdoPadron->prepare($sql);
                    $st->execute(['id' => $cand, 'nac' => $nac]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        return $row;
                    }
                } catch (PDOException $e) {
                    error_log('consultarPadron (con Nac): ' . $e->getMessage());
                }
            }
        }

        foreach ($candidates as $cand) {
            $sql = <<<SQL
                SELECT IDUsuario, Nac, Nombre1, Nombre2, Apellido1, Apellido2, FNac, Sexo
                FROM {$quotedTable}
                WHERE IDUsuario = :id
                LIMIT 1
                SQL;
            try {
                $st = $this->pdoPadron->prepare($sql);
                $st->execute(['id' => $cand]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (PDOException $e) {
                error_log('consultarPadron: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *   cedula:string,
     *   nacionalidad:string,
     *   nombre_completo:string,
     *   nombres:string,
     *   apellidos:string,
     *   fecha_nacimiento:?string,
     *   sexo:string
     * }
     */
    private function mapearFilaPadron(array $row, string $digits, string $nacDefault): array
    {
        $n1 = trim((string) ($row['Nombre1'] ?? ''));
        $n2 = trim((string) ($row['Nombre2'] ?? ''));
        $a1 = trim((string) ($row['Apellido1'] ?? ''));
        $a2 = trim((string) ($row['Apellido2'] ?? ''));
        $nombres = trim(implode(' ', array_filter([$n1, $n2])));
        $apellidos = trim(implode(' ', array_filter([$a1, $a2])));
        $completo = trim(implode(' ', array_filter([$n1, $n2, $a1, $a2])));

        $nac = strtoupper(trim((string) ($row['Nac'] ?? $nacDefault)));
        if (!in_array($nac, ['V', 'E', 'J', 'P'], true)) {
            $nac = $nacDefault;
        }

        $fn = $row['FNac'] ?? null;
        $fecha = null;
        if ($fn !== null && $fn !== '') {
            $ts = strtotime((string) $fn);
            if ($ts !== false) {
                $fecha = date('Y-m-d', $ts);
            }
        }

        $sexoRaw = strtoupper(trim((string) ($row['Sexo'] ?? '')));
        $sexo = 'M';
        if (in_array($sexoRaw, ['F', '2', 'FEMENINO', 'FEMALE'], true)) {
            $sexo = 'F';
        } elseif (in_array($sexoRaw, ['O', 'Otro'], true)) {
            $sexo = 'O';
        } elseif (in_array($sexoRaw, ['M', '1', 'MASCULINO', 'MALE'], true)) {
            $sexo = 'M';
        }

        return [
            'cedula' => $digits,
            'nacionalidad' => $nac,
            'nombre_completo' => $completo,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'fecha_nacimiento' => $fecha,
            'sexo' => $sexo,
        ];
    }

    private function qTable(): string
    {
        return '`' . str_replace('`', '', $this->table) . '`';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscarPorCedula(string $exacto, string $digits): array
    {
        $t = $this->qTable();
        $sql = <<<SQL
            SELECT id, nombre, cedula, nacionalidad, username
            FROM {$t}
            WHERE cedula = :exacto
               OR cedula = :digits
               OR cedula LIKE :pref
               OR cedula LIKE :vpref
            ORDER BY id ASC
            LIMIT 10
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'exacto' => $exacto,
            'digits' => $digits,
            'pref' => $digits . '%',
            'vpref' => 'V' . $digits . '%',
        ]);

        return $this->normalizarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscarPorNombrePrefijo(string $q): array
    {
        $t = $this->qTable();
        $sql = <<<SQL
            SELECT id, nombre, cedula, nacionalidad, username
            FROM {$t}
            WHERE nombre LIKE :pref
            ORDER BY nombre ASC
            LIMIT 10
            SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['pref' => $q . '%']);

        return $this->normalizarFilas($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id:int|string,nombre:string,cedula:string,nacionalidad:string,username:string}>
     */
    private function normalizarFilas(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['id'] ?? 0,
                'nombre' => (string) ($row['nombre'] ?? ''),
                'cedula' => (string) ($row['cedula'] ?? ''),
                'nacionalidad' => (string) ($row['nacionalidad'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
            ];
        }

        return $out;
    }
}
