<?php
$loginDestinations = ['home', 'dashboard', 'profile', 'alumni', 'opportunities', 'companies', 'company-detail', 'mentorship', 'referrals', 'applications', 'notifications', 'admin'];
$loginNext = isset($_GET['next']) && is_string($_GET['next']) && in_array($_GET['next'], $loginDestinations, true)
    ? $_GET['next']
    : '';
require __DIR__ . '/partials/header.php';
?>
<section class="section container auth-page">
    <div class="auth-card">
        <div class="auth-card__intro">
            <span class="eyebrow">Welcome back</span>
            <h1>Login to your AIUB account</h1>
            <p>Access your profile, dashboard, and opportunities using your registered AIUB email.</p>
        </div>

        <form method="post" action="index.php" class="form-grid">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="next" value="<?php echo htmlspecialchars($loginNext); ?>">

            <label>
                Email address
                <input type="email" name="email" placeholder="you@aiub.edu" autocomplete="username" required>
            </label>

            <label>
                Password
                <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
            </label>

            <button type="submit" class="btn btn--primary btn--full">Login</button>
        </form>

        <div class="auth-switch">
            <span>Need an account?</span>
            <a href="index.php?page=register">Register here</a>
        </div>

    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
