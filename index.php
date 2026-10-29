<?php
date_default_timezone_set('Asia/Dhaka');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();
ob_start();
set_exception_handler(static function (Throwable $error): void {
    error_log('AIUB Career Connect encountered an uncaught ' . get_class($error) . '.');
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Request error | AIUB Career Connect</title><body><main><h1>Unable to complete this request</h1><p>An unexpected error occurred. Check the server logs and try again.</p><p><a href="index.php">Return home</a></p></main></body></html>';
});

require __DIR__ . '/Model/DataRepository.php';
require __DIR__ . '/Model/Database.php';
require __DIR__ . '/Controller/AuthController.php';
require __DIR__ . '/Controller/OpportunityController.php';

try {
    $database = new Database();
    $repository = new DataRepository($database->getConnection());
} catch (RuntimeException $error) {
    error_log('AIUB Career Connect cannot start because the configured MySQL database is unavailable.');
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database unavailable | AIUB Career Connect</title><body><main><h1>Database unavailable</h1><p>AIUB Career Connect requires MySQL. Start MySQL in XAMPP, import the database schema, and check DB_HOST, DB_NAME, DB_USER, and DB_PASS.</p></main></body></html>';
    exit;
}
$auth = new AuthController($repository);
$opportunityController = new OpportunityController($repository);
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$postString = static function (string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
};
$postInt = static function (string $key) use ($postString): int {
    $value = filter_var($postString($key), FILTER_VALIDATE_INT);
    return $value === false ? 0 : $value;
};

try {
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrfActions = [
        'login',
        'register',
        'save_profile',
        'logout',
        'save_mentorship_profile',
        'submit_mentorship_request',
        'update_mentorship_request',
        'create_referral_opportunity',
        'submit_referral_request',
        'update_referral_request',
        'update_referral_opportunity_status',
        'save_application',
        'save_alert_preferences',
        'review_alumni',
        'save_profile_privacy',
        'save_company',
        'merge_companies',
        'update_opportunity_status',
        'submit_profile_report',
        'review_profile_report',
        'save_opportunity',
        'create_department',
        'manage_user',
        'reset_user_password',
        'change_password',
        'mark_notifications_read',
    ];
    $submittedToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';
    if (in_array($action, $csrfActions, true)
        && !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid request token. Please reload the page and try again.');
    }

    $currentUser = $auth->currentUser();
    if ($currentUser && !empty($currentUser['must_change_password'])
        && !in_array($action, ['change_password', 'logout'], true)) {
        $_SESSION['flashMessage'] = 'Change your temporary password before continuing.';
        $_SESSION['flashSuccess'] = false;
        header('Location: index.php?page=change-password');
        exit;
    }

    if ($action === 'save_opportunity') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $success = $repository->saveOpportunity($_POST, (int) $currentUser['id']);
        $_SESSION['flashMessage'] = $success
            ? 'Opportunity details saved.'
            : 'Opportunity details could not be saved. Check all required fields, the application URL, and the deadline.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#opportunity-management');
        exit;
    }

    if ($action === 'create_department') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $success = $repository->createDepartment($postString('name'), (int) $currentUser['id']);
        $_SESSION['flashMessage'] = $success
            ? 'Department category created.'
            : 'Department category could not be created. It may already exist or exceed 100 characters.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#department-management');
        exit;
    }

    if ($action === 'manage_user') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $status = $postString('status');
        $success = $status === 'remove'
            ? $repository->deleteManagedUser($postInt('user_id'), (int) $currentUser['id'])
            : $repository->updateManagedUser(
                $postInt('user_id'),
                $postString('role'),
                $status,
                (int) $currentUser['id']
            );
        $_SESSION['flashMessage'] = $success
            ? ($status === 'remove' ? 'User account and related records permanently removed.' : 'User account settings saved.')
            : ($status === 'remove' ? 'The user account could not be removed.' : 'The user account could not be updated.');
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#user-management');
        exit;
    }

    if ($action === 'reset_user_password') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $temporaryPassword = $repository->resetManagedUserPassword($postInt('user_id'), (int) $currentUser['id']);
        $_SESSION['flashMessage'] = $temporaryPassword
            ? 'Temporary password (show once; give it to the user securely): ' . $temporaryPassword
            : 'A password reset could not be prepared for that user.';
        $_SESSION['flashSuccess'] = $temporaryPassword !== null;
        header('Location: index.php?page=admin#user-management');
        exit;
    }

    if ($action === 'change_password') {
        if (!$currentUser) {
            http_response_code(403);
            exit('Sign in to change your password.');
        }
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $success = $newPassword === $confirmPassword
            && $repository->changePassword(
                (int) $currentUser['id'],
                isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '',
                $newPassword
            );
        $_SESSION['flashMessage'] = $success
            ? 'Your password has been changed.'
            : 'Password change failed. Confirm your current password and use a new password between 8 and 72 characters.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=' . ($success ? 'dashboard' : 'change-password'));
        exit;
    }

    if ($action === 'mark_notifications_read') {
        if (!$currentUser) {
            http_response_code(403);
            exit('Sign in to manage notifications.');
        }
        $success = $repository->markNotificationsRead((int) $currentUser['id']);
        $_SESSION['flashMessage'] = $success ? 'Notifications marked as read.' : 'Notifications could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=notifications');
        exit;
    }

    if ($action === 'review_alumni') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $note = $postString('review_note');
        $noteLength = preg_match_all('/./us', $note);
        if ($noteLength === false || $noteLength > 2000) {
            $success = false;
            $message = 'Review notes cannot exceed 2,000 characters.';
        } else {
            $success = $repository->reviewAlumni(
                $postInt('profile_id'),
                $postString('status'),
                $note,
                (int) $currentUser['id']
            );
            $message = $success ? 'Alumni verification review saved.' : 'The profile could not be reviewed.';
        }
        $_SESSION['flashMessage'] = $message;
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#alumni-reviews');
        exit;
    }

    if ($action === 'save_profile_privacy') {
        if (!$currentUser || $currentUser['role'] !== 'alumni') {
            http_response_code(403);
            exit('Alumni access is required.');
        }
        $success = $repository->saveProfilePrivacy(
            (int) $currentUser['id'],
            $postString('visibility'),
            $postString('email_visibility')
        );
        $_SESSION['flashMessage'] = $success
            ? 'Your profile visibility settings have been updated.'
            : 'The profile visibility settings could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=profile');
        exit;
    }

    if ($action === 'save_mentorship_profile') {
        if (!$currentUser || $currentUser['role'] !== 'alumni') {
            http_response_code(403);
            exit('Alumni access is required.');
        }
        $accepting = isset($_POST['accepting_requests']) && $_POST['accepting_requests'] === '1';
        $success = $repository->saveMentorshipProfile(
            (int) $currentUser['id'],
            $postString('topics'),
            $postString('expertise'),
            $postString('availability'),
            $accepting
        );
        $_SESSION['flashMessage'] = $success
            ? 'Your mentorship availability has been updated.'
            : 'Mentorship availability could not be saved. Check the field limits.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=profile');
        exit;
    }

    if ($action === 'submit_mentorship_request') {
        if (!$currentUser || $currentUser['role'] !== 'student') {
            http_response_code(403);
            exit('Student access is required.');
        }
        $success = $repository->submitMentorshipRequest(
            (int) $currentUser['id'],
            $postInt('alumni_id'),
            $postString('topic'),
            $postString('goal'),
            isset($_POST['share_profile']) && $_POST['share_profile'] === '1',
            isset($_POST['share_contact']) && $_POST['share_contact'] === '1',
            $currentUser
        );
        $_SESSION['flashMessage'] = $success
            ? 'Mentorship request sent. It remains pending until the alumnus responds.'
            : 'The mentorship request could not be sent. Check the mentor availability and your pending requests.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=mentorship');
        exit;
    }

    if ($action === 'update_mentorship_request') {
        if (!$currentUser || !in_array($currentUser['role'], ['student', 'alumni'], true)) {
            http_response_code(403);
            exit('A student or alumnus account is required.');
        }
        $success = $repository->updateMentorshipRequest(
            $postInt('request_id'),
            (int) $currentUser['id'],
            $postString('status'),
            isset($_POST['share_contact']) && $_POST['share_contact'] === '1'
        );
        $_SESSION['flashMessage'] = $success ? 'Mentorship request status updated.' : 'The request could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=mentorship');
        exit;
    }

    if ($action === 'create_referral_opportunity') {
        if (!$currentUser || $currentUser['role'] !== 'alumni') {
            http_response_code(403);
            exit('Alumni access is required.');
        }
        $success = $repository->createReferralOpportunity((int) $currentUser['id'], $_POST);
        $_SESSION['flashMessage'] = $success
            ? ($postInt('id') > 0 ? 'Referral opportunity updated.' : 'Referral opportunity shared. Students may request referral consideration; this does not guarantee a referral.')
            : 'The referral opportunity could not be saved. Verify your alumni profile and all required fields.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=referrals');
        exit;
    }

    if ($action === 'submit_referral_request') {
        if (!$currentUser || $currentUser['role'] !== 'student') {
            http_response_code(403);
            exit('Student access is required.');
        }
        $success = $repository->submitReferralRequest(
            (int) $currentUser['id'],
            $postInt('referral_id'),
            $postString('message'),
            isset($_POST['share_profile']) && $_POST['share_profile'] === '1',
            isset($_POST['share_contact']) && $_POST['share_contact'] === '1',
            $currentUser
        );
        $_SESSION['flashMessage'] = $success
            ? 'Referral consideration request sent. It is not a confirmed referral.'
            : 'The request could not be sent. It may have closed or already been requested.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=referrals');
        exit;
    }

    if ($action === 'update_referral_request') {
        if (!$currentUser || !in_array($currentUser['role'], ['student', 'alumni'], true)) {
            http_response_code(403);
            exit('A student or alumnus account is required.');
        }
        $success = $repository->updateReferralRequest(
            $postInt('request_id'),
            (int) $currentUser['id'],
            $postString('status'),
            isset($_POST['share_contact']) && $_POST['share_contact'] === '1'
        );
        $_SESSION['flashMessage'] = $success ? 'Referral request status updated.' : 'The request could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=referrals');
        exit;
    }

    if ($action === 'update_referral_opportunity_status') {
        if (!$currentUser || $currentUser['role'] !== 'alumni') {
            http_response_code(403);
            exit('Alumni access is required.');
        }
        $success = $repository->updateReferralOpportunityStatus(
            $postInt('referral_id'),
            (int) $currentUser['id'],
            $postString('status')
        );
        $_SESSION['flashMessage'] = $success ? 'Referral opportunity status updated.' : 'The referral opportunity could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=referrals');
        exit;
    }

    if ($action === 'save_application') {
        if (!$currentUser || $currentUser['role'] !== 'student') {
            http_response_code(403);
            exit('Student access is required.');
        }
        $success = $repository->saveApplication(
            (int) $currentUser['id'],
            $postInt('opportunity_id'),
            $postString('status'),
            $postString('application_date'),
            $postString('private_note')
        );
        $_SESSION['flashMessage'] = $success
            ? 'Your private application tracker has been updated. This does not verify an employer submission.'
            : 'The tracker could not be updated. The opportunity may have closed or the submitted values may be invalid.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=applications');
        exit;
    }

    if ($action === 'save_alert_preferences') {
        if (!$currentUser || $currentUser['role'] !== 'student') {
            http_response_code(403);
            exit('Student access is required.');
        }
        $success = $repository->saveAlertPreferences(
            (int) $currentUser['id'],
            $postString('frequency'),
            $postString('alert_department'),
            $postString('alert_keywords')
        );
        $_SESSION['flashMessage'] = $success
            ? 'Your in-app opportunity alert preferences have been saved.'
            : 'Alert preferences could not be saved. Check the field limits.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=profile');
        exit;
    }

    if ($action === 'save_company' || $action === 'merge_companies') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        if ($action === 'save_company') {
            $website = $postString('website');
            $validWebsite = $website === ''
                || (filter_var($website, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true));
            $success = $validWebsite && $repository->saveCompany($_POST, (int) $currentUser['id']);
            $message = $success
                ? 'Company details saved.'
                : ($validWebsite ? 'Company details could not be saved. Check for a duplicate name and location.' : 'Enter a valid HTTP or HTTPS company website.');
        } else {
            $success = $repository->mergeCompanies(
                $postInt('source_company_id'),
                $postInt('target_company_id'),
                (int) $currentUser['id']
            );
            $message = $success
                ? 'Company records merged.'
                : 'The records could not be merged. Select two different active records with the same normalized name and location.';
        }
        $_SESSION['flashMessage'] = $message;
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#company-management');
        exit;
    }

    if ($action === 'update_opportunity_status') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $success = $repository->updateOpportunityStatus(
            $postInt('opportunity_id'),
            $postString('status'),
            (int) $currentUser['id'],
            $postInt('company_id')
        );
        $_SESSION['flashMessage'] = $success ? 'Opportunity status updated.' : 'The opportunity status could not be updated.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#opportunity-management');
        exit;
    }

    if ($action === 'submit_profile_report') {
        if (!$currentUser) {
            http_response_code(403);
            exit('Please log in to report a profile.');
        }
        $reason = $postString('reason');
        $details = $postString('details');
        $reasonLength = preg_match_all('/./us', $reason);
        $detailsLength = preg_match_all('/./us', $details);
        $success = $reasonLength !== false && $reasonLength <= 100
            && $detailsLength !== false && $detailsLength <= 2000
            && $repository->submitProfileReport(
                $postInt('profile_id'),
                (int) $currentUser['id'],
                $reason,
                $details
            );
        $_SESSION['flashMessage'] = $success ? 'The profile report was sent to administrators.' : 'The profile report could not be submitted.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=alumni');
        exit;
    }

    if ($action === 'review_profile_report') {
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            http_response_code(403);
            exit('Administrator access is required.');
        }
        $success = $repository->reviewProfileReport(
            $postInt('report_id'),
            $postString('status'),
            (int) $currentUser['id']
        );
        $_SESSION['flashMessage'] = $success ? 'Profile report review saved.' : 'The profile report could not be reviewed.';
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=admin#profile-reports');
        exit;
    }

    if ($action === 'login') {
        $result = $auth->login($_POST);
        $_SESSION['flashMessage'] = $result['message'];
        $_SESSION['flashSuccess'] = $result['success'];
        $_SESSION['flashOverlay'] = true;
        $_SESSION['flashLoginConfirmation'] = $result['success'];
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $nextPage = $postString('next');
            $loginDestinations = ['home', 'dashboard', 'profile', 'alumni', 'opportunities', 'companies', 'company-detail', 'mentorship', 'referrals', 'applications', 'notifications', 'admin'];
            if (!in_array($nextPage, $loginDestinations, true)
                || ($nextPage === 'admin' && $_SESSION['user']['role'] !== 'admin')
                || ($nextPage === 'applications' && $_SESSION['user']['role'] !== 'student')) {
                $nextPage = 'dashboard';
            }
            if (!empty($_SESSION['user']['must_change_password'])) {
                $nextPage = 'change-password';
            }
            header('Location: index.php?page=' . $nextPage);
            exit;
        }
    }

    if ($action === 'register') {
        if ($currentUser) {
            $_SESSION['flashMessage'] = 'Log out before creating another account.';
            $_SESSION['flashSuccess'] = false;
            header('Location: index.php?page=dashboard');
            exit;
        }
        $result = $auth->register($_POST);
        $_SESSION['flashMessage'] = $result['message'];
        $_SESSION['flashSuccess'] = $result['success'];
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: index.php?page=dashboard');
            exit;
        }
    }

    if ($action === 'save_profile') {
        if (!$currentUser) {
            $_SESSION['flashMessage'] = 'Please log in to save your profile.';
            $_SESSION['flashSuccess'] = false;
            header('Location: index.php?page=login');
            exit;
        }

        try {
            $success = $repository->saveUserProfile(
                (int) $currentUser['id'],
                (string) $currentUser['role'],
                $_POST
            );
            $message = $success
                ? 'Your profile has been saved.'
                : 'Profile changes were not saved. Check the required fields and make sure the company is active.';
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            $success = false;
            $message = 'That email address is already attached to another account. Your profile was not changed.';
        }
        $_SESSION['flashMessage'] = $message;
        $_SESSION['flashSuccess'] = $success;
        header('Location: index.php?page=profile');
        exit;
    }

    if ($action === 'logout') {
        $auth->logout();
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['flashMessage'] = 'You have been logged out.';
        $_SESSION['flashSuccess'] = true;
        $_SESSION['flashOverlay'] = true;
        header('Location: index.php?page=home');
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && ($_GET['page'] ?? '') === 'opportunities') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        echo json_encode($opportunityController->search([
            'keyword' => isset($_GET['keyword']) && is_string($_GET['keyword']) ? $_GET['keyword'] : '',
            'type' => isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '',
            'department' => isset($_GET['department']) && is_string($_GET['department']) ? $_GET['department'] : '',
            'location' => isset($_GET['location']) && is_string($_GET['location']) ? $_GET['location'] : '',
            'remote' => isset($_GET['remote']) && is_string($_GET['remote']) ? $_GET['remote'] : '',
            'sort' => isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : '',
            'page' => isset($_GET['page_number']) && is_string($_GET['page_number']) ? $_GET['page_number'] : '1',
        ]), JSON_THROW_ON_ERROR);
    } catch (PDOException $error) {
        error_log('AIUB Career Connect opportunity search failed because a database query could not be completed.');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Opportunity search is temporarily unavailable.']);
    } catch (JsonException $error) {
        error_log('AIUB Career Connect opportunity search response encoding failed: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Opportunity search response could not be encoded.']);
    }
    exit;
}

$page = $_GET['page'] ?? 'home';
$currentUser = $auth->currentUser();

$allowedPages = [
    'home',
    'register',
    'login',
    'dashboard',
    'profile',
    'alumni',
    'opportunities',
    'opportunity-detail',
    'admin',
    'companies',
    'company-detail',
    'mentorship',
    'referrals',
    'applications',
    'notifications',
    'change-password',
];

if ($page === 'apply') {
    $opportunityId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $opportunity = $opportunityId ? $repository->getActiveOpportunity((int) $opportunityId) : null;
    if (!$opportunity || !$repository->isValidExternalUrl((string) $opportunity['application_url'])) {
        http_response_code(404);
        require __DIR__ . '/View/partials/header.php';
        echo '<section class="section container"><div class="message-panel"><h1>Application destination unavailable</h1><p>This opportunity has no valid employer application link. No external page was opened.</p><a class="btn btn--primary" href="index.php?page=opportunities">Browse opportunities</a></div></section>';
        require __DIR__ . '/View/partials/footer.php';
        exit;
    }
    $repository->recordOpportunityEvent((int) $opportunity['id'], $currentUser ? (int) $currentUser['id'] : null, 'click');
    header('Location: ' . $opportunity['application_url'], true, 302);
    exit;
}

if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

if ($currentUser && !empty($currentUser['must_change_password']) && $page !== 'change-password') {
    header('Location: index.php?page=change-password');
    exit;
}

if ($currentUser && in_array($page, ['login', 'register'], true)) {
    $_SESSION['flashMessage'] = 'You are already signed in. Log out before using a different account.';
    $_SESSION['flashSuccess'] = false;
    header('Location: index.php?page=dashboard');
    exit;
}

require __DIR__ . '/View/' . $page . '.php';
} catch (PDOException $error) {
    $driverErrorCode = isset($error->errorInfo[1]) ? (string) $error->errorInfo[1] : 'unavailable';
    $requestAction = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : 'none';
    error_log(
        'AIUB Career Connect database error: SQLSTATE ' . $error->getCode()
        . ', driver code ' . $driverErrorCode
        . ', action ' . $requestAction . '.'
    );
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database error | AIUB Career Connect</title><body><main><h1>Unable to complete this request</h1><p>The database operation failed. No success was reported. Check the database schema and server logs, then try again.</p><p><a href="index.php">Return home</a></p></main></body></html>';
}
