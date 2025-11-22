<?php


namespace Hitrov\Notification;


use Hitrov\Exception\ApiCallException;
use Hitrov\Exception\CurlException;
use Hitrov\Exception\NotificationException;
use Hitrov\HttpClient;
use Hitrov\Interfaces\NotifierInterface;
use JsonException;

class Telegram implements NotifierInterface
{
    /**
     * @param string $message
     * @return array
     * @throws ApiCallException|CurlException|JsonException|NotificationException
     */
    public function notify(string $message): array
    {
        $apiKey = getenv('TELEGRAM_BOT_API_KEY');
        $telegramUserId = getenv('TELEGRAM_USER_ID');

        $body = http_build_query([
            'text' => $message,
            'chat_id' => $telegramUserId,
        ]);

        $curlOptions = [
            CURLOPT_URL => "https://api.telegram.org/bot$apiKey/sendMessage",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 1,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
        ];

        return HttpClient::getResponse($curlOptions);
    }

    public function isSupported(): bool
    {
        return !empty(getenv('TELEGRAM_BOT_API_KEY')) && !empty(getenv('TELEGRAM_USER_ID'));
    }

    public function getLatestCommand(): ?string
    {
        $apiKey = getenv('TELEGRAM_BOT_API_KEY');
        $telegramUserId = getenv('TELEGRAM_USER_ID');
        
        if (!$this->isSupported()) {
            return null;
        }

        $url = "https://api.telegram.org/bot$apiKey/getUpdates";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['ok']) || !$data['ok'] || empty($data['result'])) {
            return null;
        }
        
        $updates = $data['result'];
        $maxUpdateId = 0;
        $lastCommand = null;
        
        foreach ($updates as $update) {
            $updateId = $update['update_id'];
            if ($updateId > $maxUpdateId) {
                $maxUpdateId = $updateId;
            }
            
            if (!isset($update['message']['text'])) {
                continue;
            }
            
            $message = $update['message'];
            
            // Check if message is from authorized user
            if ((string)$message['from']['id'] !== (string)$telegramUserId) {
                continue;
            }
            
            $text = trim($message['text']);
            // Check for commands (starting with / or \)
            if (strpos($text, '/') === 0 || strpos($text, '\\') === 0) {
                $lastCommand = str_replace('\\', '/', $text);
            }
        }
        
        // Confirm updates so we don't process them again in the next run
        if ($maxUpdateId > 0) {
            $this->confirmUpdates($apiKey, $maxUpdateId + 1);
        }
        
        return $lastCommand;
    }

    private function confirmUpdates(string $apiKey, int $offset): void
    {
        $url = "https://api.telegram.org/bot$apiKey/getUpdates?offset=$offset";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
}
