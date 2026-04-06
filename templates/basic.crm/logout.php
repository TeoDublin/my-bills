<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth()->logout();

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
