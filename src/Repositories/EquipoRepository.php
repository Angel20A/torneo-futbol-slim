<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EquipoRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Retorna todos los equipos junto con el conteo de sus jugadores registrados
     *
     * @return array<array<string, mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT e.id_equipo, e.nombre, e.foto_url, e.creado_en, COUNT(j.id_jugador) AS total_jugadores
                FROM equipo e
                LEFT JOIN jugador j ON e.id_equipo = j.id_equipo
                GROUP BY e.id_equipo, e.nombre, e.foto_url, e.creado_en
                ORDER BY e.nombre ASC';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Busca un equipo por su ID
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT e.id_equipo, e.nombre, e.foto_url, e.creado_en, COUNT(j.id_jugador) AS total_jugadores
                FROM equipo e
                LEFT JOIN jugador j ON e.id_equipo = j.id_equipo
                WHERE e.id_equipo = :id
                GROUP BY e.id_equipo, e.nombre, e.foto_url, e.creado_en';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $equipo = $stmt->fetch();

        return $equipo ?: null;
    }

    /**
     * Inserta un nuevo equipo y retorna el ID autogenerado
     */
    public function create(string $nombre, ?string $fotoUrl): int
    {
        $sql = 'INSERT INTO equipo (nombre, foto_url) VALUES (:nombre, :foto_url)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nombre' => trim($nombre),
            'foto_url' => $fotoUrl,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de un equipo existente
     */
    public function update(int $id, string $nombre, ?string $fotoUrl): bool
    {
        $sql = 'UPDATE equipo SET nombre = :nombre, foto_url = :foto_url WHERE id_equipo = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'nombre' => trim($nombre),
            'foto_url' => $fotoUrl,
        ]);
    }

    /**
     * Elimina un equipo por ID
     */
    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM equipo WHERE id_equipo = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Retorna la cantidad de jugadores asociados a un equipo
     */
    public function countJugadores(int $id): int
    {
        $sql = 'SELECT COUNT(*) FROM jugador WHERE id_equipo = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica si ya existe otro equipo con el mismo nombre (evita violación de UNIQUE)
     */
    public function existsNombre(string $nombre, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM equipo WHERE LOWER(nombre) = LOWER(:nombre)';
        $params = ['nombre' => trim($nombre)];

        if ($excludeId !== null) {
            $sql .= ' AND id_equipo <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
