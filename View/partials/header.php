<?php
$currentUser = $currentUser ?? null;
$flashMessage = $_SESSION['flashMessage'] ?? null;
$flashSuccess = $_SESSION['flashSuccess'] ?? true;
$flashOverlay = $_SESSION['flashOverlay'] ?? false;
$flashLoginConfirmation = $_SESSION['flashLoginConfirmation'] ?? false;
unset($_SESSION['flashMessage'], $_SESSION['flashSuccess'], $_SESSION['flashOverlay'], $_SESSION['flashLoginConfirmation']);
$routePage = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'home';
$routeTitles = [
    'home' => 'Home',
    'register' => 'Create account',
    'login' => 'Log in',
    'dashboard' => 'Dashboard',
    'profile' => 'Profile',
    'alumni' => 'Alumni directory',
    'opportunities' => 'Jobs and internships',
    'opportunity-detail' => 'Opportunity details',
    'admin' => 'Administration',
    'companies' => 'Company directory',
    'company-detail' => 'Company details',
    'mentorship' => 'Mentorship',
    'referrals' => 'Referral opportunities',
    'applications' => 'Application tracker',
    'notifications' => 'Notifications',
    'change-password' => 'Change password',
];
$documentTitle = ($routeTitles[$routePage] ?? 'Page') . ' | AIUB Career Connect';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($documentTitle); ?></title>
    <link rel="icon" type="image/png" href="Assets/aiub-favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Design/Style.css?v=<?php echo (int) filemtime(__DIR__ . '/../../Design/Style.css'); ?>">
</head>
<body data-user-role="<?php echo htmlspecialchars($currentUser['role'] ?? 'guest'); ?>">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="page-shell">
        <header class="site-header">
            <div class="utility-bar">
                <div class="container utility-bar__row">
                    <a class="utility-bar__identity" href="index.php?page=home">AIUB Career Connect</a>
                    <nav class="utility-links" aria-label="Utility links">
                        <a class="utility-search" href="index.php?page=opportunities" aria-label="Search opportunities">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"></circle><path d="m15.5 15.5 4.2 4.2"></path></svg>
                            <span>Job Search</span>
                        </a>
                        <a href="https://www.aiub.edu/" target="_blank" rel="noopener noreferrer">AIUB Website</a>
                        <?php if ($currentUser): ?>
                            <?php $headerUnreadCount = $repository->getUnreadNotificationCount((int) $currentUser['id']); ?>
                            <a href="index.php?page=notifications">Notifications<?php if ($headerUnreadCount > 0): ?> (<?php echo $headerUnreadCount; ?>)<?php endif; ?></a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>

            <div class="primary-navigation" data-primary-navigation>
                <div class="container nav-layout">
                    <nav class="main-nav main-nav--left" aria-label="Main navigation">
                        <a href="index.php?page=home"<?php echo $routePage === 'home' ? ' aria-current="page"' : ''; ?>>Home</a>
                        <a href="index.php?page=opportunities"<?php echo in_array($routePage, ['opportunities', 'opportunity-detail'], true) ? ' aria-current="page"' : ''; ?>>Jobs &amp; Internships</a>
                        <a href="index.php?page=alumni"<?php echo $routePage === 'alumni' ? ' aria-current="page"' : ''; ?>>Alumni Directory</a>
                    </nav>

                    <a href="index.php?page=home" class="nav-brand" aria-label="AIUB Career Connect home">
                        <img src="Assets/aiub-seal-small.jpg" alt="" width="192" height="192">
                        <span class="sr-only">AIUB Career Connect</span>
                    </a>

                    <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="site-navigation">
                        <span></span><span></span><span></span>
                    </button>

                    <nav class="main-nav main-nav--right" id="site-navigation" aria-label="Account and career navigation">
                        <a href="index.php?page=companies"<?php echo in_array($routePage, ['companies', 'company-detail'], true) ? ' aria-current="page"' : ''; ?>>Companies</a>
                        <?php if ($currentUser && in_array($currentUser['role'], ['student', 'alumni'], true)): ?>
                            <div class="nav-menu">
                                <button class="nav-menu__trigger" type="button" aria-expanded="false" aria-haspopup="true">
                                    Career Support <span class="nav-menu__chevron" aria-hidden="true"></span>
                                </button>
                                <div class="nav-dropdown" hidden>
                                    <?php if ($currentUser['role'] === 'student'): ?>
                                        <a href="index.php?page=applications"<?php echo $routePage === 'applications' ? ' aria-current="page"' : ''; ?>>Application Tracker</a>
                                    <?php endif; ?>
                                    <a href="index.php?page=mentorship"<?php echo $routePage === 'mentorship' ? ' aria-current="page"' : ''; ?>>Mentorship</a>
                                    <a href="index.php?page=referrals"<?php echo $routePage === 'referrals' ? ' aria-current="page"' : ''; ?>>Referral Opportunities</a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                            <a href="index.php?page=admin"<?php echo $routePage === 'admin' ? ' aria-current="page"' : ''; ?>>Administration</a>
                        <?php endif; ?>
                        <?php if ($currentUser): ?>
                            <div class="nav-menu">
                                <button class="nav-menu__trigger" type="button" aria-expanded="false" aria-haspopup="true">
                                    My Account <span class="nav-menu__chevron" aria-hidden="true"></span>
                                </button>
                                <div class="nav-dropdown" hidden>
                                    <span class="nav-dropdown__greeting"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                                    <a href="index.php?page=dashboard"<?php echo $routePage === 'dashboard' ? ' aria-current="page"' : ''; ?>>Dashboard</a>
                                    <a href="index.php?page=profile"<?php echo $routePage === 'profile' ? ' aria-current="page"' : ''; ?>>Profile</a>
                                    <a href="index.php?page=change-password"<?php echo $routePage === 'change-password' ? ' aria-current="page"' : ''; ?>>Change password</a>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="action" value="logout">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <button type="submit">Logout</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="index.php?page=login"<?php echo $routePage === 'login' ? ' aria-current="page"' : ''; ?>>Login</a>
                            <a href="index.php?page=register"<?php echo $routePage === 'register' ? ' aria-current="page"' : ''; ?>>Register</a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </header>

        <?php if ($flashMessage): ?>
            <div class="flash-message <?php echo $flashSuccess ? 'flash-success' : 'flash-error'; ?><?php echo $flashOverlay ? ' flash-message--overlay' : ''; ?><?php echo $flashLoginConfirmation ? ' flash-message--login-confirmation' : ''; ?>" role="<?php echo $flashSuccess ? 'status' : 'alert'; ?>">
                <?php echo htmlspecialchars($flashMessage); ?>
            </div>
        <?php endif; ?>

        <main class="page-content<?php echo $routePage === 'home' ? ' page-content--home' : ''; ?>" id="main-content">
