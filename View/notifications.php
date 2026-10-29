<?php
require __DIR__ . '/partials/header.php';
if (!$currentUser):
?>
<section class="section container">
    <div class="message-panel">
        <h1>Sign in required</h1>
        <p>Sign in to view your notifications.</p>
        <a class="btn btn--primary" href="index.php?page=login">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>
<?php
$notifications = $repository->getNotifications((int) $currentUser['id']);
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Updates</span>
            <h1>Notifications</h1>
        </div>
        <a class="btn btn--secondary btn--outlined" href="index.php?page=dashboard">Back to dashboard</a>
    </div>
    <?php if ($repository->getUnreadNotificationCount((int) $currentUser['id']) > 0): ?>
        <form method="post" action="index.php" class="form-actions">
            <input type="hidden" name="action" value="mark_notifications_read">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <button class="btn btn--secondary btn--outlined" type="submit">Mark all as read</button>
        </form>
    <?php endif; ?>
    <?php if (!$notifications): ?>
        <div class="message-panel"><h2>You’re all caught up</h2><p>Important account and career-support updates will appear here.</p></div>
    <?php else: ?>
        <div class="profile-list">
            <?php foreach ($notifications as $notification): ?>
                <article class="profile-item">
                    <div>
                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <small><?php echo htmlspecialchars($notification['created_at']); ?><?php if (!$notification['read_at']): ?> · New<?php endif; ?></small>
                    </div>
                    <?php if ($notification['link']): ?>
                        <a class="btn btn--secondary btn--outlined" href="<?php echo htmlspecialchars($notification['link']); ?>">Open</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
