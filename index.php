<?php
require __DIR__ . '/config.php';

$selectedMonth = $_GET['m'] ?? date('Y-m');
$nextSchedule = getNextSchedule();
$upcoming = getUpcomingSchedules();

$calendarRows = query(
    "SELECT * FROM schedules WHERE status = 'Active' AND DATE_FORMAT(collection_date, '%Y-%m') = ? ORDER BY collection_date ASC, start_time ASC",
    [$selectedMonth]
);

$calendarMap = [];
foreach ($calendarRows as $row) {
    $calendarMap[$row['collection_date']][] = $row;
}

$activeCount = getActiveScheduleCount();

generatePageHeader('Waste Collection Schedule', false, 'live-page');
?>

<section class="hero">
    <div class="hero-copy">
        <span class="pill">✦ Welcome to Barangay Talusan</span>
        <h1>Stay ahead of every <span class="accent">waste collection</span> <em>in Talusan</em></h1>
        <p class="hero-sub">Check the schedule anytime and get an SMS the moment it changes — no smartphone needed.</p>
        <div class="hero-actions">
            <a href="#schedule" class="primary-btn glow">View Schedule ✦</a>
            <a href="#upcoming" class="ghost-btn">Upcoming collections</a>
        </div>
    </div>

    <div class="hero-visual">
        <div class="device">
            <img src="logo.jpg" alt="Barangay Talusan church">
        </div>
    </div>

    <div class="hero-stats">
        <div class="stat-item">
            <strong><?= h($activeCount) ?></strong>
            <span>Upcoming collections</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <strong><?= $nextSchedule ? h(date('M j', strtotime($nextSchedule['collection_date']))) : '—' ?></strong>
            <span>Next pickup</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <strong>SMS</strong>
            <span>Alerts on every change</span>
        </div>
    </div>
</section>

<div id="schedule"></div>
<?php if ($nextSchedule): ?>
    <section class="next-card">
        <div class="next-header">
            <span class="icon-badge">🚚</span>
            <strong>Next Waste Collection</strong>
            <span class="status-badge status-active">Active</span>
        </div>

        <div class="next-body">
            <div class="date-box">
                <small><?= h(date('D', strtotime($nextSchedule['collection_date']))) ?></small>
                <strong><?= h(date('d', strtotime($nextSchedule['collection_date']))) ?></strong>
                <small><?= h(date('M Y', strtotime($nextSchedule['collection_date']))) ?></small>
            </div>

            <div class="schedule-text">
                <h2><?= h(date('l, F j', strtotime($nextSchedule['collection_date']))) ?></h2>
                <p><strong>Time:</strong> <?= h($nextSchedule['start_time']) ?> – <?= h($nextSchedule['end_time']) ?></p>
                <p><strong>Area:</strong> <?= h($nextSchedule['coverage_area'] ?: 'All sitios') ?></p>
                <p><?= h($nextSchedule['notes']) ?></p>
            </div>
        </div>
    </section>
<?php endif; ?>

<div class="sms-notice">
    <span class="icon-badge blue">💬</span>
    <div>
        <strong>You will also receive an SMS when the schedule changes.</strong><br>
        No smartphone? You'll still get a text.
    </div>
</div>

<section class="upcoming-section" id="upcoming">
    <div class="section-head">
        <div>
            <p class="eyebrow">UPCOMING</p>
            <h2>Upcoming Collections</h2>
        </div>

        <div class="view-toggle">
            <button type="button" class="toggle-btn active" data-view="list">List</button>
            <button type="button" class="toggle-btn" data-view="calendar">Calendar</button>
        </div>
    </div>

    <div id="list-view">
        <?php if ($upcoming): ?>
            <?php foreach ($upcoming as $row): ?>
                <article class="schedule-row">
                    <div class="date-pill">
                        <small><?= h(date('M', strtotime($row['collection_date']))) ?></small>
                        <strong><?= h(date('d', strtotime($row['collection_date']))) ?></strong>
                    </div>

                    <div class="info">
                        <strong><?= h(date('l, F j', strtotime($row['collection_date']))) ?></strong>
                        <span><?= h($row['start_time']) ?> – <?= h($row['end_time']) ?></span>
                        <small><?= h($row['coverage_area'] ?: 'All sitios') ?></small>
                    </div>

                    <span class="status-badge status-active">Active</span>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-message">No collections scheduled yet.</div>
        <?php endif; ?>
    </div>

    <div id="calendar-view" class="hidden">
        <?php
        $monthTs = strtotime($selectedMonth . '-01');
        $prevMonth = date('Y-m', strtotime('-1 month', $monthTs));
        $nextMonth = date('Y-m', strtotime('+1 month', $monthTs));
        ?>

        <div class="calendar-nav">
            <a href="?m=<?= h($prevMonth) ?>" class="secondary-btn small">Prev</a>
            <strong><?= h(date('F Y', $monthTs)) ?></strong>
            <a href="?m=<?= h($nextMonth) ?>" class="secondary-btn small">Next</a>
        </div>

        <div class="calendar-grid">
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                <div class="day-name"><?= h($day) ?></div>
            <?php endforeach; ?>

            <?php
            $firstDay = date('w', $monthTs);

            for ($i = 0; $i < $firstDay; $i++) {
                echo '<div class="calendar-cell empty"></div>';
            }

            $daysInMonth = date('t', $monthTs);

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateKey = date('Y-m-d', strtotime($selectedMonth . '-' . sprintf('%02d', $day)));

                echo '<div class="calendar-cell' . ($dateKey === date('Y-m-d') ? ' today' : '') . '">';
                echo '<span class="day-number">' . $day . '</span>';

                if (!empty($calendarMap[$dateKey])) {
                    foreach ($calendarMap[$dateKey] as $item) {
                        echo '<span class="calendar-event">' . h($item['start_time']) . '</span>';
                    }
                }

                echo '</div>';
            }
            ?>
        </div>
    </div>
</section>

<?php generatePageFooter(false); ?>