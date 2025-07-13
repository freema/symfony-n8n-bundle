<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Set test environment variables
$_ENV['APP_ENV'] = 'test';
$_ENV['APP_DEBUG'] = '1';
$_ENV['N8N_BASE_URL'] = 'https://test.n8n.cloud';
$_ENV['N8N_CLIENT_ID'] = 'test-client';
$_ENV['N8N_DRY_RUN'] = '1';
