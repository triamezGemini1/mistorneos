<?php

declare(strict_types=1);

namespace Tests\Unit\Lib;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class OperadorMesaAmbitoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/lib/OperadorMesaAmbitoService.php';
    }

    public function test_usuario_no_operador_no_consulta_bd(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('query');

        $this->assertNull(\OperadorMesaAmbitoService::mesasPermitidas($pdo, 1, 1, 5, 'admin_torneo'));
    }

    public function test_operador_sin_user_id_no_consulta_bd(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('query');

        $this->assertNull(\OperadorMesaAmbitoService::mesasPermitidas($pdo, 1, 1, 0, 'operador'));
    }

    public function test_operador_sin_tabla_devuelve_null(): void
    {
        $stmtTables = $this->createMock(PDOStatement::class);
        $stmtTables->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->with("SHOW TABLES LIKE 'operador_mesa_asignacion'")->willReturn($stmtTables);
        $pdo->expects($this->never())->method('prepare');

        $this->assertNull(\OperadorMesaAmbitoService::mesasPermitidas($pdo, 10, 2, 99, 'operador'));
    }

    public function test_operador_con_asignaciones_devuelve_enteros(): void
    {
        $stmtTables = $this->createMock(PDOStatement::class);
        $stmtTables->method('rowCount')->willReturn(1);

        $stmtMesas = $this->createMock(PDOStatement::class);
        $stmtMesas->method('execute')->willReturn(true);
        $stmtMesas->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([
            ['mesa_numero' => '1'],
            ['mesa_numero' => '3'],
        ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmtTables);
        $pdo->method('prepare')->willReturn($stmtMesas);

        $mesas = \OperadorMesaAmbitoService::mesasPermitidas($pdo, 10, 2, 7, 'operador');
        $this->assertSame([1, 3], $mesas);
    }
}
