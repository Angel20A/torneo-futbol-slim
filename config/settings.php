<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'Torneo Infantil de Fútbol',
        'env' => $_ENV['APP_ENV'] ?? 'local',
        'debug' => ($_ENV['APP_ENV'] ?? 'local') === 'local',
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_NAME'] ?? 'torneo_infantil',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'flags' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ],
    ],
    'twig' => [
        'path' => dirname(__DIR__) . '/templates',
        'options' => [
            'cache' => ($_ENV['APP_ENV'] ?? 'local') === 'production' 
                ? dirname(__DIR__) . '/var/cache/twig' 
                : false,
            'debug' => ($_ENV['APP_ENV'] ?? 'local') === 'local',
            'auto_reload' => true,
        ],
    ],
];
