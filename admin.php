<?php
require __DIR__ . '/config.php';

requireAdmin();

$page = $_GET['p'] ?? 'dashboard';
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_schedule') {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        $date = trim($_POST['collection_date'] ?? '');
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $coverageArea = trim($_POST['coverage_area'] ?? '');
        $status = $_POST['status'] ?? 'Draft';

        if ($date === '' || $start === '' || $end === '' || $notes === '') {
            flash('Please complete all required fields before saving.');
            header('Location: admin.php?p=schedules' . ($scheduleId ? '&schedule_id=' . $scheduleId : ''));
            exit;
        }

        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            flash('Past dates are not allowed. Please choose today or a future date.');
            header('Location: admin.php?p=schedules');
            exit;
        }

        if ($start >= $end) {
            flash('End time must be later than the start time.');
            header('Location: admin.php?p=schedules');
            exit;
        }

        $status = in_array($status, ['Active', 'Draft'], true) ? $status : 'Draft';

        if ($scheduleId > 0) {
            query(
                "UPDATE schedules SET collection_date=?, start_time=?, end_time=?, notes=?, coverage_area=?, status=? WHERE schedule_id=?",
                [$date, $start, $end, $notes, $coverageArea, $status, $scheduleId]
            );
            logScheduleAction($scheduleId, 'updated', 'Schedule updated.');
            flash('Schedule updated.');
        } else {
            $scheduleId = query(
                "INSERT INTO schedules (collection_date, start_time, end_time, notes, coverage_area, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$date, $start, $end, $notes, $coverageArea, $status, currentAdmin()['admin_id']],
                true
            );
            logScheduleAction((int) $scheduleId, 'created', 'Schedule created.');
            flash('Schedule created.');
        }

        if ($status === 'Active') {
            $gateway = new MockSmsGateway(SMS_FAIL_RATE);
            $sms = new SmsNotification($gateway);

            $recipients = $sms->compileRecipients();
            $message = "Barangay Talusan: Waste collection on " . date('F j', strtotime($date)) . " from " . $start . " to " . $end . ". " . $notes;

            $sms->sendBatch($recipients, $message, (int) $scheduleId, null);
            logScheduleAction((int) $scheduleId, 'published', 'Published and SMS sent to residents.');
            flash('Schedule published and SMS sent to residents.');
        }

        header('Location: admin.php?p=schedules');
        exit;
    }

    if ($action === 'delete_schedule') {
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);

        if ($scheduleId > 0) {
            $existing = fetchOne("SELECT * FROM schedules WHERE schedule_id = ?", [$scheduleId]);

            if ($existing) {
                try {
                    query("DELETE FROM sms_logs WHERE schedule_id = ?", [$scheduleId]);
                    query("DELETE FROM schedule_logs WHERE schedule_id = ?", [$scheduleId]);
                    query("DELETE FROM schedules WHERE schedule_id = ?", [$scheduleId]);

                    flash('Schedule deleted.');
                } catch (Throwable $e) {
                    flash('Unable to delete schedule.');
                }
            }
        }

        header('Location: admin.php?p=schedules');
        exit;
    }

    if ($action === 'save_resident') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile_number'] ?? '');
        $sitio = trim($_POST['sitio'] ?? '');

        $mobileNormalized = normalizePhilippineMobile($mobile);

        if ($name === '') {
            flash('Resident name is required.');
            header('Location: admin.php?p=residents');
            exit;
        }

        if (!isValidPhilippineMobile($mobile)) {
            flash('Mobile number must be in the format 09XX XXX XXXX.');
            header('Location: admin.php?p=residents');
            exit;
        }

        if ($sitio === '') {
            flash('Sitio is required.');
            header('Location: admin.php?p=residents');
            exit;
        }

        if (residentNameExists($name, $residentId)) {
            flash('This resident name already exists.');
            header('Location: admin.php?p=residents');
            exit;
        }

        $existing = fetchOne(
            "SELECT resident_id FROM residents WHERE mobile_number = ? AND resident_id != ? AND is_deleted = 0",
            [$mobileNormalized, $residentId]
        );

        if ($existing) {
            flash('This mobile number is already registered to another resident.');
            header('Location: admin.php?p=residents');
            exit;
        }

        if ($residentId > 0) {
            query(
                "UPDATE residents SET name=?, mobile_number=?, sitio=? WHERE resident_id=?",
                [$name, $mobileNormalized, $sitio, $residentId]
            );
            flash('Resident updated.');
        } else {
            query(
                "INSERT INTO residents (name, mobile_number, sitio, is_deleted, deleted_at) VALUES (?, ?, ?, 0, NULL)",
                [$name, $mobileNormalized, $sitio]
            );
            flash('Resident added.');
        }

        header('Location: admin.php?p=residents');
        exit;
    }

    if ($action === 'archive_resident') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);

        if ($residentId > 0) {
            query(
                "UPDATE residents SET is_deleted = 1, deleted_at = NOW() WHERE resident_id = ?",
                [$residentId]
            );
            flash('Resident archived.');
        }

        header('Location: admin.php?p=residents');
        exit;
    }

    if ($action === 'restore_resident') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);

        if ($residentId > 0) {
            query(
                "UPDATE residents SET is_deleted = 0, deleted_at = NULL WHERE resident_id = ?",
                [$residentId]
            );
            flash('Resident restored.');
        }

        header('Location: admin.php?p=deleted_residents');
        exit;
    }

    if ($action === 'retry_failed_sms') {
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
            } else {
                query(
                    "UPDATE sms_logs SET attempts = attempts + 1 WHERE sms_id = ?",
                    [(int) $row['sms_id']]
                );
            }
        }

        flash('Retry complete.');
        header('Location: admin.php?p=sms');
        exit;
    }
}

generatePageHeader('Admin Dashboard', true);

switch ($page) {
    case 'dashboard':
        $failedCount = getFailedSmsCount();
        $residentCount = getResidentCount();
        $activeCount = getActiveScheduleCount();

        $nextPickups = query(
            "SELECT * FROM schedules WHERE status = 'Active' AND collection_date >= CURDATE() ORDER BY collection_date ASC, start_time ASC LIMIT 5"
        );
        ?>
        <section class="page-overview">
            <p class="eyebrow">Dashboard</p>
            <h1>Hello, <?= h(currentAdmin()['full_name']) ?></h1>
            <p class="muted">The Barangay Secretary manages the collection schedule and SMS notifications in one place.</p>
        </section>

        <div class="stat-grid">
            <div class="stat-card">
                <span>Upcoming collections</span>
                <strong><?= h($activeCount) ?></strong>
            </div>

            <div class="stat-card">
                <span>Residents</span>
                <strong><?= h($residentCount) ?></strong>
            </div>

            <div class="stat-card danger <?= $failedCount ? 'is-alert' : '' ?>">
                <span>Failed SMS</span>
                <strong><?= h($failedCount) ?></strong>
            </div>
        </div>

        <div class="action-row">
            <a href="admin.php?p=schedules" class="primary-btn">Manage Schedule</a>
            <a href="admin.php?p=residents" class="secondary-btn">Residents</a>
        </div>

        <?php if ($failedCount > 0): ?>
            <div class="alert-box">Admin alert: <?= h($failedCount) ?> SMS message(s) failed and are queued for retry.</div>
        <?php endif; ?>

        <div class="panel">
            <h2>Next pickups</h2>

            <?php if ($nextPickups): ?>
                <?php foreach ($nextPickups as $row): ?>
                    <div class="list-row">
                        <div>
                            <strong><?= h(date('F j, Y', strtotime($row['collection_date']))) ?></strong>
                            <small><?= h($row['start_time']) ?> – <?= h($row['end_time']) ?></small>
                        </div>
                        <span class="status-badge <?= $row['status'] === 'Done' ? 'success-pill' : ($row['status'] === 'Active' ? 'status-active' : 'status-draft') ?>">
                            <?= h($row['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">No upcoming collections at the moment.</p>
            <?php endif; ?>
        </div>
        <?php
        break;

    case 'schedules':
        $rows = query(
            "SELECT * FROM schedules WHERE collection_date LIKE ? OR notes LIKE ? OR coverage_area LIKE ? OR start_time LIKE ? OR end_time LIKE ? ORDER BY collection_date DESC",
            ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]
        );
        ?>
        <div class="page-head">
            <h1>Manage Schedule</h1>
            <a href="admin.php?p=schedule_form" class="primary-btn">Add New Schedule</a>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="p" value="schedules">
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search schedules">
        </form>

        <div class="panel">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Notes</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h(date('M j, Y', strtotime($row['collection_date']))) ?></td>
                            <td><?= h($row['start_time']) ?> – <?= h($row['end_time']) ?></td>
                            <td><?= h($row['notes']) ?></td>
                            <td><?= h($row['coverage_area'] ?: 'All sitios') ?></td>
                            <td><span class="status-badge <?= $row['status'] === 'Active' ? 'status-active' : 'status-draft' ?>"><?= h($row['status']) ?></span></td>
                            <td>
                                <a href="admin.php?p=schedule_form&schedule_id=<?= (int) $row['schedule_id'] ?>" class="secondary-btn small">Edit</a>

                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete_schedule">
                                    <input type="hidden" name="schedule_id" value="<?= (int) $row['schedule_id'] ?>">
                                    <button type="submit" class="danger-btn small" onclick="return confirm('Delete this schedule?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    case 'schedule_form':
        $scheduleId = (int) ($_GET['schedule_id'] ?? 0);
        $schedule = $scheduleId ? fetchOne("SELECT * FROM schedules WHERE schedule_id = ?", [$scheduleId]) : null;
        ?>
        <div class="page-head">
            <h1><?= $schedule ? 'Edit Schedule' : 'Add New Schedule' ?></h1>
        </div>

        <form method="post" class="panel form-panel">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="save_schedule">
            <input type="hidden" name="schedule_id" value="<?= h((string) ($schedule['schedule_id'] ?? 0)) ?>">

            <label>
                Collection date
                <input type="date" name="collection_date" value="<?= h($schedule['collection_date'] ?? '') ?>" required>
            </label>

            <div class="two-col">
                <label>
                    Start time
                    <input type="time" name="start_time" value="<?= h($schedule['start_time'] ?? '06:00') ?>" required>
                </label>

                <label>
                    End time
                    <input type="time" name="end_time" value="<?= h($schedule['end_time'] ?? '10:00') ?>" required>
                </label>
            </div>

            <label>
                Coverage area
                <input type="text" name="coverage_area" value="<?= h($schedule['coverage_area'] ?? '') ?>" placeholder="All sitios / Sitio Centro / ...">
            </label>

            <label>
                Notes
                <textarea name="notes" rows="4" required><?= h($schedule['notes'] ?? '') ?></textarea>
            </label>

            <label>
                Status
                <select name="status">
                    <option value="Draft" <?= (($schedule['status'] ?? 'Draft') === 'Draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="Active" <?= (($schedule['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
                </select>
            </label>

            <div class="action-row">
                <button type="submit" class="primary-btn">Save</button>
                <a href="admin.php?p=schedules" class="secondary-btn">Cancel</a>
            </div>
        </form>
        <?php
        break;

    case 'schedule_logs':
        $rows = query(
            "SELECT sl.*, s.collection_date FROM schedule_logs sl LEFT JOIN schedules s ON s.schedule_id = sl.schedule_id WHERE sl.action LIKE ? OR sl.details LIKE ? ORDER BY sl.logged_at DESC",
            ["%$search%", "%$search%"]
        );
        ?>
        <div class="page-head">
            <h1>Schedule Logs</h1>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="p" value="schedule_logs">
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search logs">
        </form>

        <div class="panel">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Logged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row['collection_date'] ?? '-') ?></td>
                            <td><?= h($row['action']) ?></td>
                            <td><?= h($row['details']) ?></td>
                            <td><?= h($row['logged_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    case 'sms':
        $rows = query(
            "SELECT sl.*, r.name AS resident_name, r.mobile_number FROM sms_logs sl LEFT JOIN residents r ON r.resident_id = sl.resident_id WHERE sl.message LIKE ? OR r.name LIKE ? OR r.mobile_number LIKE ? ORDER BY sl.sent_at DESC LIMIT 200",
            ["%$search%", "%$search%", "%$search%"]
        );
        $failedCount = getFailedSmsCount();
        ?>
        <div class="page-head">
            <h1>SMS Delivery Log</h1>
            <?php if ($failedCount > 0): ?>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="retry_failed_sms">
                    <button type="submit" class="primary-btn">Retry Failed SMS</button>
                </form>
            <?php endif; ?>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="p" value="sms">
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search SMS logs">
        </form>

        <?php if ($failedCount === 0): ?>
            <div class="success-box">All messages delivered.</div>
        <?php endif; ?>

        <div class="panel">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Resident</th>
                        <th>Mobile</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row['sent_at'] ?: '-') ?></td>
                            <td><?= h($row['resident_name'] ?? '-') ?></td>
                            <td><?= h(formatDisplayMobile($row['mobile_number'] ?? '')) ?></td>
                            <td><span class="status-badge <?= strtolower($row['status'] ?? 'queued') === 'sent' ? 'status-active' : 'status-draft' ?>"><?= h(strtoupper($row['status'] ?? 'queued')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    case 'residents':
        $rows = query(
            "SELECT * FROM residents WHERE is_deleted = 0 AND (name LIKE ? OR mobile_number LIKE ? OR sitio LIKE ?) ORDER BY name ASC",
            ["%$search%", "%$search%", "%$search%"]
        );
        ?>
        <div class="page-head">
            <h1>Residents</h1>
            <button type="button" class="primary-btn" data-open-modal="resident-modal">Add Resident</button>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="p" value="residents">
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search residents">
        </form>

        <div class="panel">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Sitio</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h(formatDisplayMobile($row['mobile_number'])) ?></td>
                            <td><?= h($row['sitio']) ?></td>
                            <td>
                                <button type="button" class="secondary-btn small" data-edit-resident='<?= h(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>Edit</button>

                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="archive_resident">
                                    <input type="hidden" name="resident_id" value="<?= (int) $row['resident_id'] ?>">
                                    <button type="submit" class="danger-btn small" onclick="return confirm('Remove this resident?')">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="resident-modal" class="modal hidden">
            <div class="modal-card">
                <h2 id="resident-modal-title">Add Resident</h2>
                <form method="post" id="resident-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="save_resident">
                    <input type="hidden" name="resident_id" id="resident_id">

                    <label>
                        Full Name
                        <input type="text" name="name" id="resident_name" required>
                    </label>

                    <label>
                        Mobile Number
                        <input type="tel" name="mobile_number" id="resident_mobile" required placeholder="09XX XXX XXXX">
                    </label>

                    <label>
                        Sitio
                        <input type="text" name="sitio" id="resident_sitio" required>
                    </label>

                    <div class="action-row">
                        <button type="submit" class="primary-btn">Save</button>
                        <button type="button" class="secondary-btn" data-close-modal="resident-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        break;

    case 'deleted_residents':
        $rows = query(
            "SELECT * FROM residents WHERE is_deleted = 1 AND (name LIKE ? OR mobile_number LIKE ? OR sitio LIKE ?) ORDER BY deleted_at DESC",
            ["%$search%", "%$search%", "%$search%"]
        );
        ?>
        <div class="page-head">
            <h1>Deleted Residents</h1>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="p" value="deleted_residents">
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search deleted residents">
        </form>

        <div class="panel">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Sitio</th>
                        <th>Deleted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h(formatDisplayMobile($row['mobile_number'])) ?></td>
                            <td><?= h($row['sitio']) ?></td>
                            <td><?= h($row['deleted_at']) ?></td>
                            <td>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="restore_resident">
                                    <input type="hidden" name="resident_id" value="<?= (int) $row['resident_id'] ?>">
                                    <button type="submit" class="secondary-btn small">Restore</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        break;

    default:
        echo '<h1>Page not found</h1>';
        break;
}

generatePageFooter(true);
?>