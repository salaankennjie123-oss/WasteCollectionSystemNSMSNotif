<?php

interface SmsGatewayInterface
{
    public function send(string $number, string $text): bool;
}

class MockSmsGateway implements SmsGatewayInterface
{
    private float $failRate;

    public function __construct(float $failRate = 0.05)
    {
        $this->failRate = $failRate;
    }

    public function send(string $number, string $text): bool
    {
        if (mt_rand() / mt_getrandmax() < $this->failRate) {
            return false;
        }

        return true;
    }
}

class HttpSmsGateway implements SmsGatewayInterface
{
    private string $url;
    private string $apiKey;
    private string $sender;

    public function __construct(string $url, string $apiKey, string $sender)
    {
        $this->url = $url;
        $this->apiKey = $apiKey;
        $this->sender = $sender;
    }

    public function send(string $number, string $text): bool
    {
        if ($this->url === '' || $this->apiKey === '') {
            return false;
        }

        $payload = [
            'number' => $number,
            'message' => $text,
            'sendername' => $this->sender,
            'apikey' => $this->apiKey,
        ];

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response !== false && $httpCode >= 200 && $httpCode < 300;
    }
}

class SmsNotification
{
    private SmsGatewayInterface $gateway;

    public function __construct(SmsGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function compileRecipients(): array
    {
        $rows = query(
            "SELECT resident_id, name, mobile_number FROM residents WHERE is_deleted = 0 AND mobile_number IS NOT NULL AND mobile_number <> ''"
        );

        return $rows;
    }

    public function formatMessage(string $text): string
    {
        return trim($text);
    }

    public function sendBatch(array $recipients, string $message, ?int $scheduleId = null, ?int $announcementId = null): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $resident) {
            $mobile = normalizePhilippineMobile($resident['mobile_number'] ?? '');
            if ($mobile === '') {
                query(
                    "INSERT INTO sms_logs (schedule_id, announcement_id, resident_id, message, status, sent_at, attempts)
                     VALUES (?, ?, ?, ?, 'failed', NOW(), 1)",
                    [$scheduleId, $announcementId, (int) $resident['resident_id'], $message]
                );
                $failed++;
                continue;
            }

            $success = $this->gateway->send($mobile, $this->formatMessage($message));
            $status = $success ? 'sent' : 'failed';
            $attempts = 1;

            query(
                "INSERT INTO sms_logs (schedule_id, announcement_id, resident_id, message, status, sent_at, attempts)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $scheduleId,
                    $announcementId,
                    (int) $resident['resident_id'],
                    $message,
                    $status,
                    $success ? date('Y-m-d H:i:s') : null,
                    $attempts
                ]
            );

            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}