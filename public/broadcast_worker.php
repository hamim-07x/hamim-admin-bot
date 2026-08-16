<?php
/**
 * Background broadcast batch worker.
 * Called with ?job=ID&key=SECRET — processes ~40 users then re-triggers itself.
 * Safe for live traffic: only reads users by id cursor, no locks on bot tables.
 */
ignore_user_abort(true);
@set_time_limit(60);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../bot/broadcast.php';

header('Content-Type: text/plain; charset=utf-8');

$jobId = (int)($_GET['job'] ?? 0);
$key = (string)($_GET['key'] ?? '');
if ($jobId <= 0 || !hash_equals(broadcastWorkerSecret(), $key)) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

// Flush response early so caller doesn't wait
if (function_exists('fastcgi_finish_request')) {
    echo 'ok';
    fastcgi_finish_request();
} else {
    echo 'ok';
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    @flush();
}

$still = processBroadcastBatch($jobId, 40);
if ($still) {
    // brief pause then next batch
    usleep(200000);
    triggerBroadcastWorker($jobId);
}
