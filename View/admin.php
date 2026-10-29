<?php
require __DIR__ . '/partials/header.php';
if (!$currentUser || $currentUser['role'] !== 'admin'):
?>
<section class="section container">
    <div class="message-panel">
        <h1>Admin access only</h1>
        <p>Only administrators can review alumni profiles and manage companies and opportunities.</p>
        <a href="index.php?page=login&amp;next=admin" class="btn btn--primary">Login as admin</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php
endif;
$stats = $repository->getHomeStats($currentUser);
$reviewQueue = $repository->getAlumniReviewQueue();
$reports = $repository->getOpenProfileReports();
$departments = $repository->getDepartments();
$companies = $repository->getCompanies([], $currentUser);
$editCompanyId = isset($_GET['edit_company']) && is_string($_GET['edit_company']) && ctype_digit($_GET['edit_company'])
    ? (int) $_GET['edit_company']
    : 0;
$companyBeingEdited = $editCompanyId > 0 ? $repository->getCompany($editCompanyId, $currentUser) : null;
$auditLog = $repository->getAuditLog();
$opportunities = $repository->getOpportunities();
$opportunityEditId = isset($_GET['edit_opportunity']) && is_string($_GET['edit_opportunity']) && ctype_digit($_GET['edit_opportunity'])
    ? (int) $_GET['edit_opportunity']
    : 0;
$opportunityDuplicateId = isset($_GET['duplicate_opportunity']) && is_string($_GET['duplicate_opportunity']) && ctype_digit($_GET['duplicate_opportunity'])
    ? (int) $_GET['duplicate_opportunity']
    : 0;
$opportunityToEdit = $opportunityEditId > 0
    ? $repository->getOpportunityForAdmin($opportunityEditId)
    : ($opportunityDuplicateId > 0 ? $repository->getOpportunityForAdmin($opportunityDuplicateId) : null);
$adminUsers = $repository->getAdminUsers(
    isset($_GET['user_search']) && is_string($_GET['user_search']) ? trim($_GET['user_search']) : '',
    isset($_GET['user_role']) && is_string($_GET['user_role']) ? $_GET['user_role'] : '',
    isset($_GET['user_status']) && is_string($_GET['user_status']) ? $_GET['user_status'] : ''
);
$engagement = $repository->getEngagementStats();
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Admin control</span>
            <h1>Network management</h1>
        </div>
    </div>

    <p>Review alumni claims, manage user accounts and companies, and publish current opportunities.</p>

    <div class="stats-grid">
        <div class="stat-card"><span>Alumni reviews to action</span><strong data-slot-counter><?php echo count($reviewQueue); ?></strong><small>Pending or previously rejected</small></div>
        <div class="stat-card"><span>Verified alumni</span><strong data-slot-counter><?php echo (int) $stats['verified_alumni']; ?></strong><small>Verified profiles</small></div>
        <div class="stat-card"><span>Active companies</span><strong data-slot-counter><?php echo count($companies); ?></strong><small>Company directory records</small></div>
        <div class="stat-card"><span>Open profile reports</span><strong data-slot-counter><?php echo count($reports); ?></strong><small>Reports requiring review</small></div>
    </div>

    <nav class="admin-tabs" data-admin-tabs aria-label="Administration sections">
        <a href="#engagement-insights">Insights</a>
        <a href="#alumni-reviews">Alumni review</a>
        <a href="#user-management">Users</a>
        <a href="#opportunity-management">Opportunities</a>
        <a href="#department-management">Departments</a>
        <a href="#company-management">Companies</a>
        <a href="#profile-reports">Reports</a>
        <a href="#audit-log">Audit log</a>
    </nav>

    <section class="panel admin-section" id="engagement-insights" data-tab-panel>
        <h2>Aggregate engagement insights</h2>
        <p class="demo-note">Counts only; personal application notes, mentorship goals, and referral messages are not included.</p>
        <div class="stats-grid engagement-stats">
            <div class="stat-card"><span>Active users</span><strong data-slot-counter><?php echo (int) $engagement['active_users']; ?></strong><small>Active accounts</small></div>
            <div class="stat-card"><span>Verified alumni</span><strong data-slot-counter><?php echo (int) $engagement['verified_alumni']; ?></strong><small>Verified profiles</small></div>
            <div class="stat-card"><span>Opportunity views</span><strong data-slot-counter><?php echo (int) $engagement['opportunity_views']; ?></strong><small>Detail page views</small></div>
            <div class="stat-card"><span>Employer link clicks</span><strong data-slot-counter><?php echo (int) $engagement['opportunity_clicks']; ?></strong><small>Redirects to application links</small></div>
        </div>
        <div class="engagement-breakdown">
            <div><h3>Mentorship requests</h3><p>Pending: <?php echo (int) $engagement['mentorship']['pending']; ?> · Accepted: <?php echo (int) $engagement['mentorship']['accepted']; ?> · Declined: <?php echo (int) $engagement['mentorship']['declined']; ?></p></div>
            <div><h3>Referral requests</h3><p>Pending: <?php echo (int) $engagement['referrals']['pending']; ?> · Accepted: <?php echo (int) $engagement['referrals']['accepted']; ?> · Declined: <?php echo (int) $engagement['referrals']['declined']; ?></p></div>
            <div><h3>Application tracker statuses</h3><p>Saved: <?php echo (int) $engagement['applications']['saved']; ?> · Applied: <?php echo (int) $engagement['applications']['applied']; ?> · Interview: <?php echo (int) $engagement['applications']['interview']; ?> · Offer: <?php echo (int) $engagement['applications']['offer']; ?> · Rejected: <?php echo (int) $engagement['applications']['rejected']; ?></p></div>
        </div>
    </section>

    <section class="panel admin-section" id="alumni-reviews" data-tab-panel>
        <h2>Alumni verification queue</h2>
        <?php if (!$reviewQueue): ?>
            <p>No alumni profiles are waiting for review.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Alumni verification queue</caption>
                    <thead><tr><th scope="col">Alumnus</th><th scope="col">Department / graduation</th><th scope="col">Company / title</th><th scope="col">Profile evidence</th><th scope="col">Current status</th><th scope="col">Review</th></tr></thead>
                    <tbody>
                        <?php foreach ($reviewQueue as $person): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($person['name']); ?></td>
                                <td><?php echo htmlspecialchars($person['department']); ?> · <?php echo htmlspecialchars((string) $person['graduation_year']); ?></td>
                                <td><?php echo htmlspecialchars($person['company']); ?><?php if ($person['job_title']): ?> · <?php echo htmlspecialchars($person['job_title']); ?><?php endif; ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($person['email']); ?> · ID <?php echo htmlspecialchars($person['student_id'] ?? 'not provided'); ?></small><br>
                                    <small><?php echo htmlspecialchars($person['degree']); ?> · <?php echo htmlspecialchars((string) $person['skills']); ?></small>
                                    <?php if (!empty($person['bio'])): ?><p><?php echo nl2br(htmlspecialchars($person['bio'])); ?></p><?php endif; ?>
                                    <?php if (!empty($person['linkedin_url'])): ?><a href="<?php echo htmlspecialchars($person['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">LinkedIn profile</a><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($person['verification_status'])); ?><?php if (!empty($person['review_note'])): ?><br><small><?php echo htmlspecialchars($person['review_note']); ?></small><?php endif; ?></td>
                                <td>
                                    <form method="post" action="index.php" class="admin-inline-form">
                                        <input type="hidden" name="action" value="review_alumni">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="profile_id" value="<?php echo (int) $person['profile_id']; ?>">
                                        <select name="status" aria-label="Set alumni verification status">
                                            <option value="verified">Verify</option>
                                            <option value="rejected">Reject</option>
                                            <option value="pending">Keep pending</option>
                                        </select>
                                        <input type="text" name="review_note" maxlength="2000" placeholder="Review note" value="<?php echo htmlspecialchars($person['review_note'] ?? ''); ?>">
                                        <button class="btn btn--primary" type="submit">Save review</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel admin-section" id="user-management" data-tab-panel>
        <h2>User management</h2>
        <form method="get" action="index.php#user-management" class="admin-inline-form admin-user-filter">
            <input type="hidden" name="page" value="admin">
            <label>Search users<input type="search" name="user_search" value="<?php echo htmlspecialchars(isset($_GET['user_search']) && is_string($_GET['user_search']) ? $_GET['user_search'] : ''); ?>" placeholder="Name, email, or student ID"></label>
            <label>Role
                <select name="user_role">
                    <option value="">All roles</option>
                    <?php foreach (['student', 'alumni'] as $role): ?>
                        <option value="<?php echo $role; ?>"<?php echo ($_GET['user_role'] ?? '') === $role ? ' selected' : ''; ?>><?php echo ucfirst($role); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Status
                <select name="user_status">
                    <option value="">All statuses</option>
                    <?php foreach (['active', 'pending', 'inactive'] as $status): ?>
                        <option value="<?php echo $status; ?>"<?php echo ($_GET['user_status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn--secondary" type="submit">Filter users</button>
        </form>
        <?php if (!$adminUsers): ?>
            <p>No matching student or alumni accounts.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Student and alumni accounts</caption>
                    <thead><tr><th scope="col">User</th><th scope="col">Student ID / graduation</th><th scope="col">Account controls</th><th scope="col">Password reset</th></tr></thead>
                    <tbody>
                        <?php foreach ($adminUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?><br><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                <td><?php echo htmlspecialchars($user['student_id'] ?? ''); ?> · <?php echo htmlspecialchars((string) ($user['graduation_year'] ?? '')); ?></td>
                                <td>
                                    <form method="post" action="index.php" class="admin-inline-form" data-managed-user-form data-user-name="<?php echo htmlspecialchars($user['name']); ?>">
                                        <input type="hidden" name="action" value="manage_user">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                        <select name="role" aria-label="User role">
                                            <?php foreach (['student', 'alumni'] as $role): ?>
                                                <option value="<?php echo $role; ?>"<?php echo $user['role'] === $role ? ' selected' : ''; ?>><?php echo ucfirst($role); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="status" aria-label="User status">
                                            <?php foreach (['active', 'pending', 'inactive'] as $status): ?>
                                                <option value="<?php echo $status; ?>"<?php echo $user['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                            <?php endforeach; ?>
                                            <option value="remove">Remove</option>
                                        </select>
                                        <button class="btn btn--secondary" type="submit">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (empty($user['must_change_password'])): ?>
                                        <form method="post" action="index.php">
                                            <input type="hidden" name="action" value="reset_user_password">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                            <button class="btn btn--ghost" type="submit">Generate temporary password</button>
                                        </form>
                                    <?php else: ?>
                                        <span>Reset required</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p><small>Showing up to 100 accounts. Temporary passwords are displayed once and must be shared securely with the account holder.</small></p>
    </section>

    <section class="panel admin-section" id="opportunity-management" data-tab-panel>
        <h2>Opportunity management</h2>
        <?php
        $opportunityForm = $opportunityToEdit ?? [
            'id' => 0, 'title' => '', 'company' => '', 'type' => 'Job', 'department' => '',
            'location' => '', 'remote_mode' => 'On-site', 'deadline' => '', 'description' => '',
            'application_url' => '', 'status' => 'draft', 'company_id' => null,
        ];
        if ($opportunityDuplicateId > 0 && $opportunityToEdit):
            $opportunityForm['id'] = 0;
            $opportunityForm['status'] = 'draft';
        endif;
        ?>
        <?php if ($opportunityEditId > 0 && !$opportunityToEdit): ?>
            <p class="message-panel">That opportunity is unavailable.</p>
        <?php endif; ?>
        <form method="post" action="index.php" class="company-admin-form opportunity-admin-form">
            <input type="hidden" name="action" value="save_opportunity">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="id" value="<?php echo (int) $opportunityForm['id']; ?>">
            <h3><?php echo (int) $opportunityForm['id'] > 0 ? 'Edit opportunity' : ($opportunityDuplicateId > 0 ? 'Duplicate opportunity' : 'Add an opportunity'); ?></h3>
            <label>Title<input type="text" name="title" maxlength="200" required value="<?php echo htmlspecialchars($opportunityForm['title']); ?>"></label>
            <label>Company name<input type="text" name="company" maxlength="150" value="<?php echo htmlspecialchars($opportunityForm['company']); ?>" placeholder="Required only when no company record is selected"></label>
            <label>Company record
                <select name="company_id">
                    <option value="0">Use company name above</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo (int) $company['id']; ?>"<?php echo (int) ($opportunityForm['company_id'] ?? 0) === (int) $company['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($company['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Type
                <select name="type" required>
                    <?php foreach (['Job', 'Internship'] as $type): ?>
                        <option value="<?php echo $type; ?>"<?php echo $opportunityForm['type'] === $type ? ' selected' : ''; ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Department
                <select name="department" required>
                    <?php if ($opportunityForm['department'] === ''): ?><option value="">Select a department</option><?php endif; ?>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>"<?php echo $opportunityForm['department'] === $department ? ' selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Location<input type="text" name="location" maxlength="120" required value="<?php echo htmlspecialchars($opportunityForm['location']); ?>"></label>
            <label>Work mode<input type="text" name="remote_mode" maxlength="50" required value="<?php echo htmlspecialchars($opportunityForm['remote_mode']); ?>" placeholder="Remote, hybrid, or on-site"></label>
            <label>Deadline<input type="date" name="deadline" required value="<?php echo htmlspecialchars($opportunityForm['deadline']); ?>"></label>
            <label>Application URL<input type="url" name="application_url" maxlength="255" required value="<?php echo htmlspecialchars($opportunityForm['application_url']); ?>" placeholder="https://"></label>
            <label>Status
                <select name="status" required>
                    <?php foreach (['draft', 'published', 'closed', 'archived'] as $status): ?>
                        <option value="<?php echo $status; ?>"<?php echo $opportunityForm['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="full-width">Description<textarea name="description" rows="5" maxlength="10000" required><?php echo htmlspecialchars($opportunityForm['description']); ?></textarea></label>
            <div class="form-actions form-actions--wide">
                <button class="btn btn--primary" type="submit">Save opportunity</button>
                <?php if ((int) $opportunityForm['id'] > 0 || $opportunityDuplicateId > 0): ?><a class="btn btn--secondary" href="index.php?page=admin">Cancel</a><?php endif; ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="data-table">
                <caption class="sr-only">Managed opportunities</caption>
                <thead><tr><th scope="col">Opportunity</th><th scope="col">Company</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col">Actions</th><th scope="col">Update status / company</th></tr></thead>
                <tbody>
                    <?php foreach ($opportunities as $opportunity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($opportunity['title']); ?></td>
                            <td><?php echo htmlspecialchars($opportunity['company']); ?></td>
                            <td><?php echo htmlspecialchars($opportunity['deadline']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($opportunity['status'])); ?></td>
                            <td>
                                <a class="btn btn--secondary" href="index.php?page=admin&amp;edit_opportunity=<?php echo (int) $opportunity['id']; ?>#opportunity-management">Edit</a>
                                <a class="btn btn--ghost" href="index.php?page=admin&amp;duplicate_opportunity=<?php echo (int) $opportunity['id']; ?>#opportunity-management">Duplicate</a>
                            </td>
                            <td>
                                <form method="post" action="index.php" class="admin-inline-form">
                                    <input type="hidden" name="action" value="update_opportunity_status">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="opportunity_id" value="<?php echo (int) $opportunity['id']; ?>">
                                    <select name="status" aria-label="Set opportunity status">
                                        <?php foreach (['draft', 'published', 'closed', 'archived'] as $status): ?>
                                            <option value="<?php echo $status; ?>"<?php echo $opportunity['status'] === $status ? ' selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="company_id" aria-label="Link opportunity to a company">
                                        <option value="0">No company link</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo (int) $company['id']; ?>"<?php echo (int) ($opportunity['company_id'] ?? 0) === (int) $company['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($company['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn--primary" type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="demo-note">Listings past their deadline are excluded from public results.</p>
    </section>

    <section class="panel admin-section" id="department-management" data-tab-panel>
        <h2>Department categories</h2>
        <p>New categories become available in department selectors across profiles, registration, and opportunity alerts.</p>
        <form method="post" action="index.php" class="department-admin-form">
            <input type="hidden" name="action" value="create_department">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <label for="new-department-name">Department name</label>
            <input id="new-department-name" type="text" name="name" maxlength="100" required>
            <button class="btn btn--primary" type="submit">Add department</button>
        </form>
        <ul class="department-category-list" aria-label="Current department categories">
            <?php foreach ($departments as $department): ?>
                <li><?php echo htmlspecialchars($department); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel admin-section" id="company-management" data-tab-panel>
        <h2>Company records</h2>
        <form method="post" action="index.php" class="company-admin-form company-create-form">
            <input type="hidden" name="action" value="save_company">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="id" value="0">
            <h3>Add a company</h3>
            <label>Company name<input type="text" name="name" maxlength="200" required></label>
            <label>Industry<input type="text" name="industry" maxlength="100"></label>
            <label>Website<input type="url" name="website" maxlength="255" placeholder="https://"></label>
            <label>Location<input type="text" name="location" maxlength="150"></label>
            <label class="full-width">Description<textarea name="description" rows="3"></textarea></label>
            <button class="btn btn--primary" type="submit">Add company</button>
        </form>
        <?php if ($companyBeingEdited): ?>
            <form method="post" action="index.php" class="company-admin-form" id="edit-company">
                <input type="hidden" name="action" value="save_company">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="id" value="<?php echo (int) $companyBeingEdited['id']; ?>">
                <h3>Edit <?php echo htmlspecialchars($companyBeingEdited['name']); ?></h3>
                <label>Company name<input type="text" name="name" maxlength="200" required value="<?php echo htmlspecialchars($companyBeingEdited['name']); ?>"></label>
                <label>Industry<input type="text" name="industry" maxlength="100" value="<?php echo htmlspecialchars($companyBeingEdited['industry']); ?>"></label>
                <label>Website<input type="url" name="website" maxlength="255" value="<?php echo htmlspecialchars($companyBeingEdited['website'] ?? ''); ?>" placeholder="https://"></label>
                <label>Location<input type="text" name="location" maxlength="150" value="<?php echo htmlspecialchars($companyBeingEdited['location'] ?? ''); ?>"></label>
                <label class="full-width">Description<textarea name="description" rows="3"><?php echo htmlspecialchars($companyBeingEdited['description'] ?? ''); ?></textarea></label>
                <div class="form-actions"><button class="btn btn--primary" type="submit">Save company changes</button><a class="btn btn--secondary btn--outlined" href="index.php?page=admin">Cancel</a></div>
            </form>
        <?php elseif ($editCompanyId > 0): ?>
            <p class="message-panel">That company is unavailable or inactive.</p>
        <?php endif; ?>
        <?php if (!$companies): ?>
            <p>No active company records.</p>
        <?php else: ?>
            <div class="table-responsive admin-company-table-wrap">
                <table class="data-table admin-company-table">
                    <caption class="sr-only">Managed companies</caption>
                    <thead><tr><th scope="col">Company</th><th scope="col">Industry</th><th scope="col">Verified visible alumni</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($company['name']); ?></td>
                                <td><?php echo htmlspecialchars($company['industry']); ?></td>
                                <td><?php echo (int) $company['alumni_count']; ?></td>
                                <td><a class="btn btn--secondary" href="index.php?page=admin&amp;edit_company=<?php echo (int) $company['id']; ?>#edit-company">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($companies) > 1): ?>
                <form method="post" action="index.php" class="merge-company-form">
                    <input type="hidden" name="action" value="merge_companies">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="merge-company-copy">
                        <h3>Merge duplicate company records</h3>
                        <p class="demo-note">Only records with the same company name and location can be merged. Alumni and opportunity links move to the retained company.</p>
                    </div>
                    <div class="merge-company-controls">
                        <label>Duplicate record
                            <select name="source_company_id" required>
                                <?php foreach ($companies as $company): ?><option value="<?php echo (int) $company['id']; ?>"><?php echo htmlspecialchars($company['name'] . ' — ' . ($company['location'] ?: 'location unspecified') . ' — ' . $company['industry']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Keep this record
                            <select name="target_company_id" required>
                                <?php foreach ($companies as $company): ?><option value="<?php echo (int) $company['id']; ?>"><?php echo htmlspecialchars($company['name'] . ' — ' . ($company['location'] ?: 'location unspecified') . ' — ' . $company['industry']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <button class="btn btn--primary" type="submit">Merge selected duplicates</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="panel admin-section" id="profile-reports" data-tab-panel>
        <h2>Profile reports</h2>
        <?php if (!$reports): ?>
            <p>No open profile reports.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Open profile reports</caption>
                    <thead><tr><th scope="col">Alumni profile</th><th scope="col">Reason / details</th><th scope="col">Reported</th><th scope="col">Review</th></tr></thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['full_name'] ?? ('Profile #' . $report['profile_id'])); ?></td>
                                <td><?php echo htmlspecialchars($report['reason']); ?><?php if ($report['details']): ?><br><?php echo htmlspecialchars($report['details']); ?><?php endif; ?></td>
                                <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                                <td>
                                    <form method="post" action="index.php" class="admin-inline-form">
                                        <input type="hidden" name="action" value="review_profile_report">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                                        <button class="btn btn--secondary" name="status" value="reviewed" type="submit">Mark reviewed</button>
                                        <button class="btn btn--ghost" name="status" value="dismissed" type="submit">Dismiss</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel admin-section" id="audit-log" data-tab-panel>
        <h2>Recent administrative actions</h2>
        <?php if (!$auditLog): ?>
            <p>No administrative actions have been recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Recent administrative actions</caption>
                    <thead><tr><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Actor</th><th scope="col">Time</th></tr></thead>
                    <tbody>
                        <?php foreach ($auditLog as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(str_replace('_', ' ', $entry['action'])); ?></td>
                                <td><?php echo htmlspecialchars($entry['target_type'] . ' #' . $entry['target_id']); ?></td>
                                <td><?php echo htmlspecialchars($entry['actor_name'] ?? ($entry['actor_id'] ? 'User #' . $entry['actor_id'] : 'System')); ?></td>
                                <td><?php echo htmlspecialchars($entry['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
