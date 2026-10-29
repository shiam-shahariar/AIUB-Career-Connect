<?php
$readFilter = static function (string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
};
$filters = [
    'name' => $readFilter('name'),
    'department' => $readFilter('department'),
    'graduation_year' => $readFilter('graduation_year'),
    'company' => $readFilter('company'),
    'industry' => $readFilter('industry'),
    'job_title' => $readFilter('job_title'),
];
$departments = $repository->getDepartments();
$requestedPage = $readFilter('page_number');
$page = ctype_digit($requestedPage) ? max(1, (int) $requestedPage) : 1;
$alumniPage = $repository->getAlumniPage($filters, $currentUser, $page);
require __DIR__ . '/partials/header.php';
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Alumni network</span>
            <h1>AIUB Alumni Directory</h1>
        </div>
        <a class="btn btn--secondary btn--outlined" href="index.php?page=companies">Explore companies</a>
    </div>

    <form class="panel directory-filter-form alumni-filter-form" method="get" action="index.php">
        <input type="hidden" name="page" value="alumni">
        <label>Name<input type="search" name="name" value="<?php echo htmlspecialchars($filters['name']); ?>" placeholder="Search alumni"></label>
        <label>Department
            <select name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department); ?>"<?php echo $filters['department'] === $department ? ' selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Graduation year<input type="number" name="graduation_year" min="1950" max="<?php echo (int) date('Y') + 8; ?>" value="<?php echo htmlspecialchars($filters['graduation_year']); ?>" placeholder="e.g. 2019"></label>
        <label>Company<input type="search" name="company" value="<?php echo htmlspecialchars($filters['company']); ?>" placeholder="Company"></label>
        <label>Industry<input type="search" name="industry" value="<?php echo htmlspecialchars($filters['industry']); ?>" placeholder="Industry"></label>
        <label>Job title<input type="search" name="job_title" value="<?php echo htmlspecialchars($filters['job_title']); ?>" placeholder="Job title"></label>
        <div class="directory-filter-actions">
            <button class="btn btn--primary" type="submit">Search alumni</button>
            <a class="btn btn--secondary" href="index.php?page=alumni">Clear</a>
        </div>
    </form>

    <p class="demo-note"><?php echo (int) $alumniPage['total']; ?> verified alumni profiles match your search. Only verified profiles with permitted visibility are shown.</p>

    <?php if (!$alumniPage['items']): ?>
        <div class="message-panel"><h2>No alumni found</h2><p>Adjust the filters or clear them to browse the directory.</p></div>
    <?php else: ?>
        <div class="directory-grid">
            <?php foreach ($alumniPage['items'] as $alumnus): ?>
                <article class="directory-card">
                    <div class="directory-card__top">
                        <div>
                            <h3><?php echo htmlspecialchars($alumnus['name']); ?></h3>
                            <p><?php echo htmlspecialchars($alumnus['job_title'] ?? ''); ?><?php if ($alumnus['company'] !== ''): ?> at <?php if ((int) $alumnus['company_id'] > 0): ?><a href="index.php?page=company-detail&amp;id=<?php echo (int) $alumnus['company_id']; ?>"><?php echo htmlspecialchars($alumnus['company']); ?></a><?php else: ?><?php echo htmlspecialchars($alumnus['company']); ?><?php endif; ?><?php endif; ?></p>
                        </div>
                    </div>
                    <?php if (!empty($alumnus['bio'])): ?>
                        <p class="alumni-card__bio"><?php echo htmlspecialchars($alumnus['bio']); ?></p>
                    <?php endif; ?>
                    <ul>
                        <li><?php echo htmlspecialchars($alumnus['department']); ?> · <?php echo htmlspecialchars((string) $alumnus['graduation_year']); ?></li>
                        <li>Industry: <?php echo htmlspecialchars($alumnus['industry'] ?? ''); ?></li>
                        <?php if ($alumnus['skills']): ?>
                            <li class="skill-tag-list" aria-label="Skills">
                                <?php foreach (array_slice($alumnus['skills'], 0, 8) as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!empty($alumnus['linkedin_url']) && $repository->isValidExternalUrl($alumnus['linkedin_url'])): ?>
                        <a class="text-link alumni-card__linkedin" href="<?php echo htmlspecialchars($alumnus['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">View LinkedIn profile</a>
                    <?php endif; ?>
                    <div class="card-actions alumni-card-actions">
                        <?php if ($alumnus['email_contact']): ?>
                            <span class="alumni-card-actions__contact" aria-hidden="true"></span>
                        <?php else: ?>
                            <span class="alumni-card-actions__contact contact-note">Email contact is not enabled.</span>
                        <?php endif; ?>
                        <div class="alumni-card-actions__buttons">
                            <?php if ($currentUser && $currentUser['role'] === 'student'): ?>
                                <a class="btn btn--primary" href="index.php?page=mentorship&amp;mentor_search=<?php echo rawurlencode($alumnus['name']); ?>">Find mentorship options</a>
                            <?php endif; ?>
                            <?php if ($alumnus['email_contact']): ?>
                                <a href="mailto:<?php echo rawurlencode($alumnus['email']); ?>?subject=<?php echo rawurlencode('AIUB Career Connect inquiry'); ?>" class="btn btn--secondary">Email</a>
                            <?php endif; ?>
                        </div>
                        <?php if ($currentUser): ?>
                            <details class="report-profile">
                                <summary>Report profile</summary>
                                <form method="post" action="index.php" class="form-grid">
                                    <input type="hidden" name="action" value="submit_profile_report">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="profile_id" value="<?php echo (int) $alumnus['profile_id']; ?>">
                                    <label>Reason
                                        <select name="reason" required>
                                            <option value="">Select a reason</option>
                                            <option value="Incorrect information">Incorrect information</option>
                                            <option value="Inappropriate content">Inappropriate content</option>
                                            <option value="Privacy concern">Privacy concern</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </label>
                                    <label>Details<textarea name="details" rows="3" maxlength="2000"></textarea></label>
                                    <button class="btn btn--secondary" type="submit">Send report</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($alumniPage['pages'] > 1): ?>
        <?php
        $query = array_filter(array_merge(['page' => 'alumni'], $filters), static fn ($value): bool => $value !== '');
        ?>
        <nav class="pagination" aria-label="Alumni directory pages">
            <?php if ($page > 1): ?>
                <?php $previousQuery = array_merge($query, ['page_number' => $page - 1]); ?>
                <a class="btn btn--secondary" href="index.php?<?php echo htmlspecialchars(http_build_query($previousQuery)); ?>">Previous</a>
            <?php endif; ?>
            <span>Page <?php echo (int) $page; ?> of <?php echo (int) $alumniPage['pages']; ?></span>
            <?php if ($page < $alumniPage['pages']): ?>
                <?php $nextQuery = array_merge($query, ['page' => 'alumni', 'page_number' => $page + 1]); ?>
                <a class="btn btn--secondary" href="index.php?<?php echo htmlspecialchars(http_build_query($nextQuery)); ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
