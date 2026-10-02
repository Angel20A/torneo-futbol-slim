<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class JugadorRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna todos los jugadores con datos del equipo asociado y cálculo de edad
     *
     * @param int|null $idEquipo Filtro opcional por equipo
     * @param string|null $busqueda Filtro de búsqueda por nombre o apellido
     * @return array<array<string, mixed>>
     */
    public function all(?int $idEquipo = null, ?string $busqueda = null): array
    {
        $sql = 'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, j.fecha_nacimiento, 
                       j.foto_url, j.creado_en,
                       e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                WHERE 1=1';

        $params = [];

        if ($idEquipo !== null && $idEquipo > 0) {
            $sql .= ' AND j.id_equipo = :id_equipo';
            $params['id_equipo'] = $idEquipo;
        }

        if (!empty($busqueda)) {
            $sql .= ' AND (j.nombres LIKE :busqueda OR j.apellidos LIKE :busqueda OR e.nombre LIKE :busqueda)';
            $params['busqueda'] = '%' . trim($busqueda) . '%';
        }

        $sql .= ' ORDER BY e.nombre ASC, j.apellidos ASC, j.nombres ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca un jugador por ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT j.id_jugador, j.id_equipo, j.nombres, j.apellidos, j.fecha_nacimiento, 
                       j.foto_url, j.creado_en,
                       e.nombre AS equipo_nombre, e.foto_url AS equipo_foto_url,
                       TIMESTAMPDIFF(YEAR, j.fecha_nacimiento, CURDATE()) AS edad
                FROM jugador j
                INNER JOIN equipo e ON j.id_equipo = e.id_equipo
                WHERE j.id_jugador = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $jugador = $stmt->fetch();

        return $jugador ?: null;
    }

    /**
     * Inserta un nuevo jugador
     */
    public function create(
        int $idEquipo,
        string $nombres,
        string $apellidos,
        string $fechaNacimiento,
        string $fotoUrl
    ): int {
        $sql = 'INSERT INTO jugador (id_equipo, nombres, apellidos, fecha_nacimiento, foto_url) 
                VALUES (:id_equipo, :nombres, :apellidos, :fecha_nacimiento, :foto_url)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_equipo' => $idEquipo,
            'nombres' => trim($nombres),
            'apellidos' => trim($apellidos),
            'fecha_nacimiento' => $fechaNacimiento,
            'foto_url' => $fotoUrl,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza la información de un jugador
     */
    public function update(
        int $id,
        int $idEquipo,
        string $nombres,
        string $apellidos,
        string $fechaNacimiento,
        string $fotoUrl
    ): bool {
        $sql = 'UPDATE jugador 
                SET id_equipo = :id_equipo,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    fecha_nacimiento = :fecha_nacimiento,
                    foto_url = :foto_url
                WHERE id_jugador = :id';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'id_equipo' => $idEquipo,
            'nombres' => trim($nombres),
            'apellidos' => trim($apellidos),
            'fecha_nacimiento' => $fechaNacimiento,
            'foto_url' => $fotoUrl,
        ]);
    }

    /**
     * Elimina un jugador de la base de datos
     */
    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM jugador WHERE id_jugador = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Comprueba si el jugador tiene registros dependientes en goles o incidencias
     */
    public function hasGolesOIncidencias(int $id): bool
    {
        $sql = 'SELECT 
                (SELECT COUNT(*) FROM gol WHERE id_jugador = :id) +
                (SELECT COUNT(*) FROM incidencia WHERE id_jugador = :id) AS total';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
