<?php

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use Peculiarventures\GoodkeyCms\ApiClient;
use Peculiarventures\GoodkeyCms\CmsBuilder;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    if (!isset($data['hash'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Hash parameter is required']);
        exit;
    }

    $hash = $data['hash'];

    // Create client and CMS builder
    $client = new ApiClient(
        getenv('API_URL'),
        getenv('API_TOKEN')
    );

    $builder = new CmsBuilder($client);
    $cms = $builder->create($hash);

    // Return signed data
    header('Content-Type: application/octet-stream');
    echo $cms;
}
