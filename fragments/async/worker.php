<?php

require_once __DIR__ . '/../../includes/constants.php';
require_once __DIR__ . '/../../includes/class.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../pages/history/functions.php';

if (PHP_SAPI !== 'cli') {

    http_response_code(404);
    exit;
}

$job_key = trim((string) ($argv[1] ?? ''));

if ($job_key === '') {

    fwrite(STDERR, "Missing job key.\n");
    exit(1);
}

try {

    Async()->run_job($job_key);
}
catch (Throwable $throwable) {

    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

exit(0);
