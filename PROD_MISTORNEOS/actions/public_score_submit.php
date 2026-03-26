<?php
/**
 * Controlador: Envío público de resultados de mesa vía QR
 *
 * Recibe por POST: torneo_id, mesa_id, ronda, token, jugadores (puntos, sancion 40/80), image, origen (qr|admin).
 * - Origen 'qr': imagen OBLIGATORIA, estatus = pendiente_verificacion.
 * - Procesa imagen: acta_T{id}_R{ronda}_M{mesa}_{uniqid}.jpg en upload/actas_torneos/
 * - Usa SancionesHelper para tarjetas amarilla/roja (40/80).
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/ImageOptimizer.php';
require_once __DIR__ . '/../lib/SancionesHelper.php';
require_once __DIR__ . '/../lib/QrMesaTokenHelper.php';

$torneo_id = (int)($_POST['torneo_id'] ?? 0);
$mesa_id = (int)($_POST['mesa_id'] ?? $_POST['mesa'] ?? 0);
$ronda = (int)($_POST['ronda'] ?? $_POST['partida'] ?? 0);
$token = trim((string)($_POST['token'] ?? ''));
$origen = trim($_POST['origen'] ?? 'qr');
$jugadores_raw = $_POST['jugadores'] ?? [];
$registrado_por = (int)($_POST['registrado_por'] ?? 1);

if (!in_array($origen, ['admin', 'qr'])) {
    $origen = 'qr';
}

$jugadores = is_array($jugadores_raw) ? $jugadores_raw : [];

// Validación básica
if ($torneo_id <= 0 || $mesa_id <= 0 || $ronda <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan torneo_id, mesa_id o ronda']);
    exit;
}

// Validación de token cuando origen es QR
if ($origen === 'qr' && !QrMesaTokenHelper::validar($torneo_id, $mesa_id, $ronda, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Enlace inválido o expirado. Use el código QR de la hoja de anotación oficial.']);
    exit;
}

if (count($jugadores) !== 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Debe enviar exactamente 4 jugadores']);
    exit;
}

// Validación estricta: origen QR → imagen OBLIGATORIA
$imagen_ok = !empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
if ($origen === 'qr' && !$imagen_ok) {
    $err = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msgs = [
        UPLOAD_ERR_INI_SIZE => 'Archivo excede tamaño máximo',
        UPLOAD_ERR_FORM_SIZE => 'Archivo demasiado grande',
        UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
        UPLOAD_ERR_NO_FILE => 'La foto del acta es obligatoria para envíos vía QR',
    ];
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $msgs[$err] ?? 'La foto del acta es obligatoria para envíos vía QR',
    ]);
    exit;
}

// Si hay imagen, validar tipo (solo JPG, PNG)
$ruta_relativa = null;
if ($imagen_ok) {
    $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Formato de imagen no permitido. Use JPG o PNG.']);
        exit;
    }
}

try {
    $pdo = DB::pdo();

    $cols = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);
    $has_origen = in_array('origen_dato', $cols);
    $has_estatus = in_array('estatus', $cols);
    $has_foto_acta = in_array('foto_acta', $cols);

    // Validación: torneo activo (estatus = 1)
    $stmt = $pdo->prepare("SELECT id, estatus, locked FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $torneo_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$torneo_row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Torneo no encontrado']);
        exit;
    }
    if ((int)($torneo_row['estatus'] ?? 1) !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'El torneo no está activo']);
        exit;
    }

    // Validación: mesa existe y está abierta
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ?");
    $stmt->execute([$torneo_id, $ronda, $mesa_id]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Mesa no encontrada']);
        exit;
    }

    // Cierre de ronda: rechazar si torneo locked o mesa ya confirmada
    $locked = (int)($torneo_row['locked'] ?? 0) === 1;
    if ($locked) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Esta mesa ya ha sido procesada, no se permiten más cambios.']);
        exit;
    }
    if ($has_estatus) {
        $stmt = $pdo->prepare("SELECT estatus FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? LIMIT 1");
        $stmt->execute([$torneo_id, $ronda, $mesa_id]);
        $est = $stmt->fetchColumn();
        if ($est === 'confirmado') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Esta mesa ya ha sido procesada, no se permiten más cambios.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT puntos FROM tournaments WHERE id = ?");
    $stmt->execute([$torneo_id]);
    $puntosTorneo = (int)($stmt->fetchColumn() ?: 200);

    // Procesamiento de imagen: acta_T{id}_R{ronda}_M{mesa}_{uniqid}.jpg
    if ($imagen_ok) {
        $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'actas_torneos' . DIRECTORY_SEPARATOR;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $ext = (strpos($_FILES['image']['type'], 'png') !== false) ? 'png' : 'jpg';
        $filename = sprintf('acta_T%d_R%d_M%d_%s.%s', $torneo_id, $ronda, $mesa_id, uniqid(), $ext);
        $dest_path = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest_path)) {
            throw new Exception('No se pudo guardar la imagen');
        }

        // Optimizar solo si excede 1MB
        $size_bytes = filesize($dest_path);
        if ($size_bytes > 1048576) {
            ImageOptimizer::optimize($dest_path, $dest_path, [
                'quality' => 80,
                'max_width' => 1280,
                'max_height' => 1280,
                'create_webp' => false,
            ]);
        }

        $ruta_relativa = 'upload/actas_torneos/' . $filename;
    }

    // Tarjeta previa para SancionesHelper
    $ids_usuarios = array_map(function ($j) {
        return (int)($j['id_usuario'] ?? 0);
    }, $jugadores);
    $ids_usuarios = array_filter($ids_usuarios);
    $tarjeta_previa = SancionesHelper::getTarjetaPreviaDesdePartidasAnteriores($pdo, $torneo_id, $ronda, array_values($ids_usuarios));

    // Funciones efectividad
    $validarPuntos = fn($p, $pt) => min($p, (int)round($pt * 1.6));
    $efAlcanzo = fn($r1, $r2, $pt) => $r1 == $r2 ? 0 : ($r1 > $r2 ? $pt - $r2 : -($pt - $r1));
    $efNoAlcanzo = fn($r1, $r2) => $r1 == $r2 ? 0 : ($r1 > $r2 ? $r1 - $r2 : -($r2 - $r1));
    $calcularEf = function ($r1, $r2, $pt, $ff, $tarjeta) use ($validarPuntos, $efAlcanzo, $efNoAlcanzo) {
        $r1 = $validarPuntos($r1, $pt);
        $r2 = $validarPuntos($r2, $pt);
        if ($ff == 1) return -$pt;
        if (in_array($tarjeta, [3, 4])) return -$pt;
        $mayor = max($r1, $r2);
        return $mayor >= $pt ? $efAlcanzo($r1, $r2, $pt) : $efNoAlcanzo($r1, $r2);
    };

    $estatus = $origen === 'qr' ? 'pendiente_verificacion' : 'confirmado';

    $pdo->beginTransaction();

    foreach ($jugadores as $j) {
        $id_usuario = (int)($j['id_usuario'] ?? 0);
        $secuencia = (int)($j['secuencia'] ?? 0);
        $resultado1 = (int)($j['resultado1'] ?? 0);
        $resultado2 = (int)($j['resultado2'] ?? 0);
        $ff = isset($j['ff']) && ($j['ff'] == '1' || $j['ff'] === true || $j['ff'] === 'on') ? 1 : 0;
        $tarjeta_form = (int)($j['tarjeta'] ?? 0);
        $sancion_input = (int)($j['sancion'] ?? 0);
        $chancleta = (int)($j['chancleta'] ?? 0);
        $zapato = (int)($j['zapato'] ?? 0);

        $tarjeta_inscritos = (int)($tarjeta_previa[$id_usuario] ?? 0);
        $procesado = SancionesHelper::procesar($sancion_input, $tarjeta_form, $tarjeta_inscritos);
        $tarjeta = $procesado['tarjeta'];
        $sancion_guardar = $procesado['sancion_guardar'];
        $sancion_calc = $procesado['sancion_para_calculo'];

        $resultado1_ajust = max(0, $resultado1 - $sancion_calc);
        $efectividad = $calcularEf($resultado1_ajust, $resultado2, $puntosTorneo, $ff, $tarjeta);

        $cols = [
            'resultado1 = ?', 'resultado2 = ?', 'efectividad = ?', 'ff = ?', 'tarjeta = ?', 'sancion = ?',
            'chancleta = ?', 'zapato = ?', 'fecha_partida = NOW()', 'registrado_por = ?',
            'registrado = 1',
        ];
        $params = [
            $resultado1, $resultado2, $efectividad, $ff, $tarjeta, $sancion_guardar,
            $chancleta, $zapato, $registrado_por,
        ];
        if ($has_origen) { $cols[] = 'origen_dato = ?'; $params[] = $origen; }
        if ($has_estatus) { $cols[] = 'estatus = ?'; $params[] = $estatus; }
        if ($ruta_relativa !== null && $has_foto_acta) {
            $cols[] = 'foto_acta = ?';
            $params[] = $ruta_relativa;
        }

        $params[] = $torneo_id;
        $params[] = $ronda;
        $params[] = $mesa_id;
        $params[] = $id_usuario;
        $params[] = $secuencia;

        $sql = 'UPDATE partiresul SET ' . implode(', ', $cols) . ' WHERE id_torneo = ? AND partida = ? AND mesa = ? AND id_usuario = ? AND secuencia = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $updates = [];
    $up_params = [];
    if ($ruta_relativa !== null && $has_foto_acta) { $updates[] = 'foto_acta = ?'; $up_params[] = $ruta_relativa; }
    if ($has_origen) { $updates[] = 'origen_dato = ?'; $up_params[] = $origen; }
    if ($has_estatus) { $updates[] = 'estatus = ?'; $up_params[] = $estatus; }
    if (!empty($updates)) {
        $up_params = array_merge($up_params, [$torneo_id, $ronda, $mesa_id]);
        $stmt = $pdo->prepare("UPDATE partiresul SET " . implode(', ', $updates) . " WHERE id_torneo = ? AND partida = ? AND mesa = ?");
        $stmt->execute($up_params);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $estatus === 'pendiente_verificacion'
            ? 'Resultado enviado correctamente. Recibira confirmación en breve.'
            : 'Resultados guardados correctamente',
        'foto_acta' => $ruta_relativa,
        'estatus' => $estatus,
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('public_score_submit: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
}
exit;
