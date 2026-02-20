<?php
/**
 * EquiposHelper - Helper para manejo de equipos de 4 jugadores
 * 
 * Proporciona funciones para:
 * - Crear y gestionar equipos
 * - Asignar jugadores a equipos
 * - Validaciones de negocio
 * - Generación de códigos de equipo
 * 
 * @package MisTorneos
 * @since 2025-01-08
 */

require_once __DIR__ . '/../config/db.php';

class EquiposHelper {
    
    const MAX_JUGADORES_POR_EQUIPO = 4;
    const MODALIDAD_EQUIPOS = 3;
    
    const ESTATUS_ACTIVO = 0;
    const ESTATUS_INACTIVO = 1;
    
    const JUGADOR_ACTIVO = 1;
    const JUGADOR_INACTIVO = 0;
    const JUGADOR_SUSPENDIDO = 2;
    
    /**
     * Crear un nuevo equipo
     * 
     * @param int $torneoId ID del torneo
     * @param int $clubId ID del club
     * @param string $nombreEquipo Nombre del equipo
     * @param int|null $creadoPor ID del usuario que crea el equipo
     * @return array ['success' => bool, 'id' => int|null, 'codigo' => string|null, 'message' => string]
     */
    public static function crearEquipo(int $torneoId, int $clubId, string $nombreEquipo, ?int $creadoPor = null): array {
        error_log("EquiposHelper::crearEquipo - INICIO - torneoId=$torneoId, clubId=$clubId, nombreEquipo=$nombreEquipo, creadoPor=" . ($creadoPor ?? 'NULL'));
        try {
            $pdo = DB::pdo();
            
            // Verificar que el torneo sea modalidad equipos
            error_log("EquiposHelper::crearEquipo - Verificando torneo");
            $stmt = $pdo->prepare("SELECT modalidad FROM tournaments WHERE id = ?");
            $stmt->execute([$torneoId]);
            $torneo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$torneo) {
                error_log("EquiposHelper::crearEquipo - ERROR: Torneo no encontrado");
                return ['success' => false, 'id' => null, 'codigo' => null, 'message' => 'Torneo no encontrado'];
            }
            
            error_log("EquiposHelper::crearEquipo - Torneo encontrado, modalidad=" . ($torneo['modalidad'] ?? 'NULL'));
            if ((int)$torneo['modalidad'] !== self::MODALIDAD_EQUIPOS) {
                error_log("EquiposHelper::crearEquipo - ERROR: Modalidad incorrecta (esperado 3, obtenido " . ($torneo['modalidad'] ?? 'NULL') . ")");
                return ['success' => false, 'id' => null, 'codigo' => null, 'message' => 'El torneo no es modalidad equipos'];
            }
            
            // Verificar que no exista un equipo con el mismo nombre
            error_log("EquiposHelper::crearEquipo - Verificando duplicado");
            $stmt = $pdo->prepare("
                SELECT id FROM equipos 
                WHERE id_torneo = ? AND id_club = ? AND UPPER(nombre_equipo) = UPPER(?)
            ");
            $stmt->execute([$torneoId, $clubId, trim($nombreEquipo)]);
            
            if ($stmt->fetch()) {
                error_log("EquiposHelper::crearEquipo - ERROR: Equipo duplicado");
                return ['success' => false, 'id' => null, 'codigo' => null, 'message' => 'Ya existe un equipo con ese nombre en este club'];
            }
            error_log("EquiposHelper::crearEquipo - No hay duplicado, continuando");
            
            // Crear equipo y forzar formato de código: club "000" + "-" + secuencial (1..N) por club en el torneo
            // Intentar procedimiento almacenado, si falla usar inserción directa
            error_log("EquiposHelper::crearEquipo - Intentando crear equipo");
            $equipoId = null;
            try {
                error_log("EquiposHelper::crearEquipo - Intentando SP sp_crear_equipo");
                $stmt = $pdo->prepare("CALL sp_crear_equipo(?, ?, ?, ?, @id_equipo, @codigo, @msg)");
                $stmt->execute([$torneoId, $clubId, strtoupper(trim($nombreEquipo)), $creadoPor]);
                $result = $pdo->query("SELECT @id_equipo as id, @codigo as codigo, @msg as mensaje")->fetch(PDO::FETCH_ASSOC);
                error_log("EquiposHelper::crearEquipo - SP resultado: " . json_encode($result, JSON_UNESCAPED_UNICODE));
                if ((int)$result['id'] > 0) {
                    $equipoId = (int)$result['id'];
                    error_log("EquiposHelper::crearEquipo - Equipo creado por SP, id=$equipoId");
                } else {
                    error_log("EquiposHelper::crearEquipo - SP falló: " . ($result['mensaje'] ?? 'Sin mensaje'));
                    return ['success' => false, 'id' => null, 'codigo' => null, 'message' => $result['mensaje']];
                }
            } catch (PDOException $e) {
                // Inserción directa si no existe el SP
                error_log("EquiposHelper::crearEquipo - SP no disponible, usando inserción directa: " . $e->getMessage());
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO equipos (id_torneo, id_club, nombre_equipo, creado_por)
                        VALUES (?, ?, UPPER(?), ?)
                    ");
                    $stmt->execute([$torneoId, $clubId, trim($nombreEquipo), $creadoPor]);
                    $equipoId = (int)$pdo->lastInsertId();
                    error_log("EquiposHelper::crearEquipo - Equipo insertado directamente, id=$equipoId");
                } catch (PDOException $e2) {
                    error_log("EquiposHelper::crearEquipo - ERROR en inserción directa: " . $e2->getMessage());
                    error_log("EquiposHelper::crearEquipo - SQL Error Info: " . json_encode($stmt->errorInfo()));
                    throw $e2;
                }
            }

            // Calcular consecutivo por club y actualizar código con el formato requerido
            if ($equipoId > 0) {
                error_log("EquiposHelper::crearEquipo - Generando código para equipo id=$equipoId");
                // Obtener consecutivo existente
                $stmt = $pdo->prepare("SELECT consecutivo_club FROM equipos WHERE id = ?");
                $stmt->execute([$equipoId]);
                $consecutivo = (int)($stmt->fetchColumn() ?? 0);
                error_log("EquiposHelper::crearEquipo - Consecutivo existente: $consecutivo");
                if ($consecutivo <= 0) {
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(consecutivo_club),0)+1 FROM equipos WHERE id_torneo = ? AND id_club = ?");
                    $stmt->execute([$torneoId, $clubId]);
                    $consecutivo = (int)$stmt->fetchColumn();
                    error_log("EquiposHelper::crearEquipo - Nuevo consecutivo calculado: $consecutivo");
                }
                $codigo = str_pad((string)$clubId, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string)$consecutivo, 3, '0', STR_PAD_LEFT);
                error_log("EquiposHelper::crearEquipo - Código generado: $codigo");
                
                try {
                    $stmt = $pdo->prepare("UPDATE equipos SET consecutivo_club = ?, codigo_equipo = ? WHERE id = ?");
                    $stmt->execute([$consecutivo, $codigo, $equipoId]);
                    error_log("EquiposHelper::crearEquipo - Código actualizado en BD, filas afectadas: " . $stmt->rowCount());

                    error_log("EquiposHelper::crearEquipo - ÉXITO - equipoId=$equipoId, codigo=$codigo");
                    return [
                        'success' => true,
                        'id' => $equipoId,
                        'codigo' => $codigo,
                        'message' => 'Equipo creado exitosamente'
                    ];
                } catch (PDOException $e3) {
                    error_log("EquiposHelper::crearEquipo - ERROR al actualizar código: " . $e3->getMessage());
                    error_log("EquiposHelper::crearEquipo - SQL Error Info: " . json_encode($stmt->errorInfo()));
                    throw $e3;
                }
            }

            error_log("EquiposHelper::crearEquipo - ERROR: No se pudo crear el equipo (equipoId=$equipoId)");
            return ['success' => false, 'id' => null, 'codigo' => null, 'message' => 'No se pudo crear el equipo'];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::crearEquipo ERROR: " . $e->getMessage());
            error_log("EquiposHelper::crearEquipo ERROR - Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'id' => null, 'codigo' => null, 'message' => 'Error al crear equipo: ' . $e->getMessage()];
        }
    }
    
    /**
     * Agregar jugador a un equipo
     * 
     * @param int $equipoId ID del equipo
     * @param string $cedula Cédula del jugador
     * @param string $nombre Nombre del jugador
     * @param int $posicion Posición en el equipo (1-4)
     * @param bool $esCapitan Si es el capitán del equipo
     * @param int|null $idInscrito ID de tabla inscritos (opcional)
     * @param int|null $idInscripcion ID de tabla inscripciones (opcional)
     * @return array ['success' => bool, 'id' => int|null, 'message' => string]
     */
    public static function agregarJugador(
        int $equipoId, 
        string $cedula, 
        string $nombre, 
        int $posicion = 1, 
        bool $esCapitan = false,
        ?int $idInscrito = null,
        ?int $idInscripcion = null
    ): array {
        try {
            $pdo = DB::pdo();
            
            // Limpiar cédula
            $cedula = preg_replace('/^[VEJP]/i', '', trim($cedula));
            
            // Obtener información del equipo
            $stmt = $pdo->prepare("SELECT id_torneo FROM equipos WHERE id = ?");
            $stmt->execute([$equipoId]);
            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$equipo) {
                return ['success' => false, 'id' => null, 'message' => 'Equipo no encontrado'];
            }
            
            $torneoId = (int)$equipo['id_torneo'];
            
            // Verificar cantidad de jugadores
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM equipo_jugadores WHERE id_equipo = ? AND estatus = 1");
            $stmt->execute([$equipoId]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ((int)$count['total'] >= self::MAX_JUGADORES_POR_EQUIPO) {
                return ['success' => false, 'id' => null, 'message' => 'El equipo ya tiene el máximo de jugadores permitidos'];
            }
            
            // Verificar que la posición esté libre
            $stmt = $pdo->prepare("
                SELECT id FROM equipo_jugadores 
                WHERE id_equipo = ? AND posicion_equipo = ? AND estatus = 1
            ");
            $stmt->execute([$equipoId, $posicion]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'id' => null, 'message' => "La posición $posicion ya está ocupada"];
            }
            
            // Verificar que el jugador no esté en otro equipo del mismo torneo
            $stmt = $pdo->prepare("
                SELECT e.nombre_equipo 
                FROM equipo_jugadores ej
                INNER JOIN equipos e ON ej.id_equipo = e.id
                WHERE ej.cedula = ? AND e.id_torneo = ? AND ej.estatus = 1
            ");
            $stmt->execute([$cedula, $torneoId]);
            $otroEquipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otroEquipo) {
                return [
                    'success' => false, 
                    'id' => null, 
                    'message' => "El jugador ya está inscrito en el equipo '{$otroEquipo['nombre_equipo']}'"
                ];
            }
            
            // Insertar jugador
            $stmt = $pdo->prepare("
                INSERT INTO equipo_jugadores 
                (id_equipo, id_inscrito, id_inscripcion, cedula, nombre, posicion_equipo, es_capitan, estatus)
                VALUES (?, ?, ?, ?, UPPER(?), ?, ?, 1)
            ");
            $stmt->execute([
                $equipoId,
                $idInscrito ?: null,
                $idInscripcion ?: null,
                $cedula,
                trim($nombre),
                $posicion,
                $esCapitan ? 1 : 0
            ]);
            
            return [
                'success' => true,
                'id' => (int)$pdo->lastInsertId(),
                'message' => 'Jugador agregado exitosamente'
            ];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::agregarJugador ERROR: " . $e->getMessage());
            return ['success' => false, 'id' => null, 'message' => 'Error al agregar jugador: ' . $e->getMessage()];
        }
    }
    
    /**
     * Crear equipo completo con 4 jugadores en una transacción
     * 
     * @param int $torneoId ID del torneo
     * @param int $clubId ID del club
     * @param string $nombreEquipo Nombre del equipo
     * @param array $jugadores Array de jugadores: [['cedula' => '', 'nombre' => '', 'es_capitan' => false], ...]
     * @param int|null $creadoPor ID del usuario creador
     * @return array ['success' => bool, 'id_equipo' => int|null, 'codigo' => string|null, 'jugadores_agregados' => int, 'message' => string, 'errores' => array]
     */
    public static function crearEquipoCompleto(
        int $torneoId, 
        int $clubId, 
        string $nombreEquipo, 
        array $jugadores, 
        ?int $creadoPor = null
    ): array {
        $pdo = DB::pdo();
        
        try {
            $pdo->beginTransaction();
            
            // Crear el equipo
            $resultEquipo = self::crearEquipo($torneoId, $clubId, $nombreEquipo, $creadoPor);
            
            if (!$resultEquipo['success']) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'id_equipo' => null,
                    'codigo' => null,
                    'jugadores_agregados' => 0,
                    'message' => $resultEquipo['message'],
                    'errores' => []
                ];
            }
            
            $equipoId = $resultEquipo['id'];
            $codigoEquipo = $resultEquipo['codigo'];
            $jugadoresAgregados = 0;
            $errores = [];
            
            // Agregar jugadores
            foreach ($jugadores as $index => $jugador) {
                $posicion = $index + 1;
                
                if (empty($jugador['cedula']) || empty($jugador['nombre'])) {
                    $errores[] = "Jugador $posicion: Datos incompletos";
                    continue;
                }
                
                $resultJugador = self::agregarJugador(
                    $equipoId,
                    $jugador['cedula'],
                    $jugador['nombre'],
                    $posicion,
                    $jugador['es_capitan'] ?? ($index === 0), // El primero es capitán por defecto
                    $jugador['id_inscrito'] ?? null,
                    $jugador['id_inscripcion'] ?? null
                );
                
                if ($resultJugador['success']) {
                    $jugadoresAgregados++;
                } else {
                    $errores[] = "Jugador $posicion ({$jugador['cedula']}): {$resultJugador['message']}";
                }
            }
            
            // Si no se agregó ningún jugador, hacer rollback
            if ($jugadoresAgregados === 0) {
                $pdo->rollBack();
                return [
                    'success' => false,
                    'id_equipo' => null,
                    'codigo' => null,
                    'jugadores_agregados' => 0,
                    'message' => 'No se pudo agregar ningún jugador al equipo',
                    'errores' => $errores
                ];
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'id_equipo' => $equipoId,
                'codigo' => $codigoEquipo,
                'jugadores_agregados' => $jugadoresAgregados,
                'message' => "Equipo creado con $jugadoresAgregados jugadores",
                'errores' => $errores
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("EquiposHelper::crearEquipoCompleto ERROR: " . $e->getMessage());
            return [
                'success' => false,
                'id_equipo' => null,
                'codigo' => null,
                'jugadores_agregados' => 0,
                'message' => 'Error al crear equipo: ' . $e->getMessage(),
                'errores' => []
            ];
        }
    }
    
    /**
     * Obtener equipos de un torneo
     * 
     * @param int $torneoId ID del torneo
     * @param int|null $clubId Filtrar por club (opcional)
     * @return array
     */
    public static function getEquiposTorneo(int $torneoId, ?int $clubId = null): array {
        try {
            $pdo = DB::pdo();
            
            $sql = "
                SELECT 
                    e.*,
                    c.nombre AS nombre_club,
                    (SELECT COUNT(*) FROM inscritos i WHERE i.torneo_id = e.id_torneo AND i.codigo_equipo = e.codigo_equipo AND i.estatus != 'retirado') AS total_jugadores
                FROM equipos e
                LEFT JOIN clubes c ON e.id_club = c.id
                WHERE e.id_torneo = ?
            ";
            
            $params = [$torneoId];
            
            if ($clubId !== null) {
                $sql .= " AND e.id_club = ?";
                $params[] = $clubId;
            }
            
            $sql .= " ORDER BY e.posicion ASC, e.puntos DESC, e.efectividad DESC, e.nombre_equipo ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::getEquiposTorneo ERROR: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener jugadores de un equipo
     * 
     * @param int $equipoId ID del equipo
     * @return array
     */
    public static function getJugadoresEquipo(int $equipoId): array {
        try {
            $pdo = DB::pdo();
            
            // Obtener código del equipo
            $stmt = $pdo->prepare("SELECT codigo_equipo, id_torneo FROM equipos WHERE id = ?");
            $stmt->execute([$equipoId]);
            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$equipo || empty($equipo['codigo_equipo'])) {
                return [];
            }
            
            // Obtener jugadores desde inscritos usando codigo_equipo
            $stmt = $pdo->prepare("
                SELECT 
                    i.id as id_inscrito,
                    i.id_usuario,
                    i.torneo_id,
                    i.id_club,
                    i.codigo_equipo,
                    i.estatus,
                    u.cedula,
                    u.nombre,
                    u.id as usuario_id
                FROM inscritos i
                INNER JOIN usuarios u ON i.id_usuario = u.id
                WHERE i.torneo_id = ? AND i.codigo_equipo = ? AND i.estatus != 'retirado'
                ORDER BY u.nombre ASC
            ");
            $stmt->execute([$equipo['id_torneo'], $equipo['codigo_equipo']]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::getJugadoresEquipo ERROR: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener equipo con todos sus datos y jugadores
     * 
     * @param int $equipoId ID del equipo
     * @return array|null
     */
    public static function getEquipoCompleto(int $equipoId): ?array {
        try {
            $pdo = DB::pdo();
            
            $stmt = $pdo->prepare("
                SELECT 
                    e.*,
                    t.nombre AS nombre_torneo,
                    c.nombre AS nombre_club
                FROM equipos e
                LEFT JOIN tournaments t ON e.id_torneo = t.id
                LEFT JOIN clubes c ON e.id_club = c.id
                WHERE e.id = ?
            ");
            $stmt->execute([$equipoId]);
            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$equipo) {
                return null;
            }
            
            $equipo['jugadores'] = self::getJugadoresEquipo($equipoId);
            
            return $equipo;
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::getEquipoCompleto ERROR: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Remover jugador de un equipo
     * 
     * @param int $jugadorId ID del registro en equipo_jugadores
     * @return array ['success' => bool, 'message' => string]
     */
    public static function removerJugador(int $jugadorId): array {
        try {
            $pdo = DB::pdo();
            
            $stmt = $pdo->prepare("UPDATE equipo_jugadores SET estatus = 0 WHERE id = ?");
            $stmt->execute([$jugadorId]);
            
            return ['success' => true, 'message' => 'Jugador removido del equipo'];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::removerJugador ERROR: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al remover jugador'];
        }
    }
    
    /**
     * Cambiar estatus de un equipo
     * 
     * @param int $equipoId ID del equipo
     * @param int $estatus Nuevo estatus (0=activo, 1=inactivo)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function cambiarEstatus(int $equipoId, int $estatus): array {
        try {
            $pdo = DB::pdo();
            
            $stmt = $pdo->prepare("UPDATE equipos SET estatus = ? WHERE id = ?");
            $stmt->execute([$estatus, $equipoId]);
            
            $estatusTexto = $estatus === 0 ? 'activado' : 'desactivado';
            return ['success' => true, 'message' => "Equipo $estatusTexto"];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::cambiarEstatus ERROR: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al cambiar estatus'];
        }
    }
    
    /**
     * Actualizar estadísticas de un equipo
     * 
     * @param int $equipoId ID del equipo
     * @param array $stats Array con: ganados, perdidos, efectividad, puntos, gff, posicion, sancion
     * @return array ['success' => bool, 'message' => string]
     */
    public static function actualizarEstadisticas(int $equipoId, array $stats): array {
        try {
            $pdo = DB::pdo();
            
            $campos = [];
            $valores = [];
            
            $camposPermitidos = ['ganados', 'perdidos', 'efectividad', 'puntos', 'gff', 'posicion', 'sancion'];
            
            foreach ($camposPermitidos as $campo) {
                if (isset($stats[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = (int)$stats[$campo];
                }
            }
            
            if (empty($campos)) {
                return ['success' => false, 'message' => 'No hay estadísticas para actualizar'];
            }
            
            $valores[] = $equipoId;
            
            $sql = "UPDATE equipos SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($valores);
            
            return ['success' => true, 'message' => 'Estadísticas actualizadas'];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::actualizarEstadisticas ERROR: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar estadísticas'];
        }
    }
    
    /**
     * Obtener siguiente código de equipo disponible (preview)
     * 
     * @param int $torneoId ID del torneo
     * @param int $clubId ID del club
     * @return string Código preview (ej: "05003")
     */
    public static function getProximoCodigo(int $torneoId, int $clubId): string {
        try {
            $pdo = DB::pdo();
            
            $stmt = $pdo->prepare("
                SELECT IFNULL(MAX(consecutivo_club), 0) + 1 as siguiente
                FROM equipos
                WHERE id_torneo = ? AND id_club = ?
            ");
            $stmt->execute([$torneoId, $clubId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $consecutivo = (int)$result['siguiente'];
            
            return str_pad($clubId, 2, '0', STR_PAD_LEFT) . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);
            
        } catch (PDOException $e) {
            return '00000';
        }
    }
    
    /**
     * Verificar si un jugador está disponible para inscribirse en un equipo
     * Valida por cédula, id_usuario o carnet
     * 
     * @param int $torneoId ID del torneo
     * @param string|null $cedula Cédula del jugador (opcional)
     * @param int|null $idUsuario ID del usuario/carnet (opcional)
     * @param int|null $equipoIdExcluir ID del equipo a excluir (para edición)
     * @return array ['disponible' => bool, 'equipo_actual' => string|null, 'jugador' => array|null, 'message' => string]
     */
    public static function verificarDisponibilidadJugador(
        int $torneoId, 
        ?string $cedula = null, 
        ?int $idUsuario = null,
        ?int $equipoIdExcluir = null
    ): array {
        try {
            $pdo = DB::pdo();
            
            $cedulaBusqueda = null;
            $jugadorData = null;
            
            // Si viene id_usuario, buscar su cédula
            if ($idUsuario && $idUsuario > 0) {
                $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE id = ?");
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    $cedulaBusqueda = $usuario['cedula'];
                    $jugadorData = [
                        'id' => $usuario['id'],
                        'cedula' => $usuario['cedula'],
                        'nombre' => $usuario['nombre'],
                        'origen' => 'usuarios'
                    ];
                }
            }
            
            // Si viene cédula directamente
            if ($cedula && !$cedulaBusqueda) {
                $cedulaBusqueda = preg_replace('/^[VEJP]/i', '', trim($cedula));
                
                // Buscar datos del jugador
                $stmt = $pdo->prepare("SELECT id, cedula, nombre FROM usuarios WHERE cedula = ?");
                $stmt->execute([$cedulaBusqueda]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    $jugadorData = [
                        'id' => $usuario['id'],
                        'cedula' => $usuario['cedula'],
                        'nombre' => $usuario['nombre'],
                        'origen' => 'usuarios'
                    ];
                } else {
                    // Buscar en inscripciones
                    $stmt = $pdo->prepare("
                        SELECT id, cedula, nombre FROM inscripciones 
                        WHERE cedula = ? AND torneo_id = ? LIMIT 1
                    ");
                    $stmt->execute([$cedulaBusqueda, $torneoId]);
                    $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($inscripcion) {
                        $jugadorData = [
                            'id' => $inscripcion['id'],
                            'cedula' => $inscripcion['cedula'],
                            'nombre' => $inscripcion['nombre'],
                            'origen' => 'inscripciones'
                        ];
                    }
                }
            }
            
            if (!$cedulaBusqueda) {
                return [
                    'disponible' => false,
                    'equipo_actual' => null,
                    'codigo_equipo' => null,
                    'jugador' => null,
                    'message' => 'Debe proporcionar cédula o ID de usuario'
                ];
            }
            
            // Verificar si ya está en algún equipo (usando inscritos.codigo_equipo)
            $sql = "
                SELECT e.id, e.nombre_equipo, e.codigo_equipo
                FROM inscritos i
                INNER JOIN usuarios u ON i.id_usuario = u.id
                INNER JOIN equipos e ON i.codigo_equipo = e.codigo_equipo AND i.torneo_id = e.id_torneo
                WHERE u.cedula = ? AND i.torneo_id = ? AND i.codigo_equipo IS NOT NULL AND i.estatus != 'retirado' AND e.estatus = 0
            ";
            $params = [$cedulaBusqueda, $torneoId];
            
            if ($equipoIdExcluir && $equipoIdExcluir > 0) {
                $sql .= " AND e.id != ?";
                $params[] = $equipoIdExcluir;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($equipo) {
                return [
                    'disponible' => false,
                    'equipo_actual' => $equipo['nombre_equipo'],
                    'codigo_equipo' => $equipo['codigo_equipo'],
                    'equipo_id' => (int)$equipo['id'],
                    'posicion_en_equipo' => 0, // Ya no se almacena en equipo_jugadores
                    'es_capitan' => false, // Ya no se almacena en equipo_jugadores
                    'jugador' => $jugadorData,
                    'message' => "El jugador ya está inscrito en el equipo '{$equipo['nombre_equipo']}' (Código: {$equipo['codigo_equipo']})"
                ];
            }
            
            return [
                'disponible' => true,
                'equipo_actual' => null,
                'codigo_equipo' => null,
                'jugador' => $jugadorData,
                'message' => $jugadorData ? 'Jugador disponible' : 'Cédula disponible (jugador no encontrado en sistema)'
            ];
            
        } catch (PDOException $e) {
            error_log("EquiposHelper::verificarDisponibilidadJugador ERROR: " . $e->getMessage());
            return [
                'disponible' => false,
                'equipo_actual' => null,
                'codigo_equipo' => null,
                'jugador' => null,
                'message' => 'Error al verificar disponibilidad'
            ];
        }
    }
    
    /**
     * Verificar disponibilidad por cédula (método simplificado - retrocompatibilidad)
     */
    public static function verificarPorCedula(string $cedula, int $torneoId): array {
        return self::verificarDisponibilidadJugador($torneoId, $cedula, null, null);
    }
    
    /**
     * Verificar disponibilidad por ID de usuario/carnet
     */
    public static function verificarPorIdUsuario(int $idUsuario, int $torneoId): array {
        return self::verificarDisponibilidadJugador($torneoId, null, $idUsuario, null);
    }
}

