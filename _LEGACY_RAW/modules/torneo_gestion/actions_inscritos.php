<?php
/**
 * Acciones POST (y una ruta GET+POST) de inscripciones, bajas/estatus, carga masiva de equipos,
 * generación de rondas y cambios de mesa (reasignación, operadores, manual).
 * Respuestas: JSON para modales/API de equipos; redirects + $_SESSION para flash (Confirmar/Retirar en inscripciones).
 *
 * Requiere que torneo_gestion.php haya cargado auth, db, csrf y rondas_mesas.php antes de incluir este archivo.
 */

require_once __DIR__ . '/rondas_mesas.php';

/**
 * @return list<string>
 */
function torneo_gestion_actions_inscritos_post_action_keys(): array
{
    return [
        'guardar_equipo_sitio',
        'carga_masiva_equipos_validar',
        'carga_masiva_equipos_sitio',
        'cambiar_estatus_inscrito',
        'generar_ronda',
        'eliminar_ultima_ronda',
        'limpiar_ronda',
        'eliminar_ronda',
        'guardar_mesa_adicional',
        'ejecutar_reasignacion',
        'guardar_asignacion_mesas_operador',
        'actualizar_mesa_manual',
    ];
}

function torneo_gestion_actions_inscritos_should_handle_post(string $post_action): bool
{
    return in_array($post_action, torneo_gestion_actions_inscritos_post_action_keys(), true);
}

/**
 * Despacha acciones que terminan en exit (o en handle_post de rondas/mesas, que también hace exit).
 */
function torneo_gestion_actions_inscritos_dispatch_post(string $post_action, int $user_id, bool $is_admin_general): void
{
    switch ($post_action) {
        /** Inscripción equipos en sitio: JSON, misma sesión que el formulario (no public/api). */
        case 'guardar_equipo_sitio':
            $tid = (int)($_GET['torneo_id'] ?? $_POST['torneo_id'] ?? 0);
            if ($tid <= 0) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Torneo no especificado'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            verificarPermisosTorneo($tid, $user_id, $is_admin_general);
            require_once __DIR__ . '/../../lib/GuardarEquipoSitioService.php';
            $input = $_POST;
            if ((int)($input['torneo_id'] ?? 0) !== $tid) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'torneo_id no coincide'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            error_log('=== guardar_equipo_sitio POST torneo_gestion (index/admin, sesión OK) ===');
            header('Content-Type: application/json; charset=utf-8');
            try {
                $pdo = DB::pdo();
                $out = GuardarEquipoSitioService::ejecutar($pdo, $input, Auth::id() ?: null);
                echo json_encode($out, JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(500);
                error_log('guardar_equipo_sitio: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;

        /** Carga masiva: solo validación (sin borrar ni cargar). */
        case 'carga_masiva_equipos_validar':
            $tid = (int)($_POST['torneo_id'] ?? 0);
            if ($tid <= 0) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Torneo no especificado'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            verificarPermisosTorneo($tid, $user_id, $is_admin_general);
            if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'] ?? '')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Adjunte el archivo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            require_once __DIR__ . '/../../lib/CargaMasivaEquiposSitioService.php';
            header('Content-Type: application/json; charset=utf-8');
            $pdo = DB::pdo();
            $parsed = CargaMasivaEquiposSitioService::parseArchivo(
                (string)$_FILES['archivo']['tmp_name'],
                (string)($_FILES['archivo']['name'] ?? 'upload.csv')
            );
            if (isset($parsed['error'])) {
                echo json_encode(['success' => false, 'message' => $parsed['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $val = CargaMasivaEquiposSitioService::validarPrevio($pdo, $tid, $parsed['bloques']);
            echo json_encode([
                'success' => $val['puede_proceder'],
                'message' => $val['puede_proceder']
                    ? 'Archivo válido. Revise el aviso de borrado y confirme para ejecutar.'
                    : 'Revise errores antes de continuar.',
                'validacion' => $val,
                'frase_confirmacion' => CargaMasivaEquiposSitioService::CONFIRMACION_REEMPLAZO,
            ], JSON_UNESCAPED_UNICODE);
            exit;

        /** Carga masiva: borra inscritos + equipos del torneo y vuelve a cargar (requiere confirmación exacta). */
        case 'carga_masiva_equipos_sitio':
            $tid = (int)($_GET['torneo_id'] ?? $_POST['torneo_id'] ?? 0);
            if ($tid <= 0 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Torneo no especificado o método inválido'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            verificarPermisosTorneo($tid, $user_id, $is_admin_general);
            if (isTorneoLocked($tid)) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Torneo cerrado.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!isset($_FILES['archivo']) || !is_uploaded_file($_FILES['archivo']['tmp_name'] ?? '')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'No se recibió archivo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            require_once __DIR__ . '/../../lib/CargaMasivaEquiposSitioService.php';
            header('Content-Type: application/json; charset=utf-8');
            try {
                $pdo = DB::pdo();
                $out = CargaMasivaEquiposSitioService::ejecutarDesdeArchivo(
                    $pdo,
                    $tid,
                    (string)$_FILES['archivo']['tmp_name'],
                    (string)($_FILES['archivo']['name'] ?? 'upload.csv'),
                    Auth::id() ?: null,
                    trim((string)($_POST['confirmar_reemplazo'] ?? ''))
                );
                echo json_encode($out, JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(500);
                error_log('carga_masiva_equipos_sitio: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            exit;

        /** Confirmar / retirar (baja) desde lista de inscripciones — flash para la misma vista. */
        case 'cambiar_estatus_inscrito':
            require_once __DIR__ . '/../../lib/InscritosHelper.php';
            $inscripcion_id = (int)($_POST['inscripcion_id'] ?? 0);
            $torneo_id_ce = (int)($_POST['torneo_id'] ?? 0);
            $nuevo_estatus = (int)($_POST['estatus'] ?? 0);
            if ($inscripcion_id <= 0 || $torneo_id_ce <= 0 || !InscritosHelper::isValidEstatus($nuevo_estatus)) {
                $_SESSION['error'] = 'Parámetros inválidos para cambiar estatus.';
                header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id_ce]));
                exit;
            }
            verificarPermisosTorneo($torneo_id_ce, $user_id, $is_admin_general);
            $pdo = DB::pdo();
            $stmt = $pdo->prepare('SELECT id FROM inscritos WHERE id = ? AND torneo_id = ?');
            $stmt->execute([$inscripcion_id, $torneo_id_ce]);
            if (!$stmt->fetch()) {
                $_SESSION['error'] = 'Inscripción no encontrada.';
                header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id_ce]));
                exit;
            }
            $stmt = $pdo->prepare('UPDATE inscritos SET estatus = ? WHERE id = ? AND torneo_id = ?');
            $stmt->execute([$nuevo_estatus, $inscripcion_id, $torneo_id_ce]);
            $_SESSION['success'] = 'Estatus del inscrito actualizado.';
            header('Location: ' . buildRedirectUrl('inscripciones', ['torneo_id' => $torneo_id_ce]));
            exit;

        case 'generar_ronda':
        case 'eliminar_ultima_ronda':
        case 'limpiar_ronda':
        case 'eliminar_ronda':
        case 'guardar_mesa_adicional':
        case 'ejecutar_reasignacion':
        case 'guardar_asignacion_mesas_operador':
        case 'actualizar_mesa_manual':
            torneo_gestion_rondas_mesas_handle_post($post_action, $user_id, $is_admin_general);
            exit;
    }
}

/**
 * Ruta GET action=guardar_pareja_fija con POST (formulario parejas fijas).
 */
function torneo_gestion_actions_inscritos_dispatch_guardar_pareja_fija(int $torneo_id, int $user_id, bool $is_admin_general): void
{
    verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);
    $base_retorno = (strpos($_SERVER['PHP_SELF'] ?? '', 'admin_torneo.php') !== false || strpos($_SERVER['PHP_SELF'] ?? '', 'panel_torneo.php') !== false)
        ? 'admin_torneo.php' : 'index.php?page=torneo_gestion';
    $sep = (strpos($base_retorno, '?') !== false) ? '&' : '?';
    $url_retorno = $base_retorno . $sep . 'action=gestionar_inscripciones_parejas_fijas&torneo_id=' . (int)$torneo_id;
    require_once __DIR__ . '/../../lib/ParejasFijasHelper.php';
    $pdo = DB::pdo();
    $club_id = (int)($_POST['id_club'] ?? 0);
    $nombre_equipo = trim((string)($_POST['nombre_equipo'] ?? ''));
    $id_usuario1 = (int)($_POST['id_usuario_1'] ?? 0);
    $id_usuario2 = (int)($_POST['id_usuario_2'] ?? 0);
    $nac1 = strtoupper(trim((string)($_POST['nacionalidad_1'] ?? 'V')));
    $nac2 = strtoupper(trim((string)($_POST['nacionalidad_2'] ?? 'V')));
    $ced1 = preg_replace('/\D/', '', (string)($_POST['cedula_1'] ?? ''));
    $ced2 = preg_replace('/\D/', '', (string)($_POST['cedula_2'] ?? ''));
    if ($club_id <= 0 || $id_usuario1 <= 0 || $id_usuario2 <= 0) {
        $_SESSION['error'] = 'Complete club y los dos jugadores. El nombre de la pareja es opcional.';
        header('Location: ' . $url_retorno);
        exit;
    }
    $datosJugadores = [
        $id_usuario1 => ['nacionalidad' => $nac1, 'cedula' => $ced1],
        $id_usuario2 => ['nacionalidad' => $nac2, 'cedula' => $ced2],
    ];
    $resultado = ParejasFijasHelper::crearPareja(
        $pdo,
        $torneo_id,
        $club_id,
        $nombre_equipo !== '' ? $nombre_equipo : null,
        [$id_usuario1, $id_usuario2],
        $user_id,
        $datosJugadores
    );
    if ($resultado['success']) {
        $_SESSION['success'] = $resultado['message'];
    } else {
        $_SESSION['error'] = $resultado['message'];
    }
    header('Location: ' . $url_retorno);
    exit;
}

/**
 * Guarda inscripción de jugador en sitio (flujo clásico por POST; reservado si se enlaza un formulario).
 */
function guardarInscripcionSitio($torneo_id, $user_id, $is_admin_general)
{
    try {
        verificarPermisosTorneo($torneo_id, $user_id, $is_admin_general);

        require_once __DIR__ . '/../../lib/InscritosHelper.php';
        require_once __DIR__ . '/../../lib/UserActivationHelper.php';

        $pdo = DB::pdo();
        $id_usuario = (int)($_POST['id_usuario'] ?? 0);
        $cedula = trim($_POST['cedula'] ?? '');
        $id_club = !empty($_POST['id_club']) ? (int)$_POST['id_club'] : null;
        $estatus_num = (int)($_POST['estatus'] ?? 1);

        if ($estatus_num < 0 || $estatus_num > 4) {
            $estatus_num = 1;
        }

        $estatus = $estatus_num;

        $inscrito_por = $user_id;

        $current_user = Auth::user();
        $user_club_id = $current_user['club_id'] ?? null;

        if (empty($id_usuario) && !empty($cedula)) {
            $cedula_num = preg_replace('/\D/', '', trim($cedula));
            $usuario_encontrado = null;
            if ($cedula_num !== '') {
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE cedula = ? LIMIT 1');
                foreach ([$cedula_num, 'V' . $cedula_num, 'E' . $cedula_num] as $v) {
                    $stmt->execute([$v]);
                    $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($usuario_encontrado) {
                        break;
                    }
                }
            }
            if ($usuario_encontrado) {
                $id_usuario = (int)$usuario_encontrado['id'];
            } else {
                $_SESSION['error'] = 'No encontrado. No hay usuario con esa cédula en la plataforma.';
                header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
                exit;
            }
        }

        if ($id_usuario <= 0) {
            $_SESSION['error'] = 'Debe seleccionar un usuario o proporcionar una cédula válida';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }

        $stmt = $pdo->prepare('SELECT nombre, cedula, sexo, email, username, entidad, nacionalidad FROM usuarios WHERE id = ?');
        $stmt->execute([$id_usuario]);
        $usuario_datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_datos) {
            $_SESSION['error'] = 'No se encontró el usuario seleccionado';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }

        $campos_faltantes = [];
        if (empty(trim($usuario_datos['nombre'] ?? ''))) {
            $campos_faltantes[] = 'Nombre';
        }
        if (empty(trim($usuario_datos['cedula'] ?? ''))) {
            $campos_faltantes[] = 'Cédula';
        }
        if (empty($usuario_datos['sexo'] ?? '')) {
            $campos_faltantes[] = 'Sexo';
        }
        if (empty(trim($usuario_datos['email'] ?? ''))) {
            $campos_faltantes[] = 'Email';
        }
        if (empty(trim($usuario_datos['username'] ?? ''))) {
            $campos_faltantes[] = 'Username';
        }

        if (!empty($campos_faltantes)) {
            $campos_lista = implode(', ', $campos_faltantes);
            $_SESSION['error'] = 'El usuario no puede ser inscrito porque faltan los siguientes campos obligatorios: ' . $campos_lista . '. Por favor complete la información del usuario antes de inscribirlo.';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM inscritos WHERE id_usuario = ? AND torneo_id = ? AND estatus != 4');
        $stmt->execute([$id_usuario, $torneo_id]);

        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Este usuario ya está inscrito en el torneo';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }

        if (!$id_club) {
            $stmt = $pdo->prepare('SELECT club_id FROM usuarios WHERE id = ?');
            $stmt->execute([$id_usuario]);
            $usuario_club = $stmt->fetchColumn();
            $id_club = $usuario_club ?: $user_club_id;
        }

        if ($id_usuario <= 0) {
            throw new Exception('ID de usuario inválido');
        }
        if ($torneo_id <= 0) {
            throw new Exception('ID de torneo inválido');
        }

        $stmt = $pdo->prepare('SELECT modalidad FROM tournaments WHERE id = ?');
        $stmt->execute([$torneo_id]);
        $modalidad = (int)($stmt->fetchColumn() ?? 0);
        $codigo_equipo_inscripcion = InscritosHelper::codigoEquipoParaInscripcionSitioIndividual($pdo, $torneo_id, $id_club, $modalidad);

        try {
            $nac_u = strtoupper(trim((string)($usuario_datos['nacionalidad'] ?? 'V')));
            if (!in_array($nac_u, ['V', 'E', 'J', 'P'], true)) {
                $nac_u = 'V';
            }
            $ced_u = preg_replace('/\D/', '', (string)($usuario_datos['cedula'] ?? ''));
            InscritosHelper::registrarInscripcion($pdo, [
                'id_usuario' => $id_usuario,
                'torneo_id' => $torneo_id,
                'id_club' => $id_club,
                'estatus' => $estatus,
                'inscrito_por' => $inscrito_por,
                'numero' => 0,
                'nacionalidad' => $nac_u,
                'cedula' => $ced_u,
                'codigo_equipo' => $codigo_equipo_inscripcion,
            ]);
            UserActivationHelper::activateUser($pdo, $id_usuario);
            $_SESSION['success'] = 'Jugador inscrito exitosamente';
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        } catch (PDOException $e) {
            error_log('Error PDO al inscribir jugador: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al guardar la inscripción: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        } catch (Exception $e) {
            error_log('Error al inscribir jugador: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al inscribir: ' . $e->getMessage();
            header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
            exit;
        }
    } catch (Exception $e) {
        error_log('Error al inscribir jugador: ' . $e->getMessage());
        $_SESSION['error'] = 'Error al inscribir: ' . $e->getMessage();
        header('Location: ' . buildRedirectUrl('inscribir_sitio', ['torneo_id' => $torneo_id]));
        exit;
    }
}
