<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EquipoRepository;
use App\Services\UploadService;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class EquipoController
{
    public function __construct(
        private readonly Twig $view,
        private readonly EquipoRepository $equipos,
        private readonly UploadService $uploader,
        private readonly Flash $flash
    ) {
    }

    /**
     * Listado general de equipos
     */
    public function index(Request $request, Response $response): Response
    {
        $listado = $this->equipos->all();

        return $this->view->render($response, 'equipos/index.twig', [
            'title' => 'Gestión de Equipos',
            'equipos' => $listado,
        ]);
    }

    /**
     * Formulario para crear un nuevo equipo
     */
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'equipos/form.twig', [
            'title' => 'Registrar Nuevo Equipo',
            'equipo' => null,
            'errors' => [],
        ]);
    }

    /**
     * Almacena un nuevo equipo en la base de datos
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $nombre = trim((string) ($data['nombre'] ?? ''));

        $errors = $this->validate($nombre);

        if (!empty($errors)) {
            return $this->view->render($response, 'equipos/form.twig', [
                'title' => 'Registrar Nuevo Equipo',
                'equipo' => ['nombre' => $nombre],
                'errors' => $errors,
            ]);
        }

        try {
            $files = $request->getUploadedFiles();
            $fotoUrl = $this->uploader->upload($files['foto'] ?? null, 'equipos');

            $this->equipos->create($nombre, $fotoUrl);
            $this->flash->success("El equipo '{$nombre}' ha sido registrado exitosamente.");

            return $response->withHeader('Location', '/equipos')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al registrar el equipo: ' . $e->getMessage();

            return $this->view->render($response, 'equipos/form.twig', [
                'title' => 'Registrar Nuevo Equipo',
                'equipo' => ['nombre' => $nombre],
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Formulario para editar un equipo existente
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $equipo = $this->equipos->findById($id);

        if (!$equipo) {
            $this->flash->error('El equipo seleccionado no existe.');
            return $response->withHeader('Location', '/equipos')->withStatus(302);
        }

        return $this->view->render($response, 'equipos/form.twig', [
            'title' => 'Editar Equipo: ' . $equipo['nombre'],
            'equipo' => $equipo,
            'errors' => [],
        ]);
    }

    /**
     * Actualiza la información de un equipo
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $equipo = $this->equipos->findById($id);

        if (!$equipo) {
            $this->flash->error('El equipo seleccionado no existe.');
            return $response->withHeader('Location', '/equipos')->withStatus(302);
        }

        $data = (array) $request->getParsedBody();
        $nombre = trim((string) ($data['nombre'] ?? ''));

        $errors = $this->validate($nombre, $id);

        if (!empty($errors)) {
            return $this->view->render($response, 'equipos/form.twig', [
                'title' => 'Editar Equipo: ' . $equipo['nombre'],
                'equipo' => array_merge($equipo, ['nombre' => $nombre]),
                'errors' => $errors,
            ]);
        }

        try {
            $files = $request->getUploadedFiles();
            $fotoUrl = $this->uploader->upload($files['foto'] ?? null, 'equipos', $equipo['foto_url']);

            $this->equipos->update($id, $nombre, $fotoUrl);
            $this->flash->success("El equipo '{$nombre}' fue actualizado correctamente.");

            return $response->withHeader('Location', '/equipos')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al actualizar el equipo: ' . $e->getMessage();

            return $this->view->render($response, 'equipos/form.twig', [
                'title' => 'Editar Equipo: ' . $equipo['nombre'],
                'equipo' => array_merge($equipo, ['nombre' => $nombre]),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Elimina un equipo si no posee dependencias referenciales
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $equipo = $this->equipos->findById($id);

        if (!$equipo) {
            $this->flash->error('El equipo no fue encontrado.');
            return $response->withHeader('Location', '/equipos')->withStatus(302);
        }

        $totalJugadores = $this->equipos->countJugadores($id);
        if ($totalJugadores > 0) {
            $this->flash->error(
                "No es posible eliminar el equipo '{$equipo['nombre']}' porque cuenta con {$totalJugadores} jugador(es) asociado(s). Reasigne o elimine los jugadores primero."
            );
            return $response->withHeader('Location', '/equipos')->withStatus(302);
        }

        try {
            if (!empty($equipo['foto_url'])) {
                $this->uploader->deleteFile($equipo['foto_url']);
            }

            $this->equipos->delete($id);
            $this->flash->success("Equipo '{$equipo['nombre']}' eliminado satisfactoriamente.");
        } catch (Throwable $e) {
            $this->flash->error('No se pudo eliminar el equipo: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/equipos')->withStatus(302);
    }

    /**
     * Valida reglas de negocio para equipos
     *
     * @return array<string>
     */
    private function validate(string $nombre, ?int $excludeId = null): array
    {
        $errors = [];

        if ($nombre === '') {
            $errors[] = 'El nombre del equipo es obligatorio.';
        } elseif (mb_strlen($nombre) > 100) {
            $errors[] = 'El nombre del equipo no puede exceder los 100 caracteres.';
        } elseif ($this->equipos->existsNombre($nombre, $excludeId)) {
            $errors[] = "Ya existe un equipo registrado con el nombre '{$nombre}'.";
        }

        return $errors;
    }
}
