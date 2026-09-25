<?php

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'init':

        require 'upload_init.php';

        break;


    case 'chunk':

        require 'upload_chunk.php';

        break;


    case 'complete':

        require 'upload_complete.php';

        break;


    default:

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Action upload tidak dikenal.'
        ]);

        break;
}

exit;