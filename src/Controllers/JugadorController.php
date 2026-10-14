<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EquipoRepository;
use App\Repositories\JugadorRepository;
use App\Services\UploadService;
use App\Support\Flash;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class JugadorController
{
    public function __construct(
        private readonly Twig $view,
        private readonly JugadorRepository $jugadores,
        private readonly EquipoRepository $equipos,
        private readonly UploadService $uploader,
        private readonly Flash $flash
    ) {
    }

    /**
     * Listado general de jugadores con filtros opcionales
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEquipo = !empty($params['id_equipo']) ? (int) $params['id_equipo'] : null;
        $busqueda = !empty($params['q']) ? trim((string) $params['q']) : null;

        $listado = $this->jugadores->all($idEquipo, $busqueda);
        $listaEquipos = $this->equipos->all();

        return $this->view->render($response, 'jugadores/index.twig', [
            'title' => 'Gestión de Jugadores',
            'jugadores' => $listado,
            'equipos' => $listaEquipos,
            'selected_equipo' => $idEquipo,
            'busqueda' => $busqueda,
        ]);
    }

    /**
     * Formulario de registro de un nuevo jugador infantil
     */
    public function create(Request $request, Response $response): Response
    {
        $listaEquipos = $this->equipos->all();

        return $this->view->render($response, 'jugadores/form.twig', [
            'title' => 'Registrar Nuevo Jugador',
            'jugador' => null,
            'equipos' => $listaEquipos,
            'errors' => [],
        ]);
    }

    /**
     * Procesa y guarda un nuevo jugador con manejo transaccional y rollback de archivos físicos.
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $idEquipo = (int) ($data['id_equipo'] ?? 0);
        $nombres = trim((string) ($data['nombres'] ?? ''));
        $apellidos = trim((string) ($data['apellidos'] ?? ''));
        $fechaNacimiento = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $fotoFile = $files['foto'] ?? null;

        $errors = $this->validate($idEquipo, $nombres, $apellidos, $fechaNacimiento);

        // La fotografía es obligatoria al crear un nuevo jugador
        if ($fotoFile === null || $fotoFile->getError() === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'La fotografía del jugador es obligatoria.';
        }

        if (!empty($errors)) {
            return $this->view->render($response, 'jugadores/form.twig', [
                'title' => 'Registrar Nuevo Jugador',
                'jugador' => [
                    'id_equipo' => $idEquipo,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'fecha_nacimiento' => $fechaNacimiento,
                ],
                'equipos' => $this->equipos->all(),
                'errors' => $errors,
            ]);
        }

        $fotoUrl = null;

        try {
            // 1. Subida segura con validación de tipo MIME real y generación de hash único
            $fotoUrl = $this->uploader->upload($fotoFile, 'jugadores');

            // 2. Persistencia en base de datos vía PDO
            $this->jugadores->create($idEquipo, $nombres, $apellidos, $fechaNacimiento, (string) $fotoUrl);
            $this->flash->success("El jugador {$nombres} {$apellidos} fue registrado con éxito.");

            return $response->withHeader('Location', '/jugadores')->withStatus(302);
        } catch (Throwable $e) {
            // 3. ROLLBACK EN DISCO: si falla la inserción en BD, eliminamos la foto física subida
            if ($fotoUrl !== null) {
                $this->uploader->delete($fotoUrl);
            }

            $errors[] = 'Error al registrar el jugador: ' . $e->getMessage();

            return $this->view->render($response, 'jugadores/form.twig', [
                'title' => 'Registrar Nuevo Jugador',
                'jugador' => [
                    'id_equipo' => $idEquipo,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'fecha_nacimiento' => $fechaNacimiento,
                ],
                'equipos' => $this->equipos->all(),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Formulario de edición de un jugador
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jugador = $this->jugadores->findById($id);

        if (!$jugador) {
            $this->flash->error('El jugador especificado no existe.');
            return $response->withHeader('Location', '/jugadores')->withStatus(302);
        }

        return $this->view->render($response, 'jugadores/form.twig', [
            'title' => "Editar Jugador: {$jugador['nombres']} {$jugador['apellidos']}",
            'jugador' => $jugador,
            'equipos' => $this->equipos->all(),
            'errors' => [],
        ]);
    }

    /**
     * Actualiza los datos de un jugador con sustitución segura de imagen y rollback si la BD falla.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jugador = $this->jugadores->findById($id);

        if (!$jugador) {
            $this->flash->error('El jugador no existe.');
            return $response->withHeader('Location', '/jugadores')->withStatus(302);
        }

        $data = (array) $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $idEquipo = (int) ($data['id_equipo'] ?? 0);
        $nombres = trim((string) ($data['nombres'] ?? ''));
        $apellidos = trim((string) ($data['apellidos'] ?? ''));
        $fechaNacimiento = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $fotoFile = $files['foto'] ?? null;

        $errors = $this->validate($idEquipo, $nombres, $apellidos, $fechaNacimiento);

        if (!empty($errors)) {
            return $this->view->render($response, 'jugadores/form.twig', [
                'title' => "Editar Jugador: {$jugador['nombres']} {$jugador['apellidos']}",
                'jugador' => array_merge($jugador, [
                    'id_equipo' => $idEquipo,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'fecha_nacimiento' => $fechaNacimiento,
                ]),
                'equipos' => $this->equipos->all(),
                'errors' => $errors,
            ]);
        }

        $nuevaFotoUrl = null;

        try {
            // 1. Si el usuario proporcionó una nueva fotografía, se sube
            if ($fotoFile !== null && $fotoFile->getError() !== UPLOAD_ERR_NO_FILE) {
                $nuevaFotoUrl = $this->uploader->upload($fotoFile, 'jugadores');
            }

            // 2. Si no subió foto nueva, conservamos la actual
            $fotoFinal = $nuevaFotoUrl ?? $jugador['foto_url'];

            // 3. Actualizamos en base de datos mediante PDO
            $this->jugadores->update(
                $id,
                $idEquipo,
                $nombres,
                $apellidos,
                $fechaNacimiento,
                $fotoFinal
            );

            // 4. Si la BD se actualizó con éxito y se subió foto nueva, eliminamos la foto anterior del disco
            if ($nuevaFotoUrl !== null && !empty($jugador['foto_url'])) {
                $this->uploader->delete($jugador['foto_url']);
            }

            $this->flash->success("Datos del jugador {$nombres} {$apellidos} actualizados correctamente.");

            return $response->withHeader('Location', '/jugadores')->withStatus(302);
        } catch (Throwable $e) {
            // 5. ROLLBACK EN DISCO: Si falló la actualización en BD, eliminamos la nueva foto que quedó huérfana
            if ($nuevaFotoUrl !== null) {
                $this->uploader->delete($nuevaFotoUrl);
            }

            $errors[] = 'Error al actualizar el jugador: ' . $e->getMessage();

            return $this->view->render($response, 'jugadores/form.twig', [
                'title' => "Editar Jugador: {$jugador['nombres']} {$jugador['apellidos']}",
                'jugador' => array_merge($jugador, [
                    'id_equipo' => $idEquipo,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'fecha_nacimiento' => $fechaNacimiento,
                ]),
                'equipos' => $this->equipos->all(),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Elimina un jugador y remueve su archivo físico de imagen del disco
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jugador = $this->jugadores->findById($id);

        if (!$jugador) {
            $this->flash->error('El jugador no fue localizado.');
            return $response->withHeader('Location', '/jugadores')->withStatus(302);
        }

        try {
            if (!empty($jugador['foto_url'])) {
                $this->uploader->delete($jugador['foto_url']);
            }

            $this->jugadores->delete($id);
            $this->flash->success("Jugador {$jugador['nombres']} {$jugador['apellidos']} eliminado con éxito.");
        } catch (Throwable $e) {
            $this->flash->error('No se pudo eliminar el jugador: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/jugadores')->withStatus(302);
    }

    /**
     * Validaciones de negocio para datos de jugador
     *
     * @return array<string>
     */
    private function validate(int $idEquipo, string $nombres, string $apellidos, string $fechaNacimiento): array
    {
        $errors = [];

        if ($idEquipo <= 0 || !$this->equipos->findById($idEquipo)) {
            $errors[] = 'Debe seleccionar un equipo válido.';
        }

        if ($nombres === '') {
            $errors[] = 'El campo nombres es obligatorio.';
        } elseif (mb_strlen($nombres) > 100) {
            $errors[] = 'Los nombres no deben exceder los 100 caracteres.';
        }

        if ($apellidos === '') {
            $errors[] = 'El campo apellidos es obligatorio.';
        } elseif (mb_strlen($apellidos) > 100) {
            $errors[] = 'Los apellidos no deben exceder los 100 caracteres.';
        }

        if ($fechaNacimiento === '') {
            $errors[] = 'La fecha de nacimiento es obligatoria.';
        } else {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $fechaNacimiento);
            if (!$date || $date->format('Y-m-d') !== $fechaNacimiento) {
                $errors[] = 'La fecha de nacimiento no tiene un formato válido (AAAA-MM-DD).';
            } else {
                $hoy = new DateTimeImmutable('today');
                if ($date > $hoy) {
                    $errors[] = 'La fecha de nacimiento no puede ser futura.';
                }
            }
        }

        return $errors;
    }
}
