<?php require __DIR__ . '/partials/header.php'; ?>
<?php if (!$currentUser): ?>
<section class="section container">
    <div class="message-panel">
        <h1>Access required</h1>
        <p>Please log in to view your dashboard.</p>
        <a class="btn btn--primary" href="index.php?page=login&amp;next=dashboard">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>
<?php
$activity = $repository->getCareerActivity((int) $currentUser['id']);
$alerts = $currentUser['role'] === 'student'
    ? $repository->getOpportunityAlerts((int) $currentUser['id'])
    : [];
$alertPreferences = $currentUser['role'] === 'student'
    ? $repository->getAlertPreferences((int) $currentUser['id'])
    : null;
$recommendations = $currentUser['role'] === 'student'
    ? $repository->getRecommendedOpportunities($currentUser)
    : [];
?>

<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Dashboard</span>
            <h1>Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?></h1>
        </div>
        <a href="index.php?page=profile" class="btn btn--primary">Edit profile</a>
    </div>

    <?php if ($currentUser['role'] === 'admin'): ?>
    <section class="admin-time-tools" data-admin-time-tools data-time-zone="Asia/Dhaka" aria-label="Administrator clock and calendar">
        <article class="admin-clock-card" aria-labelledby="admin-clock-title">
            <div class="admin-time-tools__eyebrow">AIUB ADMIN CONSOLE <span>·</span> DHAKA</div>
            <h2 id="admin-clock-title">Local system time</h2>
            <time class="admin-clock-card__time" data-admin-clock><?php echo date('H:i:s'); ?></time>
            <span class="admin-clock-card__zone">BANGLADESH STANDARD TIME <span>UTC +06:00</span></span>
        </article>
        <section class="admin-calendar" aria-labelledby="admin-calendar-title" data-admin-calendar>
            <div class="admin-calendar__header">
                <div>
                    <span class="admin-time-tools__eyebrow">DATE NAVIGATOR</span>
                    <h2 id="admin-calendar-title">Calendar</h2>
                </div>
                <div class="admin-calendar__controls">
                    <button type="button" data-calendar-prev aria-label="Previous month">&lsaquo;</button>
                    <button type="button" data-calendar-today>Today</button>
                    <button type="button" data-calendar-next aria-label="Next month">&rsaquo;</button>
                </div>
            </div>
            <div class="admin-calendar__month" data-calendar-month aria-live="polite"></div>
            <table class="admin-calendar__grid">
                <thead>
                    <tr>
                        <th scope="col">Mon</th><th scope="col">Tue</th><th scope="col">Wed</th>
                        <th scope="col">Thu</th><th scope="col">Fri</th><th scope="col">Sat</th><th scope="col">Sun</th>
                    </tr>
                </thead>
                <tbody data-calendar-days></tbody>
            </table>
            <p class="admin-calendar__selected" data-calendar-selected aria-live="polite"></p>
        </section>
    </section>
    <?php endif; ?>

    <?php if ($currentUser['role'] !== 'admin'): ?>
    <div class="stats-grid">
        <?php if ($currentUser['role'] === 'student'): ?>
        <div class="stat-card">
            <span>Tracked applications</span>
            <strong data-slot-counter><?php echo (int) $activity['applications']; ?></strong>
            <small>Private to your account</small>
        </div>
        <?php endif; ?>
        <div class="stat-card">
            <span>Mentorship requests</span>
            <strong data-slot-counter><?php echo (int) $activity['mentorship_requests']; ?></strong>
            <small><?php echo $currentUser['role'] === 'student' ? 'Requests you sent' : 'Requests you received'; ?></small>
        </div>
        <div class="stat-card">
            <span>Referral requests</span>
            <strong data-slot-counter><?php echo (int) $activity['referral_requests']; ?></strong>
            <small><?php echo $currentUser['role'] === 'student' ? 'Requests you sent' : 'Requests on your listings'; ?></small>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($currentUser['role'], ['student', 'alumni'], true)): ?>
    <section class="career-tools">
        <h2>Career support</h2>
        <div class="career-tool-grid">
            <?php if ($currentUser['role'] === 'student'): ?>
                <a class="career-tool-card" href="index.php?page=applications"><strong>Application tracker</strong><span>Save roles and update your private progress.</span></a>
                <a class="career-tool-card" href="index.php?page=mentorship"><strong>Alumni mentorship</strong><span>Find opt-in mentors and track your requests.</span></a>
                <a class="career-tool-card" href="index.php?page=referrals"><strong>Referral opportunities</strong><span>Request consideration for alumni-shared roles.</span></a>
                <a class="career-tool-card" href="index.php?page=profile#opportunity-alerts"><strong>Alert preferences</strong><span>Choose daily, weekly, or no in-app alerts.</span></a>
            <?php elseif ($currentUser['role'] === 'alumni'): ?>
                <a class="career-tool-card" href="index.php?page=mentorship"><strong>Mentorship requests</strong><span>Review and respond to student requests.</span></a>
                <a class="career-tool-card" href="index.php?page=referrals"><strong>Referral opportunities</strong><span>Share a role where you may consider referrals.</span></a>
                <a class="career-tool-card" href="index.php?page=profile#mentorship-availability"><strong>Mentorship availability</strong><span>Set your topics and opt-in preference.</span></a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($currentUser['role'] === 'student' && ($recommendations || $alertPreferences['frequency'] !== 'off')): ?>
    <div class="dashboard-grid">
        <?php if ($recommendations): ?>
        <div class="panel">
            <h3>Recommended opportunities</h3>
            <?php foreach ($recommendations as $opportunity): ?>
                <div class="profile-item">
                    <div>
                        <strong><?php echo htmlspecialchars($opportunity['title']); ?></strong>
                        <small><?php echo htmlspecialchars($opportunity['company']) . ' • ' . htmlspecialchars($opportunity['type']); ?></small>
                        <?php if (!$opportunity['application_available']): ?><small>Application destination unavailable</small><?php endif; ?>
                    </div>
                    <a href="index.php?page=opportunity-detail&id=<?php echo $opportunity['id']; ?>">View</a>
                </div>
            <?php endforeach; ?>
            <a class="btn btn--secondary" href="index.php?page=opportunities">Browse all opportunities</a>
        </div>
        <?php endif; ?>
        <?php if ($currentUser['role'] === 'student' && $alertPreferences['frequency'] !== 'off'): ?>
            <div class="panel">
                <h3>Opportunity alerts</h3>
                <p>Your in-app digest uses your saved department and keywords. No email is sent.</p>
                <?php if (!$alerts): ?>
                    <p>No matching opportunities were published during your selected alert period.</p>
                <?php else: ?>
                    <?php foreach ($alerts as $opportunity): ?>
                        <div class="profile-item">
                            <div><strong><?php echo htmlspecialchars($opportunity['title']); ?></strong><small><?php echo htmlspecialchars($opportunity['company'] . ' · ' . $opportunity['department']); ?></small></div>
                            <a href="index.php?page=opportunity-detail&amp;id=<?php echo (int) $opportunity['id']; ?>">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="index.php?page=profile#opportunity-alerts">Manage alert preferences</a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
