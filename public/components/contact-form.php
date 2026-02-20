<?php
/**
 * Componente Contact Form - Formulario de comentarios y lista
 * Variables globales: $user, $pdo, app_base_url()
 */
$comentarios = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, u.username as usuario_username, u.nombre as usuario_nombre
        FROM comentariossugerencias c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.estatus = 'aprobado'
        ORDER BY c.fecha_creacion DESC
        LIMIT 20
    ");
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error obteniendo comentarios: " . $e->getMessage());
}
$tipo_clases = ['comentario' => 'bg-blue-100 text-blue-800', 'sugerencia' => 'bg-purple-100 text-purple-800', 'testimonio' => 'bg-yellow-100 text-yellow-800'];
$tipo_iconos = ['comentario' => 'fa-comment-dots', 'sugerencia' => 'fa-lightbulb', 'testimonio' => 'fa-star'];
?>
    <section id="comentarios" class="py-16 md:py-24 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-primary-700 mb-4"><i class="fas fa-comments mr-3 text-accent"></i>Comentarios y Testimonios</h2>
                <p class="text-lg text-gray-600">La opinión de nuestra comunidad es muy importante para nosotros</p>
            </div>
            <?php if (isset($_SESSION['comment_success'])): ?>
            <div class="max-w-4xl mx-auto mb-6">
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md">
                    <div class="flex items-center"><i class="fas fa-check-circle mr-3"></i><p><?= htmlspecialchars($_SESSION['comment_success']); unset($_SESSION['comment_success']); ?></p></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['comment_errors'])): ?>
            <div class="max-w-4xl mx-auto mb-6">
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                    <div class="flex items-center"><i class="fas fa-exclamation-circle mr-3"></i>
                        <div><?php foreach ($_SESSION['comment_errors'] as $err): ?><p><?= htmlspecialchars($err) ?></p><?php endforeach; unset($_SESSION['comment_errors']); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-200 sticky top-24">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center"><i class="fas fa-comment-dots text-primary-500 mr-2"></i>Envía tu Comentario</h3>
                            <?php if ($user): ?>
                            <form action="<?= htmlspecialchars(app_base_url()) ?>/public/index.php?page=comments/save" method="POST" class="space-y-4" id="comment-form">
                                <?php require_once __DIR__ . '/../../config/csrf.php'; ?>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::token()) ?>">
                                <div class="bg-primary-50 p-3 rounded-lg mb-4">
                                    <p class="text-sm text-primary-700"><i class="fas fa-user-check mr-2"></i>Comentando como: <strong><?= htmlspecialchars($user['nombre'] ?? $user['username']) ?></strong></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo *</label>
                                    <select name="tipo" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                        <option value="comentario">Comentario</option>
                                        <option value="sugerencia">Sugerencia</option>
                                        <option value="testimonio">Testimonio</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Calificación (opcional)</label>
                                    <div class="flex space-x-2" id="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <label class="cursor-pointer"><input type="radio" name="calificacion" value="<?= $i ?>" class="hidden star-rating"><i class="far fa-star text-yellow-400 text-2xl hover:text-yellow-500 transition-colors"></i></label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Mensaje *</label>
                                    <textarea name="contenido" rows="5" required placeholder="Escribe tu comentario aquí..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                                </div>
                                <button type="submit" class="w-full bg-gradient-to-r from-primary-500 to-primary-700 text-white py-3 rounded-lg font-bold hover:from-primary-600 hover:to-primary-800 transition-all shadow-lg hover:shadow-xl"><i class="fas fa-paper-plane mr-2"></i>Enviar Comentario</button>
                                <p class="text-xs text-gray-500 text-center"><i class="fas fa-shield-alt mr-1"></i>Los comentarios son moderados antes de publicarse</p>
                            </form>
                            <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-lock text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 mb-4">Debes iniciar sesión para publicar comentarios</p>
                                <a href="login.php?redirect=landing.php%23comentarios" class="inline-block bg-primary-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-600 transition-all"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <?php if (!empty($comentarios)): ?>
                        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border border-gray-200">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div><div class="text-3xl font-bold text-primary-500"><?= count(array_filter($comentarios, fn($c) => ($c['tipo'] ?? '') === 'comentario')) ?></div><div class="text-sm text-gray-600">Comentarios</div></div>
                                <div><div class="text-3xl font-bold text-purple-600"><?= count(array_filter($comentarios, fn($c) => ($c['tipo'] ?? '') === 'sugerencia')) ?></div><div class="text-sm text-gray-600">Sugerencias</div></div>
                                <div><div class="text-3xl font-bold text-yellow-600"><?= count(array_filter($comentarios, fn($c) => ($c['tipo'] ?? '') === 'testimonio')) ?></div><div class="text-sm text-gray-600">Testimonios</div></div>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <?php foreach ($comentarios as $c): 
                                $tipo_actual = $c['tipo'] ?? 'comentario';
                                $clase_tipo = $tipo_clases[$tipo_actual] ?? 'bg-gray-100 text-gray-800';
                                $icono_tipo = $tipo_iconos[$tipo_actual] ?? 'fa-circle';
                            ?>
                            <div id="comentario-<?= (int)$c['id'] ?>" class="bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-200">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-bold"><?= strtoupper(substr($c['nombre'], 0, 1)) ?></div>
                                        <div>
                                            <h4 class="font-bold text-gray-900"><?= htmlspecialchars($c['nombre']) ?>
                                                <?php if (!empty($c['usuario_username'])): ?><span class="text-xs text-primary-500 ml-2"><i class="fas fa-user-check"></i> Usuario registrado</span><?php endif; ?>
                                            </h4>
                                            <span class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($c['fecha_creacion'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $clase_tipo ?>"><i class="fas <?= $icono_tipo ?> mr-1"></i><?= ucfirst($tipo_actual) ?></span>
                                </div>
                                <?php if (!empty($c['calificacion'])): ?>
                                <div class="mb-3"><?php for ($i = 0; $i < 5; $i++): ?><i class="fas fa-star <?= $i < $c['calificacion'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i><?php endfor; ?></div>
                                <?php endif; ?>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?= nl2br(htmlspecialchars($c['contenido'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-lg p-12 text-center border border-gray-200">
                            <i class="fas fa-comment-slash text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">No hay comentarios aún</h3>
                            <p class="text-gray-600 mb-6">Sé el primero en compartir tu opinión con la comunidad.</p>
                            <?php if (!$user): ?><a href="login.php?redirect=landing.php%23comentarios" class="inline-block bg-gradient-to-r from-primary-500 to-primary-700 text-white px-6 py-3 rounded-lg font-semibold hover:from-primary-600 hover:to-primary-800 transition-all"><i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión para Comentar</a><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
