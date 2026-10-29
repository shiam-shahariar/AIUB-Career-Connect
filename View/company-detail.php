<?php
$companyId = isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0;
$company = $repository->getCompany($companyId, $currentUser);
$requestedPage = isset($_GET['page_number']) && is_string($_GET['page_number']) ? trim($_GET['page_number']) : '';
$page = ctype_digit($requestedPage) ? max(1, (int) $requestedPage) : 1;
$alumniPage = $company ? $repository->getCompanyAlumniPage($companyId, $currentUser, $page) : null;
require __DIR__ . '/partials/header.php';
?>
<section class="section container">
    <?php if (!$company): ?>
        <div class="message-panel">
            <h1>Company not found</h1>
            <p>This company is unavailable or does not have an active directory record.</p>
            <a class="btn btn--primary" href="index.php?page=companies">Back to companies</a>
        </div>
    <?php else: ?>
        <div class="page-header">
            <div>
                <span class="eyebrow">Company profile</span>
                <h1><?php echo htmlspecialchars($company['name']); ?></h1>
                <p><?php echo htmlspecialchars($company['industry']); ?><?php if ($company['location']): ?> · <?php echo htmlspecialchars($company['location']); ?><?php endif; ?></p>
            </div>
            <a class="btn btn--secondary company-detail-back" href="index.php?page=companies">Back to companies</a>
        </div>

        <div class="panel company-detail-panel">
            <p><?php echo htmlspecialchars($company['description'] ?: 'No company description has been added.'); ?></p>
            <p><strong><?php echo (int) $company['alumni_count']; ?></strong> verified visible AIUB alumni work here.</p>
            <?php if ($company['website'] && $repository->isValidExternalUrl($company['website'])): ?>
                <a class="btn btn--secondary" href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" rel="noopener noreferrer">Visit company website</a>
            <?php elseif ($company['website']): ?>
                <p class="contact-note">Company website unavailable.</p>
            <?php endif; ?>
        </div>

        <div class="section-heading company-alumni-heading">
            <h2>Verified AIUB alumni</h2>
        </div>
        <?php if (!$alumniPage['items']): ?>
            <div class="message-panel"><p>No alumni profiles are currently visible for this company.</p></div>
        <?php else: ?>
            <div class="directory-grid">
                <?php foreach ($alumniPage['items'] as $alumnus): ?>
                    <article class="directory-card">
                        <h3><?php echo htmlspecialchars($alumnus['name']); ?></h3>
                        <p><?php echo htmlspecialchars($alumnus['job_title'] ?? ''); ?></p>
                        <p><?php echo htmlspecialchars($alumnus['department']); ?> · <?php echo htmlspecialchars((string) $alumnus['graduation_year']); ?></p>
                        <?php if (!empty($alumnus['bio'])): ?><p class="alumni-card__bio"><?php echo htmlspecialchars($alumnus['bio']); ?></p><?php endif; ?>
                        <?php if (!empty($alumnus['linkedin_url']) && $repository->isValidExternalUrl($alumnus['linkedin_url'])): ?>
                            <a class="text-link alumni-card__linkedin" href="<?php echo htmlspecialchars($alumnus['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer">View LinkedIn profile</a>
                        <?php endif; ?>
                        <?php if ($currentUser && $currentUser['role'] === 'student'): ?>
                            <a class="btn btn--primary" href="index.php?page=mentorship&amp;mentor_search=<?php echo rawurlencode($alumnus['name']); ?>">Find mentorship options</a>
                        <?php endif; ?>
                        <?php if ($alumnus['email_contact']): ?>
                            <div class="card-actions"><a class="btn btn--secondary" href="mailto:<?php echo rawurlencode($alumnus['email']); ?>?subject=<?php echo rawurlencode('AIUB Career Connect inquiry'); ?>">Email</a></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($alumniPage['pages'] > 1): ?>
            <nav class="pagination" aria-label="Company alumni pages">
                <?php if ($page > 1): ?>
                    <a class="btn btn--secondary" href="index.php?page=company-detail&amp;id=<?php echo (int) $company['id']; ?>&amp;page_number=<?php echo $page - 1; ?>">Previous</a>
                <?php endif; ?>
                <span>Page <?php echo (int) $page; ?> of <?php echo (int) $alumniPage['pages']; ?></span>
                <?php if ($page < $alumniPage['pages']): ?>
                    <a class="btn btn--secondary" href="index.php?page=company-detail&amp;id=<?php echo (int) $company['id']; ?>&amp;page_number=<?php echo $page + 1; ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
