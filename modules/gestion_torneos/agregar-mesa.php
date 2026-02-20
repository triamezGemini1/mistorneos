<?php
/**
 * Vista: Agregar Mesa Adicional
 */
$script_actual = basename($_SERVER['PHP_SELF'] ?? '');
$use_standalone_breadcrumb = in_array($script_actual, ['admin_torneo.php', 'panel_torneo.php']);
$base_url_breadcrumb = $use_standalone_breadcrumb ? $script_actual : 'index.php?page=torneo_gestion';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2">
                <i class="fas fa-plus-circle text-primary"></i> Agregar Mesa Adicional
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo $base_url_breadcrumb; ?>">Gestión de Torneos</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $base_url_breadcrumb . ($use_standalone_breadcrumb ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>"><?php echo htmlspecialchars($torneo['nombre']); ?></a></li>
                    <li class="breadcrumb-item active">Agregar Mesa</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <?php
            $use_standalone_btn = $use_standalone_breadcrumb;
            $base_url_btn = $base_url_breadcrumb;
            ?>
            <a href="<?php echo $base_url_btn . ($use_standalone_btn ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Panel
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle mr-2"></i> Instrucciones
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Selecciona 4 jugadores <strong>no asignados</strong> para crear una mesa adicional en la ronda <?php echo $ronda; ?>. 
                        Solo se muestran los jugadores que aún no tienen mesa en esta ronda.
                    </p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Seleccionar Jugadores (solo no asignados)</h5>
                </div>
                <div class="card-body">
                    <?php
                    $use_standalone_form = $use_standalone_breadcrumb;
                    $base_url_form = $base_url_breadcrumb;
                    $total_no_asignados = isset($jugadores) ? count($jugadores) : 0;
                    ?>
                    <?php if ($total_no_asignados < 4): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle mr-2"></i>
                            No hay jugadores disponibles.
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo $base_url_form; ?>">
                        <input type="hidden" name="action" value="guardar_mesa_adicional">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::token(); ?>">
                        <input type="hidden" name="torneo_id" value="<?php echo $torneo['id']; ?>">
                        <input type="hidden" name="ronda" value="<?php echo $ronda; ?>">

                        <div class="row">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="col-md-6 mb-3">
                                    <label>Jugador <?php echo $i; ?>:</label>
                                    <select name="jugadores[]" class="form-control" required>
                                        <option value="">-- Seleccionar Jugador <?php echo $i; ?> --</option>
                                        <?php foreach ($jugadores as $jugador): ?>
                                            <option value="<?php echo $jugador['id_usuario']; ?>">
                                                <?php echo htmlspecialchars($jugador['nombre_completo'] ?? $jugador['nombre'] ?? 'N/A'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Importante:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Asegúrate de seleccionar 4 jugadores diferentes</li>
                                <li>La mesa se agregará automáticamente con el siguiente número disponible</li>
                                <li>Después de crear la mesa, deberás ingresar los resultados manualmente</li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success btn-lg" <?php echo $total_no_asignados < 4 ? 'disabled' : ''; ?>>
                                <i class="fas fa-check mr-2"></i> Crear Mesa Adicional
                            </button>
                            <?php
                            $use_standalone = $use_standalone_breadcrumb;
                            $base_url = $base_url_breadcrumb;
                            ?>
                            <a href="<?php echo $base_url . ($use_standalone ? '?' : '&'); ?>action=panel&torneo_id=<?php echo $torneo['id']; ?>" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times mr-2"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>










