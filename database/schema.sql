-- ========================================================
-- Base de Datos: torneo_infantil
-- Gestor: MySQL
-- ========================================================

CREATE DATABASE IF NOT EXISTS torneo_infantil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE torneo_infantil;

-- 1. Equipos
CREATE TABLE equipo (
    id_equipo INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    foto_url VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Jornadas (Requerimiento 2.b)
CREATE TABLE jornada (
    id_jornada INT AUTO_INCREMENT PRIMARY KEY,
    numero_jornada INT NOT NULL UNIQUE,
    fecha_juego DATE NOT NULL,
    descripcion VARCHAR(150) NULL
);

-- 3. Jugadores (Requerimiento 1)
CREATE TABLE jugador (
    id_jugador INT AUTO_INCREMENT PRIMARY KEY,
    id_equipo INT NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    foto_url VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_jugador_equipo FOREIGN KEY (id_equipo) REFERENCES equipo(id_equipo) ON DELETE RESTRICT
);

-- 4. Partidos (Opción B)
CREATE TABLE partido (
    id_partido INT AUTO_INCREMENT PRIMARY KEY,
    id_jornada INT NOT NULL,
    id_equipo_local INT NOT NULL,
    id_equipo_visita INT NOT NULL,
    fecha DATE NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_partido_jornada FOREIGN KEY (id_jornada) REFERENCES jornada(id_jornada) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_local FOREIGN KEY (id_equipo_local) REFERENCES equipo(id_equipo) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_visita FOREIGN KEY (id_equipo_visita) REFERENCES equipo(id_equipo) ON DELETE RESTRICT,
    CONSTRAINT chk_equipos_distintos CHECK (id_equipo_local <> id_equipo_visita)
);

-- 5. Goles (Requerimiento 2)
CREATE TABLE gol (
    id_gol INT AUTO_INCREMENT PRIMARY KEY,
    id_jugador INT NOT NULL,
    id_partido INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_gol_jugador FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE,
    CONSTRAINT fk_gol_partido FOREIGN KEY (id_partido) REFERENCES partido(id_partido) ON DELETE CASCADE,
    CONSTRAINT uq_jugador_partido UNIQUE (id_jugador, id_partido)
);

-- 6. Incidencias (Requerimiento 3 - Incluye fecha_incidencia y fecha_suspension explícitas)
CREATE TABLE incidencia (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_jugador INT NOT NULL,
    id_partido INT NOT NULL,
    tipo_tarjeta ENUM('amarilla', 'roja') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_incidencia DATE NOT NULL,
    fecha_suspension DATE NULL,
    id_jornada_suspension INT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidencia_jugador FOREIGN KEY (id_jugador) REFERENCES jugador(id_jugador) ON DELETE CASCADE,
    CONSTRAINT fk_incidencia_partido FOREIGN KEY (id_partido) REFERENCES partido(id_partido) ON DELETE CASCADE,
    CONSTRAINT fk_incidencia_jornada_susp FOREIGN KEY (id_jornada_suspension) REFERENCES jornada(id_jornada) ON DELETE SET NULL
);