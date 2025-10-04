-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-09-2025 a las 20:44:09
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `proyecto-final`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `ObtenerPermisosUsuario` (IN `p_cedula` VARCHAR(15))   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SELECT DISTINCT
        a.cedula,
        a.nombre as usuario_nombre,
        a.modulo as usuario_modulo,
        p.id as permiso_id,
        p.nombre as permiso_nombre,
        p.descripcion as permiso_descripcion
    FROM `administrador` a
    INNER JOIN `modulo_permisos` mp ON a.modulo = mp.modulo
    INNER JOIN `permisos` p ON mp.permiso_id = p.id
    WHERE a.cedula = p_cedula
    ORDER BY p.nombre;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `UsuarioTienePermiso` (`p_cedula` VARCHAR(15), `p_permiso_nombre` VARCHAR(100)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE tiene_permiso INT DEFAULT 0;
    
    SELECT COUNT(*) INTO tiene_permiso
    FROM `administrador` a
    INNER JOIN `modulo_permisos` mp ON a.modulo = mp.modulo
    INNER JOIN `permisos` p ON mp.permiso_id = p.id
    WHERE a.cedula = p_cedula 
      AND p.nombre = p_permiso_nombre;
    
    RETURN tiene_permiso > 0;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administrador`
--

CREATE TABLE `administrador` (
  `id` int(11) NOT NULL,
  `cedula` varchar(15) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `modulo` enum('Administrador','Vendedor','Agendamiento','Aprovisionamiento') NOT NULL,
  `modulo_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de usuarios administradores del sistema';

--
-- Volcado de datos para la tabla `administrador`
--

INSERT INTO `administrador` (`id`, `cedula`, `nombre`, `email`, `contrasena`, `modulo`, `modulo_id`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, '12345678', 'Administrador Principal', 'admin@conecto.com', '$2y$10$QPc0pZ65zKOd.msgyP5X9.2CpAdArZ2y2/irU4Bs9C2Sno4srnhdK', 'Administrador', 1, '2025-09-21 05:56:08', '2025-09-21 16:25:53'),
(2, '3362273', 'Edgar Albeiro Vanegas Marin', 'evanegam2014@gmail.com', '$2y$10$rmUJnB12VupmyCMhB8ZmI.H0P6rH2jos0btLTfEqDOze2dT36VG0O', 'Administrador', 1, '2025-09-21 06:20:01', '2025-09-28 17:55:41'),
(3, '32456890', 'Aracelly Padilla', 'evanegam2020@gmail.com', '$2y$10$VXm5Wu6Nq8DrTW7p1bWBlOVrx7zd0L8V8uJyr59Xm4GpOLdPORjUe', 'Vendedor', NULL, '2025-09-21 20:11:40', '2025-09-28 15:06:20'),
(4, '34567867', 'Cristian Albeiro Vanegas Agudelo', 'edgar_avanegas@soy.sena.edu.co', '$2y$10$xk/FCqSXXQf4ZJCUSFaE6OrLx06V93qvVLKGZAD.//JMjg7bi.Abm', 'Agendamiento', NULL, '2025-09-21 21:02:10', '2025-09-28 15:06:43'),
(5, '21651205', 'Lorena Vanegas Agudelo', 'aprovisionamiento2025@gmail.com', '$2y$10$NUooaxtV.kdK3CU0y7dRXOhl.nuvGGYkQkvf8/dOrXu7701Zt04y2', 'Aprovisionamiento', NULL, '2025-09-28 16:37:56', '2025-09-28 17:01:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agendamiento`
--

CREATE TABLE `agendamiento` (
  `id` int(11) NOT NULL,
  `cedula_cliente` varchar(20) NOT NULL,
  `fecha_visita` date NOT NULL,
  `franja_visita` enum('AM','PM') NOT NULL,
  `tecnico_asignado` varchar(100) NOT NULL,
  `notas` text DEFAULT NULL,
  `estado_visita` enum('NO Asignado','AGENDADO','REPROGRAMAR','PENDIENTE','CANCELADO') NOT NULL DEFAULT 'NO Asignado',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `agendamiento`
--

INSERT INTO `agendamiento` (`id`, `cedula_cliente`, `fecha_visita`, `franja_visita`, `tecnico_asignado`, `notas`, `estado_visita`, `fecha_registro`, `updated_at`) VALUES
(14, '345678904', '2025-09-30', 'AM', 'Juan Diego Jaramillo', 'LLamar antes de ir ', 'AGENDADO', '2025-09-28 15:08:01', '2025-09-28 15:55:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aprovisionamiento`
--

CREATE TABLE `aprovisionamiento` (
  `id` int(11) NOT NULL,
  `cedula_cliente` varchar(20) NOT NULL,
  `tipo_radio` varchar(100) DEFAULT NULL,
  `mac_serial_radio` varchar(100) DEFAULT NULL,
  `tipo_router_onu` varchar(100) NOT NULL,
  `mac_serial_router` varchar(100) NOT NULL,
  `ip_navegacion` varchar(15) NOT NULL,
  `ip_gestion` varchar(15) DEFAULT NULL,
  `metros_cable` int(11) NOT NULL,
  `tipo_cable` enum('DROP','UTP') DEFAULT NULL,
  `notas_aprovisionamiento` text NOT NULL,
  `estado_aprovisionamiento` enum('NO ASIGNADO','AGENDADO','REPROGRAMAR','PENDIENTE','CANCELADO','CUMPLIDO') NOT NULL DEFAULT 'NO ASIGNADO',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `aprovisionamiento`
--

INSERT INTO `aprovisionamiento` (`id`, `cedula_cliente`, `tipo_radio`, `mac_serial_radio`, `tipo_router_onu`, `mac_serial_router`, `ip_navegacion`, `ip_gestion`, `metros_cable`, `tipo_cable`, `notas_aprovisionamiento`, `estado_aprovisionamiento`, `fecha_registro`, `updated_at`) VALUES
(5, '345678904', '', '', 'HUAWEI 8041', 'CC:CC:00:AA:RR:CC', '192.168.150.12', '10.200.150.12', 300, 'DROP', 'Usuario queda conforme con la instalacion y paga efectivo $35000  ok', 'CUMPLIDO', '2025-09-28 18:42:06', '2025-09-28 18:42:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de módulos del sistema';

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id`, `nombre`, `fecha_creacion`, `fecha_modificacion`) VALUES
(1, 'Administrador', '2025-09-21 16:25:53', '2025-09-21 16:25:53'),
(2, 'Vendedor', '2025-09-21 16:25:53', '2025-09-21 16:25:53'),
(3, 'Agendamiento', '2025-09-21 16:25:53', '2025-09-21 16:25:53'),
(4, 'Aprovisionamiento', '2025-09-21 16:25:53', '2025-09-21 16:25:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulo_permisos`
--

CREATE TABLE `modulo_permisos` (
  `id` int(11) NOT NULL,
  `modulo` enum('Administrador','Vendedor','Agendamiento','Aprovisionamiento') NOT NULL,
  `permiso_id` int(11) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de relación entre módulos y permisos';

--
-- Volcado de datos para la tabla `modulo_permisos`
--

INSERT INTO `modulo_permisos` (`id`, `modulo`, `permiso_id`, `fecha_asignacion`) VALUES
(1, 'Administrador', 1, '2025-09-21 15:07:02'),
(2, 'Administrador', 4, '2025-09-21 15:07:02'),
(3, 'Administrador', 6, '2025-09-21 15:07:02'),
(4, 'Administrador', 11, '2025-09-21 15:07:02'),
(5, 'Administrador', 12, '2025-09-21 15:07:02'),
(6, 'Vendedor', 6, '2025-09-21 15:07:02'),
(7, 'Vendedor', 11, '2025-09-21 15:07:02'),
(8, 'Agendamiento', 12, '2025-09-21 15:07:02'),
(9, 'Agendamiento', 11, '2025-09-21 15:07:02'),
(10, 'Aprovisionamiento', 4, '2025-09-21 15:07:02'),
(11, 'Aprovisionamiento', 11, '2025-09-21 15:07:02'),
(12, 'Administrador', 14, '2025-09-21 17:45:18'),
(13, 'Agendamiento', 14, '2025-09-21 17:45:44'),
(14, 'Aprovisionamiento', 14, '2025-09-21 17:45:58'),
(15, 'Vendedor', 14, '2025-09-21 17:46:13'),
(16, 'Administrador', 15, '2025-09-21 19:58:40'),
(17, 'Administrador', 16, '2025-09-21 19:59:37'),
(18, 'Administrador', 17, '2025-09-24 03:00:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de permisos del sistema';

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `fecha_creacion`, `fecha_modificacion`) VALUES
(1, 'crear_usuario', NULL, '2025-09-21 12:45:31', '2025-09-21 12:50:48'),
(4, 'aprovisionamiento', NULL, '2025-09-21 12:45:31', '2025-09-21 12:48:34'),
(6, 'ventas', NULL, '2025-09-21 12:45:31', '2025-09-21 12:48:20'),
(11, 'consultas', NULL, '2025-09-21 12:47:11', '2025-09-21 12:47:11'),
(12, 'agendamiento', NULL, '2025-09-21 12:49:16', '2025-09-21 12:49:16'),
(14, 'dashboard', NULL, '2025-09-21 17:24:08', '2025-09-21 17:24:08'),
(15, 'permisos', NULL, '2025-09-21 19:48:05', '2025-09-21 19:48:05'),
(16, 'administrar_permisos', NULL, '2025-09-21 19:59:04', '2025-09-21 19:59:04'),
(17, 'exportar_dashboard', NULL, '2025-09-24 02:59:58', '2025-09-24 02:59:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono1` varchar(15) NOT NULL,
  `telefono2` varchar(15) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `municipio` varchar(50) NOT NULL,
  `vereda` varchar(100) NOT NULL,
  `coordenadas` varchar(50) NOT NULL,
  `indicaciones` text NOT NULL,
  `notas` text DEFAULT NULL,
  `num_servicio` varchar(10) NOT NULL,
  `tecnologia` varchar(20) NOT NULL,
  `plan` varchar(20) NOT NULL,
  `vendedor_usuario` varchar(50) NOT NULL,
  `vendedor_nombre` varchar(100) NOT NULL,
  `vendedor_cedula` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `fecha`, `cedula`, `nombre`, `telefono1`, `telefono2`, `email`, `municipio`, `vereda`, `coordenadas`, `indicaciones`, `notas`, `num_servicio`, `tecnologia`, `plan`, `vendedor_usuario`, `vendedor_nombre`, `vendedor_cedula`, `created_at`) VALUES
(14, '2025-09-27 20:40:00', '12356789', 'prueba2', '3243897604', '', 'prueba2@gmail.com', 'Tarso', 'Carilla', '6.198708229710615,-75.49838198279697', 'dddddddddddddddddddddddddddd', '', '1', 'Radio Enlace', '6 Megas', '32456890', 'Aracelly Padilla', '32456890', '2025-09-28 01:43:24'),
(15, '0000-00-00 00:00:00', '345678904', 'prueba3', '3214568903', '3016622577', 'prueba23@gmail.com', 'Amaga', 'Camilo C', '6.198708229710615, -75.49838198279696', 'rrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr', 'ok', '1', 'Fibra Óptica', '500 Megas', '3362273', 'Edgar Albeiro Vanegas Marin', '3362273', '2025-09-28 01:45:39');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_modulo_permisos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_modulo_permisos` (
`id` int(11)
,`modulo` enum('Administrador','Vendedor','Agendamiento','Aprovisionamiento')
,`permiso_id` int(11)
,`permiso_nombre` varchar(100)
,`permiso_descripcion` text
,`fecha_asignacion` timestamp
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_modulo_permisos`
--
DROP TABLE IF EXISTS `vista_modulo_permisos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_modulo_permisos`  AS SELECT `mp`.`id` AS `id`, `mp`.`modulo` AS `modulo`, `p`.`id` AS `permiso_id`, `p`.`nombre` AS `permiso_nombre`, `p`.`descripcion` AS `permiso_descripcion`, `mp`.`fecha_asignacion` AS `fecha_asignacion` FROM (`modulo_permisos` `mp` join `permisos` `p` on(`mp`.`permiso_id` = `p`.`id`)) ORDER BY `mp`.`modulo` ASC, `p`.`nombre` ASC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_cedula` (`cedula`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_modulo` (`modulo`),
  ADD KEY `fk_administrador_modulo` (`modulo_id`);

--
-- Indices de la tabla `agendamiento`
--
ALTER TABLE `agendamiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cedula_cliente` (`cedula_cliente`);

--
-- Indices de la tabla `aprovisionamiento`
--
ALTER TABLE `aprovisionamiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cedula_cliente` (`cedula_cliente`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `modulo_permisos`
--
ALTER TABLE `modulo_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_modulo_permiso` (`modulo`,`permiso_id`),
  ADD KEY `fk_modulo_permisos_permiso` (`permiso_id`),
  ADD KEY `idx_modulo` (`modulo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administrador`
--
ALTER TABLE `administrador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `agendamiento`
--
ALTER TABLE `agendamiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `aprovisionamiento`
--
ALTER TABLE `aprovisionamiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `modulo_permisos`
--
ALTER TABLE `modulo_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `administrador`
--
ALTER TABLE `administrador`
  ADD CONSTRAINT `fk_administrador_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `agendamiento`
--
ALTER TABLE `agendamiento`
  ADD CONSTRAINT `fk_agendamiento_ventas` FOREIGN KEY (`cedula_cliente`) REFERENCES `ventas` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `aprovisionamiento`
--
ALTER TABLE `aprovisionamiento`
  ADD CONSTRAINT `fk_aprovisionamiento_ventas` FOREIGN KEY (`cedula_cliente`) REFERENCES `ventas` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `modulo_permisos`
--
ALTER TABLE `modulo_permisos`
  ADD CONSTRAINT `fk_modulo_permisos_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
