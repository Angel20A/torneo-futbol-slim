USE torneo_infantil;

-- Desactivar temporalmente revisión de llaves foráneas para limpieza limpia
-- SET FOREIGN_KEY_CHECKS = 0;
-- TRUNCATE TABLE gol;
-- TRUNCATE TABLE incidencia;
-- TRUNCATE TABLE partido;
-- TRUNCATE TABLE jugador;
-- TRUNCATE TABLE jornada;
-- TRUNCATE TABLE equipo;
-- SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- 1. EQUIPOS (Mapeados a tus archivos en public/uploads/equipos/)
-- ========================================================
INSERT INTO equipo (id_equipo, nombre, foto_url) VALUES
(1, 'FC Barcelona',   '/uploads/equipos/barca.png'),
(2, 'Real Madrid CF', '/uploads/equipos/madrid.png'),
(3, 'Bayern Múnich',  '/uploads/equipos/bayen.png'),
(4, 'Boca Juniors',   '/uploads/equipos/boca.avif');

-- ========================================================
-- 2. JORNADAS (Fechas habilitadas para el torneo)
-- ========================================================
INSERT INTO jornada (id_jornada, numero_jornada, fecha_juego, descripcion) VALUES
(1, 1, '2026-10-03', 'Jornada 1 - Fecha Inaugural'),
(2, 2, '2026-10-10', 'Jornada 2 - Fase de Grupos'),
(3, 3, '2026-10-17', 'Jornada 3 - Cierre Clasificatorio');

-- ========================================================
-- 3. JUGADORES (Mapeados a tus archivos en public/uploads/jugadores/)
-- Categoría infantil 2014, nombres y apellidos en campos separados
-- ========================================================
INSERT INTO jugador (id_jugador, id_equipo, nombres, apellidos, fecha_nacimiento, foto_url) VALUES
(1, 1, 'Lionel Andrés', 'Messi Cuccittini', '2014-06-24', '/uploads/jugadores/messi.jpg'),
(2, 2, 'Cristiano',     'Ronaldo dos Santos', '2014-02-05', '/uploads/jugadores/ronaldo.jpg'),
(3, 1, 'Neymar',        'da Silva Santos',   '2014-03-10', '/uploads/jugadores/neymar.jpg');

-- ========================================================
-- 4. PARTIDOS (Fixture para probar cruces y suspensiones)
-- ========================================================
-- Jornada 1 (2026-10-03)
INSERT INTO partido (id_partido, id_jornada, id_equipo_local, id_equipo_visita, fecha) VALUES
(1, 1, 1, 2, '2026-10-03'), -- Barcelona vs Real Madrid
(2, 1, 3, 4, '2026-10-03'); -- Bayern vs Boca

-- Jornada 2 (2026-10-10)
INSERT INTO partido (id_partido, id_jornada, id_equipo_local, id_equipo_visita, fecha) VALUES
(3, 2, 1, 3, '2026-10-10'), -- Barcelona vs Bayern
(4, 2, 2, 4, '2026-10-10'); -- Real Madrid vs Boca

-- ========================================================
-- 5. GOLES (Para probar el Reporte de Goleadores)
-- ========================================================
-- Partido 1: Barcelona vs Real Madrid
INSERT INTO gol (id_jugador, id_partido, cantidad) VALUES
(1, 1, 2), -- Messi: 2 goles
(2, 1, 1); -- Ronaldo: 1 gol

-- Partido 3: Barcelona vs Bayern (Jornada 2)
INSERT INTO gol (id_jugador, id_partido, cantidad) VALUES
(1, 3, 1), -- Messi: 1 gol más (Acumula 3 goles)
(3, 3, 2); -- Neymar: 2 goles (Acumula 2 goles)

-- ========================================================
-- 6. INCIDENCIAS (Para probar el Reporte del Árbitro y Suspensión)
-- ========================================================
-- Neymar: Tarjeta amarilla en el Clásico (Partido 1)
INSERT INTO incidencia (id_incidencia, id_jugador, id_partido, tipo_tarjeta, descripcion, fecha_incidencia, fecha_suspension, id_jornada_suspension) VALUES
(1, 3, 1, 'amarilla', 'Discusión airada con el árbitro asistente al minuto 34', '2026-10-03', NULL, NULL);

-- Ronaldo: Tarjeta ROJA en el Clásico (Partido 1)
-- Queda suspendido para la Jornada 2 (2026-10-10)
INSERT INTO incidencia (id_incidencia, id_jugador, id_partido, tipo_tarjeta, descripcion, fecha_incidencia, fecha_suspension, id_jornada_suspension) VALUES
(2, 2, 1, 'roja', 'Falta desmedida sin balón en disputa al minuto 88', '2026-10-03', '2026-10-10', 2);