<?php
$daysLeft = max(0, (int) floor((strtotime($opportunity['deadline']) - strtotime(date('Y-m-d'))) / 86400));
?>
<article class="listing-card opportunity-card">
    <div class="listing-card__meta">
        <span class="badge badge--soft"><?php echo htmlspecialchars($opportunity['type']); ?></span>
        <span><?php echo htmlspecialchars($opportunity['remote'] . ' · ' . $opportunity['location']); ?></span>
    </div>
    <h2><?php echo htmlspecialchars($opportunity['title']); ?></h2>
    <p class="company-name"><?php echo htmlspecialchars($opportunity['company']); ?></p>
    <p class="opportunity-card__description"><?php echo htmlspecialchars($opportunity['description']); ?></p>
    <div class="listing-card__foot">
        <span class="deadline-badge<?php echo $daysLeft <= 3 ? ' deadline-badge--soon' : ''; ?>">
            <?php echo $daysLeft === 0 ? 'Last day to apply' : $daysLeft . ' ' . ($daysLeft === 1 ? 'day' : 'days') . ' left'; ?>
            <small>Deadline: <?php echo htmlspecialchars(date('M j, Y', strtotime($opportunity['deadline']))); ?></small>
        </span>
        <div class="card-actions">
            <a href="index.php?page=opportunity-detail&amp;id=<?php echo (int) $opportunity['id']; ?>" class="btn btn--secondary btn--outlined">View details</a>
            <?php if ($currentUser && $currentUser['role'] === 'student'): ?>
                <form method="post" action="index.php">
                    <input type="hidden" name="action" value="save_application">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="opportunity_id" value="<?php echo (int) $opportunity['id']; ?>">
                    <input type="hidden" name="status" value="saved">
                    <input type="hidden" name="application_date" value="">
                    <input type="hidden" name="private_note" value="">
                    <button type="submit" class="btn btn--primary">Save to tracker</button>
                </form>
            <?php elseif (!$currentUser): ?>
                <a href="index.php?page=login&amp;next=opportunities" class="btn btn--ghost">Login to save</a>
            <?php endif; ?>
        </div>
        <?php if (!$opportunity['application_available']): ?>
            <p class="contact-note" role="status">Employer application destination unavailable; this listing has no valid external application link.</p>
        <?php endif; ?>
    </div>
</article>
