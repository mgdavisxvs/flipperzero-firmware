<?php
/**
 * FoodWatch US — Cron Entry Point (CLI or HTTP self-call)
 *
 * IONOS crontab (recommended — HTTP self-call, no PHP CLI needed):
 *   curl -s "https://yoursite.com/foodwatch/?page=cron_alerts&secret=YOUR_CRON_SECRET" >> /dev/null
 *
 * IONOS PHP CLI crontab (if PHP CLI is available):
 *   php /path/to/foodwatch/cron.php >> /var/log/foodwatch_cron.log 2>&1
 *
 * This file calls the existing cron_alerts HTTP endpoint to run all scheduled
 * work (job queue, ingest, model retrain, retention, retry queue).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Direct HTTP access denied. Use the cron_alerts endpoint instead.\n");
}

// Load config from the main app constants without executing the full dispatcher
$app_dir = __DIR__;

// Read FW_CRON_SECRET from index.php without executing bootstrap
$src = file_get_contents($app_dir . '/index.php');
$secret = 'change-me-before-deploy';
if (preg_match("/const FW_CRON_SECRET\s*=\s*'([^']+)'/", $src, $m)) {
    $secret = $m[1];
}

// Determine base URL — try APP_URL env var, fall back to HTTP_HOST
$base = getenv('APP_URL') ?: ('https://' . (getenv('HTTP_HOST') ?: 'localhost') . '/foodwatch');

$url = rtrim($base, '/') . '/?page=cron_alerts&secret=' . rawurlencode($secret);

echo "[cron] " . date('Y-m-d H:i:s') . " — calling: " . $url . "\n";

if (!function_exists('curl_init')) {
    echo "[cron] ERROR: curl not available. Set APP_URL env var and call the endpoint directly.\n";
    exit(1);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'FoodWatch-Cron/1.0',
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $err) {
    echo "[cron] ERROR: $err\n";
    exit(1);
}

echo "[cron] HTTP $code\n";
$data = json_decode($resp, true);
if ($data) {
    echo "[cron] Response: " . json_encode($data) . "\n";
}

exit($code === 200 ? 0 : 1);
