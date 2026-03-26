CREATE TABLE IF NOT EXISTS bannerclock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nivel INT NOT NULL COMMENT '0=registro maestro global; otros valores=id usuario organizador',
    selector INT NOT NULL DEFAULT 0 COMMENT '0=todos los torneos; >0=id torneo',
    contenido TEXT NOT NULL,
    estatus TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=publicado, 0=oculto',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bannerclock_nivel (nivel),
    INDEX idx_bannerclock_selector (selector),
    INDEX idx_bannerclock_estatus (estatus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
