<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hitrov\Notification\Telegram;

$apiKey = getenv('TELEGRAM_BOT_API_KEY');
$userId = getenv('TELEGRAM_USER_ID');

if (empty($apiKey) || empty($userId)) {
    echo "Telegram credentials not configured. Skipping report.\n";
    return;
}

// Get workflow runs from the last 24 hours
$repo = getenv('GITHUB_REPOSITORY');
$token = getenv('GITHUB_TOKEN');

if (empty($repo) || empty($token)) {
    echo "GitHub credentials not configured. Skipping report.\n";
    return;
}

$yesterday = date('c', strtotime('-24 hours'));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/{$repo}/actions/workflows/tests.yml/runs?created=>{$yesterday}&per_page=100");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/vnd.github.v3+json',
    'User-Agent: PHP'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Failed to fetch workflow runs. HTTP Code: $httpCode\n";
    return;
}

$data = json_decode($response, true);
$runs = $data['workflow_runs'] ?? [];

$total = count($runs);
$success = 0;
$failed = 0;
$cancelled = 0;
$skipped = 0;

foreach ($runs as $run) {
    switch ($run['conclusion']) {
        case 'success':
            $success++;
            break;
        case 'failure':
            $failed++;
            break;
        case 'cancelled':
            $cancelled++;
            break;
        case 'skipped':
            $skipped++;
            break;
    }
}

$message = "📊 *Daily Provisioning Report*\n";
$message .= "_(Last 24 hours)_\n\n";
$message .= "🔄 Total Runs: *$total*\n";
$message .= "✅ Successful: *$success*\n";
$message .= "❌ Failed: *$failed*\n";
$message .= "🚫 Cancelled: *$cancelled*\n";
$message .= "⏭️ Skipped: *$skipped*\n\n";

$message .= "ℹ️ *Status:* System is active and checking regularly.\n";
$message .= "If an instance is successfully created, you will receive a separate 'SUCCESS' alert immediately.";

// Send to Telegram
$telegram = new Telegram();
$telegram->notify($message);

echo "Report sent to Telegram.\n";
