-- Estructura generada por scripts/export_db_structure.php (PDO)
-- Base: mistorneos @ 2026-03-25T17:57:49+00:00

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `asignacionmesasoperadores`;
CREATE TABLE `asignacionmesasoperadores` (
  `id` int unsigned NOT NULL,
  `id_torneo` int unsigned NOT NULL,
  `partida` int unsigned NOT NULL COMMENT 'Número de ronda/partida',
  `id_operador` int unsigned NOT NULL COMMENT 'ID del usuario operador',
  `mesa_desde` int unsigned NOT NULL COMMENT 'Número de mesa inicial del rango',
  `mesa_hasta` int unsigned NOT NULL COMMENT 'Número de mesa final del rango',
  `asignado_por` int unsigned NOT NULL COMMENT 'ID del admin que asignó',
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1' COMMENT 'Si está activa la asignación'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `atletas`;
CREATE TABLE `atletas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cedula` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sexo` int NOT NULL DEFAULT '0',
  `numfvd` int NOT NULL DEFAULT '0',
  `asociacion` int DEFAULT NULL,
  `estatus` int NOT NULL DEFAULT '1',
  `afiliacion` int NOT NULL DEFAULT '0',
  `anualidad` int NOT NULL DEFAULT '0',
  `carnet` int NOT NULL DEFAULT '0',
  `traspaso` int NOT NULL DEFAULT '0',
  `inscripcion` int NOT NULL DEFAULT '0',
  `categ` int NOT NULL DEFAULT '0',
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `profesion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `celular` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechnac` date DEFAULT NULL,
  `fechfvd` date DEFAULT NULL,
  `fechact` date DEFAULT NULL,
  `foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cedula_img` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_numfvd` (`numfvd`)
) ENGINE=InnoDB AUTO_INCREMENT=6930 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `banner_clock`;
CREATE TABLE `banner_clock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nivel` int NOT NULL,
  `selector` int NOT NULL,
  `contenido` text NOT NULL,
  `estatus` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `bannerclock`;
CREATE TABLE `bannerclock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nivel` int NOT NULL,
  `selector` int NOT NULL DEFAULT '0',
  `contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estatus` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bannerclock_nivel` (`nivel`),
  KEY `idx_bannerclock_selector` (`selector`),
  KEY `idx_bannerclock_estatus` (`estatus`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `calendarioactividades`;
CREATE TABLE `calendarioactividades` (
  `id` int unsigned NOT NULL,
  `titulo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tipo` enum('torneo','reunion','evento','festivo','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'evento',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `lugar` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `torneo_id` int unsigned DEFAULT NULL,
  `club_id` int unsigned DEFAULT NULL,
  `visible_publico` tinyint(1) DEFAULT '1',
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3788d8',
  `creado_por` int unsigned NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clasiranking`;
CREATE TABLE `clasiranking` (
  `id` int unsigned NOT NULL,
  `tipo_torneo` tinyint unsigned NOT NULL COMMENT '1=Individual, 2=Parejas, 3=Equipos',
  `clasificacion` tinyint unsigned NOT NULL COMMENT 'Posición del 1 al 31',
  `puntos_posicion` int unsigned DEFAULT '0' COMMENT 'Puntos asignados por la posición',
  `puntos_por_partida_ganada` int unsigned DEFAULT '0' COMMENT 'Puntos por cada partida ganada',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `club_photos`;
CREATE TABLE `club_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `club_id` int DEFAULT NULL,
  `torneo_id` int DEFAULT NULL,
  `ruta_imagen` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int DEFAULT '0',
  `subido_por` int DEFAULT NULL,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activa` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `subido_por` (`subido_por`),
  KEY `idx_club_id` (`club_id`),
  KEY `idx_orden` (`orden`),
  KEY `idx_torneo_id` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clubes`;
CREATE TABLE `clubes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegado_user_id` int DEFAULT NULL COMMENT 'ID del usuario responsable del club (admin_club o usuario). NULL=usar delegado texto',
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_club_id` smallint NOT NULL,
  `organizacion_id` int DEFAULT NULL COMMENT 'Organización a la que pertenece el club',
  `id_directorio_club` int DEFAULT NULL COMMENT 'ID en directorio_clubes si el club se creó desde invitación por directorio',
  `entidad` int NOT NULL DEFAULT '0' COMMENT 'Entidad de la organización',
  `indica` int NOT NULL DEFAULT '0',
  `estatus` tinyint NOT NULL DEFAULT '1',
  `permite_inscripcion_linea` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=permite inscripción en línea a afiliados, 0=solo en sitio',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_clubes_id_directorio` (`id_directorio_club`),
  KEY `idx_delegado_user_id` (`delegado_user_id`),
  KEY `idx_clubes_organizacion` (`organizacion_id`),
  KEY `idx_clubes_entidad` (`entidad`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `comentariossugerencias`;
CREATE TABLE `comentariossugerencias` (
  `id` int unsigned NOT NULL,
  `usuario_id` int unsigned DEFAULT NULL COMMENT 'NULL si es anónimo',
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del comentarista',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email (opcional)',
  `tipo` enum('comentario','sugerencia','testimonio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'comentario',
  `contenido` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `calificacion` tinyint unsigned DEFAULT NULL COMMENT '1-5 estrellas (opcional)',
  `estatus` enum('pendiente','aprobado','rechazado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `moderado_por` int unsigned DEFAULT NULL COMMENT 'ID del admin que moderó',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_moderacion` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User agent para seguridad'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cuentas_bancarias`;
CREATE TABLE `cuentas_bancarias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_user_id` int NOT NULL,
  `cedula_propietario` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Cédula del propietario de la cuenta',
  `nombre_propietario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del propietario',
  `telefono_afiliado` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Teléfono para pago móvil',
  `banco` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del banco',
  `numero_cuenta` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de cuenta',
  `tipo_cuenta` enum('corriente','ahorro','pagomovil') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tipo de cuenta',
  `estatus` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = Activa, 0 = Inactiva',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_cedula` (`cedula_propietario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cuentas bancarias receptoras de pagos para torneos';

DROP TABLE IF EXISTS `directorio_clubes`;
CREATE TABLE `directorio_clubes` (
  `id` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delegado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_usuario` int unsigned DEFAULT NULL COMMENT 'ID del usuario (usuarios.id) que gestiona este club en invitaciones',
  `indica` int NOT NULL DEFAULT '0',
  `estatus` tinyint NOT NULL DEFAULT '1',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `entidad`;
CREATE TABLE `entidad` (
  `id` int NOT NULL,
  `nombre` varchar(60) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `equipos`;
CREATE TABLE `equipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_torneo` int NOT NULL COMMENT 'Referencia al torneo',
  `id_club` int NOT NULL COMMENT 'Club al que pertenece el equipo',
  `nombre_equipo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del equipo',
  `codigo_equipo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único: LPAD(club,2,0) + LPAD(consecutivo,3,0)',
  `consecutivo_club` int NOT NULL DEFAULT '1' COMMENT 'Consecutivo por club (1 a N)',
  `estatus` tinyint NOT NULL DEFAULT '0' COMMENT '0=Activo, 1=Inactivo',
  `ganados` int NOT NULL DEFAULT '0',
  `perdidos` int NOT NULL DEFAULT '0',
  `efectividad` int NOT NULL DEFAULT '0',
  `puntos` int NOT NULL DEFAULT '0',
  `gff` int NOT NULL DEFAULT '0',
  `posicion` int NOT NULL DEFAULT '0',
  `sancion` int NOT NULL DEFAULT '0',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_equipo_torneo_club` (`id_torneo`,`id_club`,`nombre_equipo`),
  UNIQUE KEY `uk_codigo_torneo` (`id_torneo`,`codigo_equipo`),
  KEY `idx_torneo` (`id_torneo`),
  KEY `idx_club` (`id_club`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_posicion` (`posicion`),
  KEY `idx_puntos` (`puntos`),
  KEY `fk_equipos_creador` (`creado_por`),
  KEY `idx_equipos_ranking` (`id_torneo`,`posicion`,`puntos` DESC,`efectividad` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `historial_parejas`;
CREATE TABLE `historial_parejas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torneo_id` int DEFAULT NULL,
  `ronda_id` int DEFAULT NULL,
  `jugador_1_id` int DEFAULT NULL,
  `jugador_2_id` int DEFAULT NULL,
  `llave` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jugador_1_id` (`jugador_1_id`,`jugador_2_id`),
  KEY `idx_torneo_llave` (`torneo_id`,`llave`)
) ENGINE=MyISAM AUTO_INCREMENT=1407 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `inscritos`;
CREATE TABLE `inscritos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nacionalidad` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cedula` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_usuario` int NOT NULL,
  `torneo_id` int NOT NULL,
  `id_club` int DEFAULT NULL COMMENT 'Club al que pertenece el inscrito',
  `codigo_equipo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT 'Código del equipo al que pertenece el jugador (formato: ccc-sss)',
  `mesa` int NOT NULL DEFAULT '0',
  `letra` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posicion` int DEFAULT '0' COMMENT 'Posici??n en la clasificaci??n del torneo',
  `ganados` int DEFAULT '0' COMMENT 'Partidas ganadas',
  `perdidos` int DEFAULT '0' COMMENT 'Partidas perdidas',
  `efectividad` int DEFAULT '0' COMMENT 'La efectividad es un valor diferencial int',
  `puntos` int DEFAULT '0' COMMENT 'Puntos acumulados en el torneo',
  `ptosrnk` int unsigned DEFAULT '0' COMMENT 'Puntos de ranking: puntos por posici??n + (partidas ganadas ?? puntos por partida ganada)',
  `sancion` int DEFAULT '0' COMMENT 'C??digo de sanci??n',
  `chancletas` int DEFAULT '0' COMMENT 'Contador de chancletas',
  `zapatos` int DEFAULT '0' COMMENT 'Contador de zapatos',
  `tarjeta` int DEFAULT '0' COMMENT 'N??mero de tarjetas',
  `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `inscrito_por` int DEFAULT NULL COMMENT 'ID del usuario que hizo la inscripci??n',
  `numero` int unsigned NOT NULL COMMENT 'Observaciones',
  `clasiequi` int NOT NULL,
  `estatus` int DEFAULT '0' COMMENT 'Estatus: 0=pendiente, 1=confirmado, 2=solvente, 3=no_solvente, 4=retirado',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscripcion` (`id_usuario`,`torneo_id`) COMMENT 'Un usuario solo puede inscribirse una vez por torneo',
  KEY `inscrito_por` (`inscrito_por`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_club` (`id_club`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_puntos` (`puntos`),
  KEY `idx_posicion` (`posicion`),
  KEY `idx_ptosrnk` (`ptosrnk`),
  KEY `idx_codigo_equipo` (`codigo_equipo`),
  KEY `idx_inscritos_torneo_estatus` (`torneo_id`,`estatus`),
  KEY `idx_inscritos_clasificacion` (`torneo_id`,`posicion`,`ganados`,`efectividad`,`puntos`)
) ENGINE=InnoDB AUTO_INCREMENT=377 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invitaciones`;
CREATE TABLE `invitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torneo_id` int NOT NULL,
  `club_id` int NOT NULL DEFAULT '0',
  `id_directorio_club` int DEFAULT NULL COMMENT 'ID en directorio_clubes del club invitado (cuando la invitación se crea desde el directorio)',
  `invitado_delegado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitado_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceso1` datetime NOT NULL,
  `acceso2` datetime NOT NULL,
  `usuario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `club_email` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `club_telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `club_delegado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `id_usuario_vinculado` int unsigned DEFAULT NULL COMMENT 'ID del usuario (usuarios.id) que reclamó esta invitación',
  `estado` enum('pendiente','activa','expirada','cancelada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'activa',
  `admin_club_id` int NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invitaciones_id_directorio_club` (`id_directorio_club`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications_queue`;
CREATE TABLE `notifications_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `canal` enum('telegram','web','email') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_destino` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#',
  `datos_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON con tipo, ronda, mesa, nombre, stats, urls',
  `estado` enum('pendiente','enviado','fallido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_canal` (`canal`),
  KEY `idx_usuario_canal_estado` (`usuario_id`,`canal`,`estado`),
  KEY `idx_notifications_queue_usuario` (`usuario_id`,`canal`,`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=1936 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `organizaciones`;
CREATE TABLE `organizaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre del responsable/presidente',
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entidad` int NOT NULL DEFAULT '0' COMMENT 'Código de entidad geográfica (estado/región)',
  `admin_user_id` int NOT NULL COMMENT 'Usuario admin_club que registró/gestiona esta organización',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatus` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_user_id` (`admin_user_id`),
  KEY `idx_entidad` (`entidad`),
  KEY `idx_estatus` (`estatus`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `partiresul`;
CREATE TABLE `partiresul` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_torneo` int unsigned NOT NULL,
  `partida` int NOT NULL COMMENT 'Número de partida',
  `mesa` int NOT NULL COMMENT 'Número de mesa',
  `secuencia` int NOT NULL COMMENT 'Secuencia dentro de la partida',
  `id_usuario` int unsigned NOT NULL COMMENT 'Usuario que jugó',
  `resultado1` int DEFAULT '0' COMMENT 'Primer resultado',
  `resultado2` int DEFAULT '0' COMMENT 'Segundo resultado',
  `efectividad` int DEFAULT '0' COMMENT 'Efectividad de la partida: +puntos normales, -puntos si forfait',
  `ff` tinyint(1) DEFAULT '0' COMMENT 'Forfeit (No presentado)',
  `tarjeta` int DEFAULT '0' COMMENT 'Tarjetas recibidas',
  `sancion` int DEFAULT '0' COMMENT 'Sanciones aplicadas',
  `chancleta` int DEFAULT '0' COMMENT 'Chancletas',
  `zapato` int DEFAULT '0' COMMENT 'Zapatos',
  `fecha_partida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `registrado_por` int unsigned NOT NULL COMMENT 'Admin que registró el resultado',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `foto_acta` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ruta relativa del acta/foto de la mesa (ej: upload/actas_torneos/xxx.jpg)',
  `registrado` tinyint(1) DEFAULT '0' COMMENT 'Indica si los resultados de esta fila ya fueron registrados (0=No, 1=Sí)',
  `origen_dato` enum('admin','qr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `estatus` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=pendiente, 1=confirmado',
  PRIMARY KEY (`id`),
  KEY `idx_partiresul_torneo_registrado` (`id_torneo`,`registrado`),
  KEY `idx_partiresul_torneo_usuario_partida` (`id_torneo`,`id_usuario`,`partida`)
) ENGINE=InnoDB AUTO_INCREMENT=3057 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resultados de partidas por jugador - Incluye efectividad por forfait';

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torneo_id` int NOT NULL,
  `club_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendiente','confirmado','rechazado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_club` (`club_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `plantillas_notificaciones`;
CREATE TABLE `plantillas_notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: nueva_ronda, resultados, recordatorio_pago',
  `titulo_visual` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: Aviso de Nueva Ronda',
  `cuerpo_mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Texto con variables: {nombre}, {ronda}, {torneo}, etc.',
  `categoria` enum('torneo','afiliacion','general') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nombre_clave` (`nombre_clave`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reportes_pago_usuarios`;
CREATE TABLE `reportes_pago_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL COMMENT 'ID del usuario que reporta el pago',
  `torneo_id` int NOT NULL COMMENT 'ID del torneo',
  `cuenta_id` int DEFAULT NULL COMMENT 'ID de la cuenta bancaria donde se realizó el pago',
  `inscrito_id` int unsigned DEFAULT NULL COMMENT 'ID de la inscripción en la tabla inscritos',
  `cantidad_inscritos` int NOT NULL DEFAULT '1' COMMENT 'Cantidad de personas inscritas (si inscribe a más de 1)',
  `fecha` date NOT NULL COMMENT 'Fecha del pago',
  `hora` time NOT NULL COMMENT 'Hora del pago',
  `tipo_pago` enum('transferencia','pagomovil','efectivo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de pago',
  `banco` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Banco (para transferencia o pagomovil)',
  `monto` decimal(10,2) NOT NULL COMMENT 'Monto del pago',
  `referencia` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de referencia de la transacción',
  `comentarios` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Comentarios adicionales',
  `estatus` enum('pendiente','confirmado','rechazado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente' COMMENT 'Estado del pago',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del reporte',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_inscrito` (`inscrito_id`),
  KEY `idx_estatus` (`estatus`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_cuenta_id` (`cuenta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reportes de pago de usuarios individuales en eventos masivos';

DROP TABLE IF EXISTS `solicitudes_afiliacion`;
CREATE TABLE `solicitudes_afiliacion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `organizacion_id` int DEFAULT NULL,
  `tipo_solicitud` varchar(20) DEFAULT 'particular',
  `nacionalidad` char(1) DEFAULT 'V',
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL COMMENT 'Chat ID Telegram',
  `fechnac` date DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `club_nombre` varchar(150) NOT NULL,
  `club_ubicacion` varchar(255) DEFAULT NULL,
  `org_direccion` varchar(255) DEFAULT NULL,
  `org_responsable` varchar(100) DEFAULT NULL,
  `org_telefono` varchar(50) DEFAULT NULL,
  `org_email` varchar(100) DEFAULT NULL,
  `motivo` text,
  `estatus` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `notas_admin` text,
  `revisado_por` int DEFAULT NULL,
  `revisado_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `entidad` int DEFAULT NULL,
  `rif` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `tournaments`;
CREATE TABLE `tournaments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clase` int NOT NULL DEFAULT '0',
  `modalidad` int NOT NULL DEFAULT '0',
  `tiempo` int NOT NULL DEFAULT '35',
  `puntos` int NOT NULL DEFAULT '200',
  `rondas` int NOT NULL DEFAULT '9',
  `estatus` tinyint NOT NULL DEFAULT '1',
  `permite_inscripcion_linea` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=permite inscripción en línea, 0=solo en sitio',
  `publicar_landing` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=publicar en landing, 0=no publicar',
  `es_evento_masivo` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = Evento masivo con inscripción pública, 0 = Torneo normal',
  `cuenta_id` int DEFAULT NULL COMMENT 'ID de la cuenta bancaria asociada para pagos',
  `costo` int NOT NULL DEFAULT '0',
  `ranking` int NOT NULL DEFAULT '0',
  `pareclub` int NOT NULL DEFAULT '8',
  `fechator` date DEFAULT NULL,
  `hora_torneo` time NOT NULL,
  `tipo_torneo` int NOT NULL,
  `lugar` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Lugar donde se realiza el torneo',
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitacion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normas` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiche` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `club_responsable` int DEFAULT NULL,
  `owner_user_id` int DEFAULT NULL COMMENT 'ID del usuario admin que registra el torneo',
  `organizacion_id` int DEFAULT NULL COMMENT 'Organización que organiza el torneo',
  `entidad` int NOT NULL DEFAULT '0' COMMENT 'Código de entidad - heredado del admin_club',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `finalizado` tinyint(1) DEFAULT '0' COMMENT 'Indica si el torneo está finalizado/cerrado (1 = finalizado, 0 = activo)',
  `fecha_finalizacion` datetime DEFAULT NULL COMMENT 'Fecha y hora en que se finalizó el torneo',
  `correcciones_cierre_at` datetime DEFAULT NULL COMMENT 'Cierre de correcciones 20 min después de completar última mesa',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_club_responsable` (`club_responsable`),
  KEY `idx_tournaments_finalizado` (`finalizado`),
  KEY `idx_slug` (`slug`),
  KEY `idx_es_evento_masivo` (`es_evento_masivo`,`fechator`),
  KEY `idx_cuenta_id` (`cuenta_id`),
  KEY `idx_tournaments_entidad` (`entidad`),
  KEY `idx_tournaments_organizacion` (`organizacion_id`),
  KEY `idx_tournaments_locked` (`locked`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nacionalidad` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cedula` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numfvd` int NOT NULL,
  `sexo` enum('M','F','O') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'M',
  `fechnac` date DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `celular` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de teléfono/celular del usuario',
  `telegram_chat_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chat ID de Telegram para notificaciones',
  `categ` int NOT NULL DEFAULT '0',
  `photo_path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recovery_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin_general','admin_torneo','admin_club','usuario','operador') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'usuario',
  `club_id` int DEFAULT '0',
  `entidad` int NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=activo, 1=inactivo',
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_verificado` tinyint(1) DEFAULT '0' COMMENT 'Indica si el email fue verificado',
  `email_verification_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token para verificación de email',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de verificación del email',
  `recovery_token_expires` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint NOT NULL DEFAULT '1' COMMENT '0=desactivado por admin, 1=activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `cedula` (`cedula`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `fk_approved_by` (`approved_by`),
  KEY `idx_celular` (`celular`),
  KEY `idx_usuarios_club_id` (`club_id`),
  KEY `idx_status` (`status`),
  KEY `carnet_fvd` (`numfvd`)
) ENGINE=InnoDB AUTO_INCREMENT=7150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
