CREATE TABLE IF NOT EXISTS usuarios (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL, usuario VARCHAR(80) NOT NULL UNIQUE,
 clave VARCHAR(255) NOT NULL, rol ENUM('admin','vendedor') NOT NULL, activo TINYINT NOT NULL DEFAULT 1,
 fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS eventos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(200) NOT NULL, tipo ENUM('Congreso','Curso','Taller') NOT NULL,
 grupo_minimo INT UNSIGNED NOT NULL DEFAULT 5, grupo_descuento DECIMAL(5,2) NOT NULL DEFAULT 20,
 enlace_grupo_whatsapp VARCHAR(2048) NOT NULL DEFAULT '',
 descripcion TEXT, lugar VARCHAR(250), fecha_inicio DATETIME NOT NULL, fecha_fin DATETIME NOT NULL,
 inicio_inscripcion DATE NOT NULL, fin_inscripcion DATE NOT NULL, cupos INT UNSIGNED NOT NULL DEFAULT 0,
 activo TINYINT NOT NULL DEFAULT 1, qr_pago VARCHAR(100), imagen VARCHAR(100), fondo_certificado VARCHAR(100),
 posicion_x DECIMAL(5,2) NOT NULL DEFAULT 50, posicion_y DECIMAL(5,2) NOT NULL DEFAULT 50,
 tamano_nombre INT NOT NULL DEFAULT 32, color_nombre VARCHAR(7) NOT NULL DEFAULT '#ba1320',
 fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tarifas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_evento INT UNSIGNED NOT NULL,
 categoria ENUM('Estudiante','Profesional','Estudiante BTH','UNIOR') NOT NULL, desde DATE NOT NULL, hasta DATE NOT NULL,
 precio DECIMAL(12,2) NOT NULL, FOREIGN KEY(id_evento) REFERENCES eventos(id),
 INDEX(id_evento,categoria,desde,hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS inscripciones (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_evento INT UNSIGNED NOT NULL,
 codigo_privado CHAR(48) NOT NULL UNIQUE, codigo_certificado CHAR(48) NOT NULL UNIQUE,
 codigo_grupo CHAR(48) NULL, precio_base DECIMAL(12,2) NOT NULL DEFAULT 0, descuento_grupo DECIMAL(12,2) NOT NULL DEFAULT 0, INDEX(codigo_grupo),
 tratamiento VARCHAR(20) NOT NULL DEFAULT '',
 nombres VARCHAR(120) NOT NULL, apellidos VARCHAR(150) NOT NULL, ci VARCHAR(40) NOT NULL,
 ciudad VARCHAR(100) NOT NULL, celular VARCHAR(40) NOT NULL, correo VARCHAR(180) NOT NULL,
 institucion VARCHAR(200), carrera VARCHAR(120) NOT NULL DEFAULT 'Otra carrera', categoria ENUM('Estudiante','Profesional','Estudiante BTH','UNIOR') NOT NULL,
 precio DECIMAL(12,2) NOT NULL,
 monto_cobrado DECIMAL(12,2) NOT NULL DEFAULT 0, id_tarifa INT UNSIGNED NOT NULL,
 estado ENUM('Pendiente','En revisión','Pagado','Rechazado','Anulado') NOT NULL DEFAULT 'Pendiente',
 forma_pago VARCHAR(30), referencia VARCHAR(120), comprobante VARCHAR(100), observacion_revision TEXT,
 fecha_pago DATETIME, id_revisor INT UNSIGNED, id_creador INT UNSIGNED, fecha_asistencia DATETIME,
 fecha_certificado DATETIME, fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(id_evento,ci), FOREIGN KEY(id_evento) REFERENCES eventos(id), FOREIGN KEY(id_tarifa) REFERENCES tarifas(id),
 FOREIGN KEY(id_revisor) REFERENCES usuarios(id), FOREIGN KEY(id_creador) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS materiales (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_evento INT UNSIGNED NOT NULL, nombre VARCHAR(150) NOT NULL,
 existencias INT UNSIGNED NOT NULL, cantidad_por_persona INT UNSIGNED NOT NULL DEFAULT 1, costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
 activo TINYINT NOT NULL DEFAULT 1, UNIQUE(id_evento,nombre), FOREIGN KEY(id_evento) REFERENCES eventos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS entregas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_inscripcion INT UNSIGNED NOT NULL, id_material INT UNSIGNED NOT NULL,
 cantidad INT UNSIGNED NOT NULL, id_entregador INT UNSIGNED NOT NULL, fecha_entrega TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_inscripcion) REFERENCES inscripciones(id), FOREIGN KEY(id_material) REFERENCES materiales(id),
 FOREIGN KEY(id_entregador) REFERENCES usuarios(id), INDEX(id_inscripcion,id_material)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS categorias_gastos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL UNIQUE,
 activo TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS gastos (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_evento INT UNSIGNED NOT NULL, descripcion VARCHAR(250) NOT NULL,
 monto DECIMAL(12,2) NOT NULL, fecha_gasto DATE NOT NULL, id_creador INT UNSIGNED NOT NULL,
 id_categoria INT UNSIGNED NULL, FOREIGN KEY(id_categoria) REFERENCES categorias_gastos(id),
 FOREIGN KEY(id_evento) REFERENCES eventos(id), FOREIGN KEY(id_creador) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS historial (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_usuario INT UNSIGNED, accion VARCHAR(100) NOT NULL,
 detalle TEXT, fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(id_usuario) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pagos_inscripcion (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo_conjunto VARCHAR(60) NOT NULL,
 id_titular INT UNSIGNED NOT NULL,
 importe_abono DECIMAL(12,2) NOT NULL,
 codigo_transaccion VARCHAR(120) NULL UNIQUE,
 archivo_abono VARCHAR(100) NULL,
 estado_abono ENUM('En revisión','Confirmado','Rechazado') NOT NULL DEFAULT 'En revisión',
 metodo_abono VARCHAR(30) NOT NULL,
 fecha_abono TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 fecha_verificacion DATETIME NULL,
 usuario_verifica INT UNSIGNED NULL,
 nota_abono VARCHAR(2000) NULL,
 INDEX(codigo_conjunto),
 FOREIGN KEY(id_titular) REFERENCES inscripciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
