<?php
require __DIR__ . '/partials/header.php';
if (!$currentUser || $currentUser['role'] !== 'student'):
?>
<section class="section container">
    <div class="message-panel">
        <h1>Student access only</h1>
        <p>Sign in as a student to manage your private opportunity tracker.</p>
        <a class="btn btn--primary" href="index.php?page=login&amp;next=applications">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>
<?php
$applications = $repository->getApplications((int) $currentUser['id']);
$applicationMap = [];
foreach ($applications as $application) {
    $applicationMap[(int) $application['opportunity_id']] = $application;
}
$opportunities = array_values(array_filter(
    $repository->getOpportunities(),
    static fn (array $opportunity): bool => $opportunity['status'] === 'published' && $opportunity['deadline'] >= date('Y-m-d')
));
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
$applicationStatusCounts = array_fill_keys(['saved', 'applied', 'interview', 'offer', 'rejected'], 0);
foreach ($applications as $application) {
    $applicationStatusCounts[$application['status']] = ($applicationStatusCounts[$application['status']] ?? 0) + 1;
}
?>
<section class="section container">
    <div class="page-header">
        <div><span class="eyebrow">My career</span><h1>Opportunity application tracker</h1></div>
        <a href="index.php?page=dashboard" class="btn btn--secondary btn--outlined">Back to dashboard</a>
    </div>
    <p class="demo-note">This private tracker records progress you enter. It does not confirm that an application was submitted to an employer. Notes are visible only to you.</p>

    <section class="panel" id="tracked-opportunities">
        <div class="section-heading">
            <div>
                <h2>Your tracked opportunities</h2>
                <p><?php echo count($applications); ?> saved application<?php echo count($applications) === 1 ? '' : 's'; ?></p>
            </div>
        </div>
        <?php if ($applications): ?>
            <div class="application-filters" role="group" aria-label="Filter tracked opportunities">
                <button type="button" class="application-filter is-active" data-application-filter="all">All (<?php echo count($applications); ?>)</button>
                <?php foreach ($applicationStatusCounts as $status => $count): ?>
                    <?php if ($count > 0): ?>
                        <button type="button" class="application-filter" data-application-filter="<?php echo htmlspecialchars($status); ?>"><?php echo ucfirst($status); ?> (<?php echo $count; ?>)</button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!$applications): ?>
            <p>You have not saved any opportunities yet.</p>
        <?php else: ?>
            <div class="application-list">
                <?php foreach ($applications as $application): ?>
                    <article class="application-card" data-application-status="<?php echo htmlspecialchars($application['status']); ?>">
                        <div>
                            <span class="badge badge--soft"><?php echo htmlspecialchars($application['type']); ?></span>
                            <span class="badge application-status application-status--<?php echo htmlspecialchars($application['status']); ?>"><?php echo htmlspecialchars(ucfirst($application['status'])); ?></span>
                            <h3><?php echo htmlspecialchars($application['title']); ?></h3>
                            <p><?php echo htmlspecialchars($application['company'] . ' · ' . $application['department']); ?></p>
                            <p class="application-deadline">Deadline: <?php echo htmlspecialchars(date('M j, Y', strtotime($application['deadline']))); ?></p>
                            <?php if ($application['opportunity_status'] === 'published' && $application['deadline'] >= date('Y-m-d')): ?>
                                <a class="text-link" href="index.php?page=opportunity-detail&amp;id=<?php echo (int) $application['opportunity_id']; ?>">View opportunity</a>
                            <?php else: ?>
                                <span class="contact-note">This opportunity is closed or expired.</span>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="index.php" class="form-grid two-col">
                            <input type="hidden" name="action" value="save_application">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="opportunity_id" value="<?php echo (int) $application['opportunity_id']; ?>">
                            <label>Status
                                <select name="status" required>
                                    <?php foreach (['saved', 'applied', 'interview', 'offer', 'rejected'] as $status): ?>
                                        <option value="<?php echo $status; ?>"<?php echo $application['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Application date
                                <input type="date" name="application_date" value="<?php echo htmlspecialchars($application['application_date'] ?? ''); ?>">
                            </label>
                            <label class="full-width">Private note
                                <textarea name="private_note" rows="3" maxlength="4000"><?php echo htmlspecialchars($application['private_note'] ?? ''); ?></textarea>
                            </label>
                            <div class="form-actions form-actions--wide">
                                <button class="btn btn--primary" type="submit">Update tracker</button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <h2>Discover active opportunities</h2>
        <?php if (!$opportunities): ?>
            <p>No active opportunities are available to track.</p>
        <?php else: ?>
            <div class="listing-grid">
                <?php foreach ($opportunities as $opportunity): ?>
                    <article class="listing-card" id="opportunity-<?php echo (int) $opportunity['id']; ?>">
                        <div class="listing-card__meta">
                            <span class="badge badge--soft"><?php echo htmlspecialchars($opportunity['type']); ?></span>
                            <span>Deadline: <?php echo htmlspecialchars($opportunity['deadline']); ?></span>
                        </div>
                        <h3><?php echo htmlspecialchars($opportunity['title']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($opportunity['company']); ?></p>
                        <?php if (isset($applicationMap[(int) $opportunity['id']])): ?>
                            <a href="#tracked-opportunities" class="btn btn--secondary">Already in your tracker</a>
                        <?php else: ?>
                            <form method="post" action="index.php">
                                <input type="hidden" name="action" value="save_application">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="opportunity_id" value="<?php echo (int) $opportunity['id']; ?>">
                                <input type="hidden" name="status" value="saved">
                                <input type="hidden" name="application_date" value="">
                                <input type="hidden" name="private_note" value="">
                                <button class="btn btn--primary" type="submit">Save opportunity</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
