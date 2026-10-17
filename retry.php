<?php
require __DIR__ . '/config.php';

$failedRows = query("SELECT * FROM sms_logs WHERE status = 'failed' ORDER BY sms_id ASC");

foreach ($failedRows as $row) {
    $resident = fetchOne("SELECT * FROM residents WHERE resident_id = ?", [(int) $row['resident_id']]);
    if (!$resident) {
        continue;
    }

    $mobile = normalizePhilippineMobile($resident['mobile_number'] ?? '');
    if ($mobile === '') {
        continue;
    }

    $gateway = new MockSmsGateway(SMS_FAIL_RATE);
    $success = $gateway->send($mobile, $row['message']);

    if ($success) {
        query(
            "UPDATE sms_logs SET status='sent', sent_at = NOW(), attempts = attempts + 1 WHERE sms_id = ?",
            [(int) $row['sms_id']]
        );
        echo "Retried and delivered: " . $row['sms_id'] . PHP_EOL;
    } else {
        query(
            "UPDATE sms_logs SET attempts = attempts + 1 WHERE sms_id = ?",
            [(int) $row['sms_id']]
        );
        echo "Retry failed: " . $row['sms_id'] . PHP_EOL;
    }
}