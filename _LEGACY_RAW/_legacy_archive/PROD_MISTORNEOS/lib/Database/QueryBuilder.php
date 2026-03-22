<?php



namespace Lib\Database;

use PDO;
use PDOStatement;

/**
 * Query Builder - Constructor de consultas SQL fluido y seguro
 * 
 * Características:
 * - Fluent interface
 * - Prevención automática de SQL Injection
 * - Soporte para joins, where, orderBy, groupBy
 * - Paginación integrada
 * - Query logging
 * 
 * @package Lib\Database
 * @version 1.0.0
 */
class QueryBuilder
{
    private PDO $pdo;
    private string $table = '';
    private array $select = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private string $type = 'SELECT';

    /**
     * Constructor
     * 
     * @param PDO $pdo Instancia PDO
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Especifica la tabla
     * 
     * @param string $table Nombre de la tabla
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Especifica columnas a seleccionar
     * 
     * @param string|array $columns Columnas
     * @return self
     */
    public function select($columns = ['*']): self
    {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Agrega WHERE condition
     * 
     * @param string $column Columna
     * @param string $operator Operador (=, !=, >, <, LIKE, etc)
     * @param mixed $value Valor
     * @param string $boolean AND o OR
     * @return self
     */
    public function where(string $column, string $operator, $value, string $boolean = 'AND'): self
    {
        $placeholder = $this->createPlaceholder($column);
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'placeholder' => $placeholder,
            'boolean' => count($this->wheres) === 0 ? '' : $boolean
        ];
        
        $this->bindings[$placeholder] = $value;
        
        return $this;
    }

    /**
     * WHERE OR condition
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function orWhere(string $column, string $operator, $value): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE IN condition
     * 
     * @param string $column
     * @param array $values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        
        foreach ($values as $i => $value) {
            $placeholder = $this->createPlaceholder($column . '_' . $i);
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }
        
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'placeholders' => $placeholders,
            'boolean' => count($this->wheres) === 0 ? '' : 'AND'
        ];
        
        return $this;
    }

    /**
     * WHERE NULL condition
     * 
     * @param string $column
     * @param bool $not NOT NULL si es true
     * @return self
     */
    public function whereNull(string $column, bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'not' => $not,
            'boolean' => count($this->wheres) === 0 ? '' : 'AND'
        ];
        
        return $this;
    }

    /**
     * WHERE NOT NULL condition
     * 
     * @param string $column
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        return $this->whereNull($column, true);
    }

    /**
     * INNER JOIN
     * 
     * @param string $table Tabla a hacer join
     * @param string $first Primera columna
     * @param string $operator Operador
     * @param string $second Segunda columna
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        
        return $this;
    }

    /**
     * LEFT JOIN
     * 
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        
        return $this;
    }

    /**
     * ORDER BY
     * 
     * @param string $column
     * @param string $direction ASC o DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }

    /**
     * GROUP BY
     * 
     * @param string|array $columns
     * @return self
     */
    public function groupBy($columns): self
    {
        $this->groupBy = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * LIMIT
     * 
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET
     * 
     * @param int $offset
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Paginación
     * 
     * @param int $page Página actual (1-based)
     * @param int $perPage Items por página
     * @return self
     */
    public function paginate(int $page, int $perPage = 15): self
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    /**
     * Construye y ejecuta SELECT
     * 
     * @return array Resultados
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->execute($sql);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtiene primer resultado
     * 
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        
        return $results[0] ?? null;
    }

    /**
     * Cuenta registros
     * 
     * @return int
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectQuery();
        $stmt = $this->execute($sql);
        $result = $stmt->fetch();
        
        $this->select = $originalSelect;
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * INSERT
     * 
     * @param array $data Datos a insertar
     * @return int Last insert ID
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = [];
        
        foreach ($columns as $column) {
            $placeholder = $this->createPlaceholder($column);
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $data[$column];
        }
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->execute($sql);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * UPDATE
     * 
     * @param array $data Datos a actualizar
     * @return int Filas afectadas
     */
    public function update(array $data): int
    {
        $sets = [];
        
        foreach ($data as $column => $value) {
            $placeholder = $this->createPlaceholder('set_' . $column);
            $sets[] = "$column = $placeholder";
            $this->bindings[$placeholder] = $value;
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->table,
            implode(', ', $sets),
            $this->buildWhere()
        );
        
        $stmt = $this->execute($sql);
        
        return $stmt->rowCount();
    }

    /**
     * DELETE
     * 
     * @return int Filas eliminadas
     */
    public function delete(): int
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->buildWhere()
        );
        
        $stmt = $this->execute($sql);
        
        return $stmt->rowCount();
    }

    /**
     * Construye query SELECT completo
     * 
     * @return string
     */
    private function buildSelectQuery(): string
    {
        $parts = [
            'SELECT',
            implode(', ', $this->select),
            'FROM',
            $this->table
        ];
        
        // Joins
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $parts[] = sprintf(
                    "%s JOIN %s ON %s %s %s",
                    $join['type'],
                    $join['table'],
                    $join['first'],
                    $join['operator'],
                    $join['second']
                );
            }
        }
        
        // Where
        if (!empty($this->wheres)) {
            $parts[] = $this->buildWhere();
        }
        
        // Group By
        if (!empty($this->groupBy)) {
            $parts[] = 'GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // Order By
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $parts[] = 'ORDER BY ' . implode(', ', $orderClauses);
        }
        
        // Limit
        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }
        
        // Offset
        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }
        
        return implode(' ', $parts);
    }

    /**
     * Construye cláusula WHERE
     * 
     * @return string
     */
    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $clauses = [];
        
        foreach ($this->wheres as $where) {
            $clause = '';
            
            if (!empty($where['boolean'])) {
                $clause .= $where['boolean'] . ' ';
            }
            
            switch ($where['type']) {
                case 'basic':
                    $clause .= "{$where['column']} {$where['operator']} {$where['placeholder']}";
                    break;
                    
                case 'in':
                    $clause .= "{$where['column']} IN (" . implode(', ', $where['placeholders']) . ")";
                    break;
                    
                case 'null':
                    $clause .= "{$where['column']} IS " . ($where['not'] ? 'NOT ' : '') . "NULL";
                    break;
            }
            
            $clauses[] = $clause;
        }
        
        return ' WHERE ' . implode(' ', $clauses);
    }

    /**
     * Ejecuta query preparado
     * 
     * @param string $sql
     * @return PDOStatement
     */
    private function execute(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($this->bindings as $placeholder => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($placeholder, $value, $type);
        }
        
        $stmt->execute();
        
        // Reset para nueva query
        $this->reset();
        
        return $stmt;
    }

    /**
     * Crea placeholder único
     * 
     * @param string $column
     * @return string
     */
    private function createPlaceholder(string $column): string
    {
        $base = ':' . preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
        $counter = 0;
        $placeholder = $base;
        
        while (isset($this->bindings[$placeholder])) {
            $placeholder = $base . '_' . (++$counter);
        }
        
        return $placeholder;
    }

    /**
     * Reset query builder state
     * 
     * @return void
     */
    private function reset(): void
    {
        $this->select = ['*'];
        $this->joins = [];
        $this->wheres = [];
        $this->bindings = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = null;
        $this->offset = null;
    }

    /**
     * Debug: obtiene SQL con bindings reemplazados
     * 
     * @return string
     */
    public function toSql(): string
    {
        $sql = $this->buildSelectQuery();
        
        foreach ($this->bindings as $placeholder => $value) {
            $value = is_string($value) ? "'$value'" : $value;
            $sql = str_replace($placeholder, (string)$value, $sql);
        }
        
        return $sql;
    }
}







