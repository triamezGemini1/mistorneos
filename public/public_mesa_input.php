<?php
/**
 * Interfaz de Mesa - Registro de resultados vía QR (La Estación)
 * Acceso: GET ?t=X&m=Y&r=Z&token=HASH
 * Versión ligera: CSS inline, sin librerías externas, envío expedito.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/QrMesaTokenHelper.php';

$torneo_id = (int)($_GET['t'] ?? $_GET['torneo_id'] ?? 0);
$mesa_id = (int)($_GET['m'] ?? $_GET['mesa_id'] ?? $_GET['mesa'] ?? 0);
$ronda = (int)($_GET['r'] ?? $_GET['ronda'] ?? $_GET['partida'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

$error = '';
$jugadores = [];
$torneo = null;
$submit_url = '';
$mesa_cerrada = false;
$puntos_torneo = 200;
$max_permitido = 320;

$usa_formato_qr = isset($_GET['t']) || isset($_GET['m']) || isset($_GET['r']);
if ($usa_formato_qr && $token === '') {
    $error = 'Enlace inválido. Use el código QR de la hoja oficial.';
} elseif ($token !== '' && !QrMesaTokenHelper::validar($torneo_id, $mesa_id, $ronda, $token)) {
    $error = 'Enlace inválido o expirado.';
}

if ($error === '' && $torneo_id > 0 && $mesa_id > 0 && $ronda > 0) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT nombre, locked, puntos FROM tournaments WHERE id = ?");
        $stmt->execute([$torneo_id]);
        $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

        $torneo_lock = (int)($torneo['locked'] ?? 0) === 1;
        $mesa_confirmada = false;
        $qr_ya_enviado = false;
        $cols_pr = $pdo->query("SHOW COLUMNS FROM partiresul")->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ?");
        $stmt->execute([$torneo_id, $ronda, $mesa_id]);
        $existe_mesa_ronda = ((int)$stmt->fetchColumn()) > 0;

        if (!$existe_mesa_ronda) {
            $error = 'Mesa ' . $mesa_id . ' no asignada para ronda ' . $ronda . '.';
        } else {
            if (in_array('estatus', $cols_pr)) {
                $stmt = $pdo->prepare("SELECT estatus FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? LIMIT 1");
                $stmt->execute([$torneo_id, $ronda, $mesa_id]);
                $est = $stmt->fetchColumn();
                $mesa_confirmada = ($est === 'confirmado' || $est === 1);
            }
            if (in_array('origen_dato', $cols_pr)) {
                $stmt = $pdo->prepare("SELECT 1 FROM partiresul WHERE id_torneo = ? AND partida = ? AND mesa = ? AND origen_dato = 'qr' AND registrado = 1 LIMIT 1");
                $stmt->execute([$torneo_id, $ronda, $mesa_id]);
                $qr_ya_enviado = (bool)$stmt->fetch();
            }
        }

        if ($torneo_lock) {
            $mesa_cerrada = true;
            $error = 'El torneo ha finalizado.';
        } elseif (!$existe_mesa_ronda) {
            $mesa_cerrada = true;
        } elseif ($mesa_confirmada) {
            $mesa_cerrada = true;
            $error = 'Esta mesa ya fue procesada.';
        } elseif ($qr_ya_enviado) {
            $mesa_cerrada = true;
            $error = 'Envío completado.';
        }

        if (!$mesa_cerrada) {
            $stmt = $pdo->prepare("SELECT pr.id_usuario, pr.secuencia, u.nombre FROM partiresul pr INNER JOIN usuarios u ON pr.id_usuario = u.id WHERE pr.id_torneo = ? AND pr.partida = ? AND pr.mesa = ? ORDER BY pr.secuencia ASC");
            $stmt->execute([$torneo_id, $ronda, $mesa_id]);
            $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($jugadores) !== 4) {
                $error = 'La mesa no tiene 4 jugadores.';
                $jugadores = [];
            } else {
                $puntos_torneo = (int)($torneo['puntos'] ?? 200);
                $max_permitido = (int)round($puntos_torneo * 1.6);
                $base = function_exists('AppHelpers') && method_exists('AppHelpers', 'getBaseUrl') ? AppHelpers::getBaseUrl() : (function_exists('app_base_url') ? app_base_url() : '');
                $submit_url = rtrim($base ?? '', '/') . '/actions/public-score-submit';
            }
        }
    } catch (Exception $e) {
        $error = 'Error al cargar datos.';
    }
} elseif ($error === '') {
    $error = 'Faltan parámetros: torneo, mesa y ronda.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#003366">
    <title>Mesa <?= (int)$mesa_id ?> — Ronda <?= (int)$ronda ?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#f0f0f0;min-height:100vh;padding:12px}
        .c{max-width:380px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)}
        .h{background:#003366;color:#fff;padding:16px;text-align:center}
        .h h1{margin:0;font-size:1.1rem}
        .h p{margin:6px 0 0;color:#ff9900;font-weight:600;font-size:.9rem}
        .b{padding:16px}
        .err{background:#fee;color:#c00;padding:12px;border-radius:8px;text-align:center;font-size:.9rem}
        .fld{margin-bottom:16px}
        .fld label{display:block;font-size:.75rem;color:#666;margin-bottom:4px}
        .fld input[type=number]{width:100%;font-size:1.5rem;padding:12px;text-align:center;border:2px solid #003366;border-radius:8px}
        .foto{border:2px dashed #003366;border-radius:8px;padding:24px;text-align:center;background:#00336608}
        .foto input{display:none}
        .foto .lbl{color:#003366;font-weight:600;cursor:pointer;display:block}
        .btn{width:100%;padding:14px;font-size:1rem;font-weight:700;color:#fff;background:#ff9900;border:none;border-radius:8px;cursor:pointer;margin-top:8px}
        .btn:disabled{opacity:.7;cursor:not-allowed}
        .nombres{font-size:.8rem;color:#444;margin-bottom:4px}
        .text-muted{color:#888;font-size:.75rem}
        .err-msg{display:block;font-size:.8rem;color:#c00;margin-top:4px}
        input.is-invalid{border-color:#dc3545 !important;background:#fff5f5}
    </style>
</head>
<body>
    <div class="c">
        <div class="h">
            <h1><?= htmlspecialchars($torneo['nombre'] ?? 'Torneo') ?></h1>
            <p>Ronda <?= (int)$ronda ?> — Mesa <?= (int)$mesa_id ?></p>
        </div>
        <div class="b">
            <?php if ($error): ?>
                <div class="err"><?= htmlspecialchars($error) ?></div>
            <?php elseif (!empty($jugadores)): ?>
                <form id="f" action="<?= htmlspecialchars($submit_url) ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="torneo_id" value="<?= (int)$torneo_id ?>">
                    <input type="hidden" name="mesa_id" value="<?= (int)$mesa_id ?>">
                    <input type="hidden" name="ronda" value="<?= (int)$ronda ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="origen" value="qr">
                    <?php foreach ($jugadores as $i => $j): ?>
                        <input type="hidden" name="jugadores[<?= $i ?>][id_usuario]" value="<?= (int)$j['id_usuario'] ?>">
                        <input type="hidden" name="jugadores[<?= $i ?>][secuencia]" value="<?= (int)$j['secuencia'] ?>">
                        <input type="hidden" name="jugadores[<?= $i ?>][resultado1]" id="r1_<?= $i ?>" value="0">
                        <input type="hidden" name="jugadores[<?= $i ?>][resultado2]" id="r2_<?= $i ?>" value="0">
                    <?php endforeach; ?>

                    <div class="fld" id="fldA">
                        <p class="nombres"><?= htmlspecialchars($jugadores[0]['nombre'] ?? '') ?> / <?= htmlspecialchars($jugadores[1]['nombre'] ?? '') ?></p>
                        <label>Puntos Pareja A <span class="text-muted">(máx. <?= $max_permitido ?>)</span></label>
                        <input type="number" id="pA" min="0" max="<?= $max_permitido ?>" value="0" required>
                        <span class="err-msg" id="errA"></span>
                    </div>
                    <div class="fld" id="fldB">
                        <p class="nombres"><?= htmlspecialchars($jugadores[2]['nombre'] ?? '') ?> / <?= htmlspecialchars($jugadores[3]['nombre'] ?? '') ?></p>
                        <label>Puntos Pareja B <span class="text-muted">(máx. <?= $max_permitido ?>)</span></label>
                        <input type="number" id="pB" min="0" max="<?= $max_permitido ?>" value="0" required>
                        <span class="err-msg" id="errB"></span>
                    </div>
                    <div class="err-msg mb-2" id="errGeneral"></div>
                    <div class="fld">
                        <label>Archivo a subir *</label>
                        <label class="foto">
                            <span class="lbl" id="lblFoto">Seleccionar o tomar foto del acta</span>
                            <input type="file" id="img" name="image" accept="image/jpeg,image/png" capture="camera" required>
                        </label>
                    </div>
                    <button type="submit" class="btn" id="btn">Enviar resultados</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
(function(){
    var f=document.getElementById('f');
    if(!f)return;
    var pA=document.getElementById('pA'), pB=document.getElementById('pB');
    var puntosTorneo=<?= (int)$puntos_torneo ?>;
    var maxPermitido=<?= (int)$max_permitido ?>;

    function sync(){
        var a=parseInt(pA.value,10)||0, b=parseInt(pB.value,10)||0;
        document.getElementById('r1_0').value=document.getElementById('r1_1').value=a;
        document.getElementById('r2_0').value=document.getElementById('r2_1').value=b;
        document.getElementById('r1_2').value=document.getElementById('r1_3').value=b;
        document.getElementById('r2_2').value=document.getElementById('r2_3').value=a;
    }
    function limpiarErrores(){
        pA.classList.remove('is-invalid'); pB.classList.remove('is-invalid');
        document.getElementById('errA').textContent='';
        document.getElementById('errB').textContent='';
        document.getElementById('errGeneral').textContent='';
    }
    function validarPuntos(){
        limpiarErrores();
        var a=parseInt(pA.value,10)||0, b=parseInt(pB.value,10)||0;
        var ok=true;
        if(a>maxPermitido){
            pA.classList.add('is-invalid');
            document.getElementById('errA').textContent='Máximo permitido: '+maxPermitido+' (puntos del torneo + 60%)';
            ok=false;
        }
        if(b>maxPermitido){
            pB.classList.add('is-invalid');
            document.getElementById('errB').textContent='Máximo permitido: '+maxPermitido+' (puntos del torneo + 60%)';
            ok=false;
        }
        if(a===b&&a>0){
            document.getElementById('errGeneral').textContent='Los puntos no pueden ser iguales. Debe haber un ganador.';
            ok=false;
        }
        if(a>=puntosTorneo&&b>=puntosTorneo){
            document.getElementById('errGeneral').textContent='Solo una pareja puede alcanzar los puntos del torneo ('+puntosTorneo+').';
            ok=false;
        }
        return ok;
    }
    pA.oninput=function(){sync();limpiarErrores();};
    pB.oninput=function(){sync();limpiarErrores();};
    sync();
    document.getElementById('img').onchange=function(){
        document.getElementById('lblFoto').textContent=this.files[0]?this.files[0].name:'Seleccionar o tomar foto del acta';
    };

    function comprimirImagen(file, maxAncho, calidad, cb) {
        if(!file||!file.type.match(/^image\/(jpeg|png|webp)/i)){
            cb(file);return;
        }
        var img=new Image(), url=URL.createObjectURL(file);
        img.onload=function(){
            URL.revokeObjectURL(url);
            var w=img.width,h=img.height;
            if(w>maxAncho){ h=Math.round(h*maxAncho/w); w=maxAncho; }
            var c=document.createElement('canvas');
            c.width=w; c.height=h;
            var ctx=c.getContext('2d');
            ctx.drawImage(img,0,0,w,h);
            c.toBlob(function(blob){
                if(blob) cb(new File([blob],'acta.jpg',{type:'image/jpeg'}));
                else cb(file);
            },'image/jpeg',calidad);
        };
        img.onerror=function(){ URL.revokeObjectURL(url); cb(file); };
        img.src=url;
    }

    f.onsubmit=function(e){
        e.preventDefault();
        if(!validarPuntos())return;
        if(!confirm('¿Enviar resultados y foto del acta?'))return;
        var btn=document.getElementById('btn');
        var inputImg=document.getElementById('img');
        var file=inputImg.files[0];
        btn.disabled=true;

        function enviar(fd){
            btn.textContent='Enviando...';
            var ctrl=new AbortController();
            var id=setTimeout(function(){ ctrl.abort(); },90000);
            fetch(f.action,{method:'POST',body:fd,signal:ctrl.signal}).then(function(r){ clearTimeout(id); return r.json(); }).then(function(d){
                btn.disabled=false;
                btn.textContent='Enviar resultados';
                alert(d.success?(d.message||'Enviado correctamente.'):(d.error||'Error al enviar.'));
                if(d.success){ f.reset(); sync(); limpiarErrores(); document.getElementById('lblFoto').textContent='Seleccionar o tomar foto del acta'; }
            }).catch(function(err){
                clearTimeout(id);
                btn.disabled=false;
                btn.textContent='Enviar resultados';
                alert(err.name==='AbortError'?'Tardó demasiado. Compruebe la conexión e intente de nuevo.':'Error de conexión.');
            });
        }

        if(file&&file.size>200000){
            btn.textContent='Comprimiendo foto...';
            comprimirImagen(file,1280,0.72,function(archivo){
                var fd=new FormData(f);
                fd.set('image',archivo,archivo.name||'acta.jpg');
                enviar(fd);
            });
        } else {
            enviar(new FormData(f));
        }
    };
})();
    </script>
</body>
</html>
