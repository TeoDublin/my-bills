<?php

require_once __DIR__ . '/montly_sync.php';

if (PHP_SAPI !== 'cli') {

    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

try {

    $summary = montly_sync_run($argv[1] ?? null);
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}
catch (Throwable $exception) {

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
