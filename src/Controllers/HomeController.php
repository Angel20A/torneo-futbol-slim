<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use Throwable;

final class HomeController
{
    public function __construct(
        private readonly Twig $view,
        private readonly PDO $db
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        // Prueba de conexión básica con MariaDB para verificar fontanería
        $dbConnected = false;
        $dbVersion = null;

        try {
            /** @var string $version */
            $version = $this->db->query('SELECT VERSION()')->fetchColumn();
            $dbConnected = true;
            $dbVersion = $version;
        } catch (Throwable) {
            $dbConnected = false;
        }

        return $this->view->render($response, 'home.twig', [
            'title' => 'Panel Principal - Torneo Infantil de Fútbol',
            'db_connected' => $dbConnected,
            'db_version' => $dbVersion,
            'php_version' => PHP_VERSION,
        ]);
    }
}
