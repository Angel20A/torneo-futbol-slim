<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JornadaRepository;
use App\Support\Flash;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class JornadaController
{
    public function __construct(
        private readonly Twig $view,
        private readonly JornadaRepository $jornadas,
        private readonly Flash $flash
    ) {
    }

    /**
     * Listado del catálogo de jornadas
     */
    public function index(Request $request, Response $response): Response
    {
        $hoy = (new DateTimeImmutable('today', new DateTimeZone('America/Guatemala')))->format('Y-m-d');

        $listado = array_map(static function (array $jornada) use ($hoy): array {
            $fecha = (string) $jornada['fecha_juego'];
            $jornada['estado'] = $fecha > $hoy ? 'proxima' : ($fecha === $hoy ? 'hoy' : 'realizada');

            return $jornada;
        }, $this->jornadas->all());

        return $this->view->render($response, 'jornadas/index.twig', [
            'title' => 'Gestión de Jornadas',
            'jornadas' => $listado,
        ]);
    }

    /**
     * Formulario para habilitar una nueva jornada (sugiere el siguiente número)
     */
    public function create(Request $request, Response $response): Response
    {
        return $this->renderForm(
            $response,
            'Registrar Nueva Jornada',
            ['numero_jornada' => $this->jornadas->siguienteNumero()],
            [],
            null
        );
    }

    /**
     * Guarda una nueva jornada
     */
    public function store(Request $request, Response $response): Response
    {
        [$numeroRaw, $fecha, $descripcion] = $this->readInput($request);

        $errors = $this->validate($numeroRaw, $fecha, $descripcion);

        $formData = [
            'numero_jornada' => $numeroRaw,
            'fecha_juego' => $fecha,
            'descripcion' => $descripcion,
        ];

        if ($errors !== []) {
            return $this->renderForm($response, 'Registrar Nueva Jornada', $formData, $errors, null);
        }

        try {
            $this->jornadas->create((int) $numeroRaw, $fecha, $descripcion !== '' ? $descripcion : null);
            $this->flash->success("La Jornada {$numeroRaw} fue habilitada correctamente.");

            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al registrar la jornada: ' . $e->getMessage();

            return $this->renderForm($response, 'Registrar Nueva Jornada', $formData, $errors, null);
        }
    }

    /**
     * Formulario de edición de una jornada
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jornada = $this->jornadas->findById($id);

        if ($jornada === null) {
            $this->flash->error('La jornada seleccionada no existe.');
            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        }

        return $this->renderForm($response, "Editar Jornada {$jornada['numero_jornada']}", $jornada, [], $id);
    }

    /**
     * Actualiza una jornada y sincroniza la fecha de sus partidos si esta cambió
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jornada = $this->jornadas->findById($id);

        if ($jornada === null) {
            $this->flash->error('La jornada seleccionada no existe.');
            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        }

        [$numeroRaw, $fecha, $descripcion] = $this->readInput($request);

        $errors = $this->validate($numeroRaw, $fecha, $descripcion, $id);

        $formData = array_merge($jornada, [
            'numero_jornada' => $numeroRaw,
            'fecha_juego' => $fecha,
            'descripcion' => $descripcion,
        ]);

        $title = "Editar Jornada {$jornada['numero_jornada']}";

        if ($errors !== []) {
            return $this->renderForm($response, $title, $formData, $errors, $id);
        }

        try {
            $partidosActualizados = $this->jornadas->update($id, (int) $numeroRaw, $fecha, $descripcion !== '' ? $descripcion : null);
            $this->flash->success("La Jornada {$numeroRaw} fue actualizada correctamente.");

            if ($partidosActualizados > 0) {
                $this->flash->info("Se actualizó la fecha de {$partidosActualizados} partido(s) de esta jornada.");
            }

            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        } catch (Throwable $e) {
            $errors[] = 'Error al actualizar la jornada: ' . $e->getMessage();

            return $this->renderForm($response, $title, $formData, $errors, $id);
        }
    }

    /**
     * Elimina una jornada solo si no tiene partidos ni suspensiones asignadas
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $jornada = $this->jornadas->findById($id);

        if ($jornada === null) {
            $this->flash->error('La jornada no fue encontrada.');
            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        }

        $motivos = [];
        if ($jornada['total_partidos'] > 0) {
            $motivos[] = "{$jornada['total_partidos']} partido(s) programado(s)";
        }
        if ($jornada['total_suspensiones'] > 0) {
            $motivos[] = "{$jornada['total_suspensiones']} suspensión(es) asignada(s)";
        }

        if ($motivos !== []) {
            $this->flash->error(
                "No es posible eliminar la Jornada {$jornada['numero_jornada']} porque tiene " . implode(' y ', $motivos) . '. Elimine o reasigne esos registros primero.'
            );
            return $response->withHeader('Location', '/jornadas')->withStatus(302);
        }

        try {
            $this->jornadas->delete($id);
            $this->flash->success("Jornada {$jornada['numero_jornada']} eliminada satisfactoriamente.");
        } catch (Throwable $e) {
            $this->flash->error('No se pudo eliminar la jornada: ' . $e->getMessage());
        }

        return $response->withHeader('Location', '/jornadas')->withStatus(302);
    }

    /**
     * Renderiza el formulario con las jornadas de referencia para el orden cronológico
     *
     * @param array<string, mixed> $jornada
     * @param array<string> $errors
     */
    private function renderForm(Response $response, string $title, array $jornada, array $errors, ?int $excluirId): Response
    {
        $referencias = [];
        foreach ($this->jornadas->all() as $j) {
            if ($excluirId !== null && (int) $j['id_jornada'] === $excluirId) {
                continue;
            }

            $fecha = (string) $j['fecha_juego'];
            $referencias[] = [
                'numero' => (int) $j['numero_jornada'],
                'fecha' => $fecha,
                'fecha_txt' => (new DateTimeImmutable($fecha))->format('d/m/Y'),
            ];
        }

                // Aviso al editar: una jornada ya realizada con partidos o suspensiones afecta el historial
        $original = $excluirId !== null ? $this->jornadas->findById($excluirId) : null;
        $hoy = (new DateTimeImmutable('today', new DateTimeZone('America/Guatemala')))->format('Y-m-d');
        $yaRealizada = $original !== null
            && (string) $original['fecha_juego'] < $hoy
            && ($original['total_partidos'] > 0 || $original['total_suspensiones'] > 0);

        return $this->view->render($response, 'jornadas/form.twig', [
            'title' => $title,
            'jornada' => $jornada,
            'referencias' => $referencias,
            'errors' => $errors,
            'ya_realizada' => $yaRealizada,
            'original' => $original,
        ]);
    }

    /**
     * Lee número, fecha y descripción del cuerpo de la petición
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function readInput(Request $request): array
    {
        $data = (array) $request->getParsedBody();

        return [
            trim((string) ($data['numero_jornada'] ?? '')),
            trim((string) ($data['fecha_juego'] ?? '')),
            trim((string) ($data['descripcion'] ?? '')),
        ];
    }

    /**
     * Reglas de negocio de una jornada
     *
     * @return array<string>
     */
    private function validate(string $numeroRaw, string $fecha, string $descripcion, ?int $excluirId = null): array
    {
        $errors = [];
        $numero = null;
        $date = null;

        if ($numeroRaw === '') {
            $errors[] = 'El número de jornada es obligatorio.';
        } elseif (!ctype_digit($numeroRaw) || (int) $numeroRaw < 1 || (int) $numeroRaw > 999) {
            $errors[] = 'El número de jornada debe ser un entero entre 1 y 999.';
        } else {
            $numero = (int) $numeroRaw;
            if ($this->jornadas->existsNumero($numero, $excluirId)) {
                $errors[] = "Ya existe una jornada con el número {$numero}.";
                $numero = null;
            }
        }

        if ($fecha === '') {
            $errors[] = 'La fecha de juego es obligatoria.';
        } else {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
            if (!$date || $date->format('Y-m-d') !== $fecha) {
                $errors[] = 'La fecha de juego no tiene un formato válido (AAAA-MM-DD).';
                $date = null;
            } elseif ((int) $date->format('Y') < 2000 || (int) $date->format('Y') > 2100) {
                $errors[] = 'La fecha de juego debe estar entre los años 2000 y 2100.';
                $date = null;
            }
        }

        if (mb_strlen($descripcion) > 150) {
            $errors[] = 'La descripción no debe exceder los 150 caracteres.';
        }

        // Las fechas deben seguir el orden de las jornadas: Jornada 2 después de la 1, etc.
        if ($numero !== null && $date !== null) {
            $anterior = $this->jornadas->anteriorA($numero, $excluirId);
            if ($anterior !== null && $fecha <= (string) $anterior['fecha_juego']) {
                $errors[] = "La fecha debe ser posterior a la de la Jornada {$anterior['numero_jornada']} ({$this->formatear((string) $anterior['fecha_juego'])}).";
            }

            $siguiente = $this->jornadas->siguienteA($numero, $excluirId);
            if ($siguiente !== null && $fecha >= (string) $siguiente['fecha_juego']) {
                $errors[] = "La fecha debe ser anterior a la de la Jornada {$siguiente['numero_jornada']} ({$this->formatear((string) $siguiente['fecha_juego'])}).";
            }
        }

        return $errors;
    }

    private function formatear(string $fecha): string
    {
        return (new DateTimeImmutable($fecha))->format('d/m/Y');
    }
}