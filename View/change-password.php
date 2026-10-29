<?php require __DIR__ . '/partials/header.php'; ?>
<section class="section container auth-page">
    <div class="auth-card">
        <div class="auth-card__intro">
            <span class="eyebrow">Account security</span>
            <h1>Change your password</h1>
            <p><?php echo !empty($currentUser['must_change_password']) ? 'An administrator issued a temporary password. Set a new password to continue.' : 'Choose a new password for your account.'; ?></p>
        </div>
        <?php if (!$currentUser): ?>
            <p>Sign in before changing your password.</p>
            <a class="btn btn--primary" href="index.php?page=login">Login</a>
        <?php else: ?>
            <form method="post" action="index.php" class="form-grid">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
                <label>New password<input type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
                <label>Confirm new password<input type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
                <button class="btn btn--primary" type="submit">Save new password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
