<?php
$profile = $currentUser ? $repository->getUserProfile((int) $currentUser['id']) : null;
$departments = $repository->getDepartments();
$companies = $repository->getCompanies([], $currentUser);
$hasAlumniProfile = $currentUser && $currentUser['role'] === 'alumni'
    && $repository->hasAlumniProfile((int) $currentUser['id']);
$profilePrivacy = $hasAlumniProfile
    ? $repository->getProfilePrivacy((int) $currentUser['id'])
    : null;
$mentorshipProfile = $currentUser && $currentUser['role'] === 'alumni'
    ? $repository->getMentorshipProfile((int) $currentUser['id'])
    : null;
$alertPreferences = $currentUser && $currentUser['role'] === 'student'
    ? $repository->getAlertPreferences((int) $currentUser['id'])
    : null;
require __DIR__ . '/partials/header.php';
?>
<?php if (!$currentUser): ?>
<section class="section container">
    <div class="message-panel">
        <h1>Please log in</h1>
        <p>You must sign in before editing your profile.</p>
        <a class="btn btn--primary" href="index.php?page=login&amp;next=profile">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>

<section class="section container profile-page">
    <div class="page-header">
        <div>
            <span class="eyebrow">Profile</span>
            <h1><?php echo htmlspecialchars($profile['full_name'] ?? $currentUser['name']); ?> profile</h1>
        </div>
        <a href="index.php?page=dashboard" class="btn btn--secondary btn--outlined">Back to dashboard</a>
    </div>

    <?php if ($currentUser['role'] === 'alumni'): ?>
        <?php
        $verificationStatus = $profile['verification_status'] ?? 'pending';
        $verificationCopy = [
            'pending' => 'Your alumni profile is awaiting review. Keep your academic and career details up to date.',
            'verified' => 'Your alumni profile is verified and eligible for directory visibility based on your privacy settings.',
            'rejected' => 'Your alumni profile was not verified. Review the note below, update your details, and contact the career office if you need help.',
        ];
        ?>
        <div class="message-panel" role="status">
            <h2>Verification: <?php echo htmlspecialchars(ucfirst($verificationStatus)); ?></h2>
            <p><?php echo htmlspecialchars($verificationCopy[$verificationStatus] ?? $verificationCopy['pending']); ?></p>
            <?php if (!empty($profile['review_note'])): ?><p><strong>Review note:</strong> <?php echo nl2br(htmlspecialchars($profile['review_note'])); ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="panel form-panel">
        <?php if ($currentUser['role'] === 'admin'): ?>
            <p>Update your account identity and department.</p>
        <?php else: ?>
            <p>Profile details are saved to your account and are used across the alumni directory, company directory, and recommendations.</p>
        <?php endif; ?>

        <form method="post" action="index.php" class="form-grid two-col profile-edit-form" data-enhanced-form>
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label>
                Full name
                <input type="text" name="full_name" maxlength="150" value="<?php echo htmlspecialchars($profile['full_name'] ?? $currentUser['name']); ?>" required>
            </label>
            <label>
                Role
                <input type="text" name="role" value="<?php echo htmlspecialchars($currentUser['role']); ?>" readonly>
            </label>
            <label>
                Email
                <input type="email" name="email" maxlength="180" value="<?php echo htmlspecialchars($profile['email'] ?? $currentUser['email']); ?>" required>
            </label>
            <label>
                Department
                <select name="department" required>
                    <?php if (($profile['department'] ?? '') === ''): ?><option value="">Select your department</option><?php endif; ?>
                    <?php if (!in_array((string) ($profile['department'] ?? ''), $departments, true) && ($profile['department'] ?? '') !== ''): ?>
                        <option value="<?php echo htmlspecialchars($profile['department']); ?>" selected><?php echo htmlspecialchars($profile['department']); ?></option>
                    <?php endif; ?>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>"<?php echo ($profile['department'] ?? '') === $department ? ' selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($currentUser['role'] !== 'admin'): ?>
                <label>Degree<input type="text" name="degree" maxlength="180" value="<?php echo htmlspecialchars($profile['degree'] ?? ''); ?>"></label>
                <label>Student ID<input type="text" name="student_id" maxlength="50" value="<?php echo htmlspecialchars($profile['student_id'] ?? ''); ?>"></label>
                <label>Graduation year
                    <input type="number" name="graduation_year" min="1900" max="<?php echo (int) date('Y') + 10; ?>" value="<?php echo htmlspecialchars((string) ($profile['graduation_year'] ?? '')); ?>">
                </label>
                <?php if ($currentUser['role'] === 'alumni'): ?>
                <label>Current company
                    <input type="text" name="company" maxlength="150" list="company-options" value="<?php echo htmlspecialchars($profile['company'] ?? ''); ?>">
                    <datalist id="company-options">
                        <?php foreach ($companies as $company): ?><option value="<?php echo htmlspecialchars($company['name']); ?>"><?php endforeach; ?>
                    </datalist>
                </label>
                <label>Job title
                    <input type="text" name="job_title" maxlength="150" value="<?php echo htmlspecialchars($profile['job_title'] ?? ''); ?>">
                </label>
                <label>Industry
                    <input type="text" name="industry" maxlength="100" value="<?php echo htmlspecialchars($profile['industry'] ?? ''); ?>">
                </label>
                <?php endif; ?>
                <label>Phone
                    <input type="tel" name="phone" maxlength="30" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                </label>
                <label>Location
                    <input type="text" name="location" maxlength="100" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>">
                </label>
                <label>Portfolio URL
                    <input type="url" name="portfolio_url" maxlength="255" placeholder="https://" value="<?php echo htmlspecialchars($profile['portfolio_url'] ?? ''); ?>">
                </label>
                <label>LinkedIn URL
                    <input type="url" name="linkedin_url" maxlength="255" placeholder="https://" value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>">
                </label>
                <label class="full-width">Skills
                    <div class="skill-tag-editor" data-tag-input>
                        <ul class="skill-tag-list" data-tag-list aria-label="Your skills"></ul>
                        <input type="text" name="skills" data-tag-entry maxlength="2000" value="<?php echo htmlspecialchars($profile['skills'] ?? ''); ?>" placeholder="Type a skill and press Enter">
                    </div>
                    <small>Separate skills with commas if you are not using the interactive tag editor.</small>
                </label>
                <label class="full-width">Bio
                    <textarea rows="5" name="bio" maxlength="5000"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                </label>
            <?php endif; ?>
            <div class="form-actions form-actions--wide">
                <button type="submit" class="btn btn--primary">Save profile</button>
            </div>
        </form>
    </div>

    <?php if ($currentUser['role'] === 'alumni' && $hasAlumniProfile): ?>
        <div class="panel privacy-panel" id="directory-privacy">
            <h2>Directory and contact privacy</h2>
            <p>Only verified alumni profiles can appear in search results. Email contact is shown only when you explicitly allow it.</p>
            <form method="post" action="index.php" class="form-grid two-col">
                <input type="hidden" name="action" value="save_profile_privacy">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>Profile visibility
                    <select name="visibility" required>
                        <option value="public"<?php echo $profilePrivacy['visibility'] === 'public' ? ' selected' : ''; ?>>Public directory</option>
                        <option value="logged_in"<?php echo $profilePrivacy['visibility'] === 'logged_in' ? ' selected' : ''; ?>>Logged-in AIUB users</option>
                        <option value="private"<?php echo $profilePrivacy['visibility'] === 'private' ? ' selected' : ''; ?>>Private (not listed)</option>
                    </select>
                </label>
                <label>Email contact visibility
                    <select name="email_visibility" required>
                        <option value="disabled"<?php echo $profilePrivacy['email_contact_visibility'] === 'disabled' ? ' selected' : ''; ?>>Disable email contact</option>
                        <option value="logged_in"<?php echo $profilePrivacy['email_contact_visibility'] === 'logged_in' ? ' selected' : ''; ?>>Show only to logged-in AIUB users</option>
                    </select>
                </label>
                <div class="form-actions form-actions--wide">
                    <button type="submit" class="btn btn--primary">Save privacy settings</button>
                </div>
            </form>
        </div>
    <?php elseif ($currentUser['role'] === 'alumni'): ?>
        <p class="demo-note">Privacy controls are available once an alumni profile is connected to a saved profile record.</p>
    <?php endif; ?>

    <?php if ($currentUser['role'] === 'alumni' && $hasAlumniProfile): ?>
        <div class="panel privacy-panel" id="mentorship-availability">
            <h2>Mentorship availability</h2>
            <p>Opt in to receive mentorship requests. Your profile must be verified and visible in the alumni directory to be discoverable. Do not include private contact details in these fields.</p>
            <form method="post" action="index.php" class="form-grid two-col">
                <input type="hidden" name="action" value="save_mentorship_profile">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>Topics
                    <input type="text" name="topics" maxlength="500" value="<?php echo htmlspecialchars($mentorshipProfile['topics']); ?>" placeholder="Career planning, backend engineering">
                </label>
                <label>Availability
                    <input type="text" name="availability" maxlength="300" value="<?php echo htmlspecialchars($mentorshipProfile['availability']); ?>" placeholder="Weekday evenings">
                </label>
                <label class="full-width">Expertise
                    <textarea name="expertise" rows="4" maxlength="3000"><?php echo htmlspecialchars($mentorshipProfile['expertise']); ?></textarea>
                </label>
                <label class="consent-option full-width">
                    <input type="checkbox" name="accepting_requests" value="1"<?php echo $mentorshipProfile['accepting_requests'] ? ' checked' : ''; ?>>
                    I agree to appear as available for mentorship requests
                </label>
                <div class="form-actions form-actions--wide">
                    <button type="submit" class="btn btn--primary">Save mentorship availability</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($currentUser['role'] === 'student'): ?>
        <div class="panel privacy-panel" id="opportunity-alerts">
            <h2>Opportunity alert preferences</h2>
            <p>Alerts appear in your dashboard as an in-app digest. Matching uses published opportunities, your department, and optional keywords; no email is sent. Turn alerts off at any time.</p>
            <form method="post" action="index.php" class="form-grid two-col alert-preferences-form">
                <input type="hidden" name="action" value="save_alert_preferences">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label>Alert frequency
                    <select name="frequency" required>
                        <?php foreach (['off' => 'Off', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>"<?php echo $alertPreferences['frequency'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Department filter
                    <select name="alert_department">
                        <option value="">Use my profile department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department); ?>"<?php echo $alertPreferences['department'] === $department ? ' selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="full-width">Matching keywords
                    <input type="text" name="alert_keywords" maxlength="500" value="<?php echo htmlspecialchars($alertPreferences['keywords']); ?>" placeholder="PHP, data analysis, product design">
                </label>
                <div class="form-actions form-actions--wide">
                    <button type="submit" class="btn btn--primary">Save alert preferences</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
