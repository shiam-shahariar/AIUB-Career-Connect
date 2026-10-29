<?php
$readFilter = static function (string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
};
$filters = [
    'keyword' => $readFilter('keyword'),
    'industry' => $readFilter('industry'),
    'location' => $readFilter('location'),
];
$requestedPage = $readFilter('page_number');
$page = ctype_digit($requestedPage) ? max(1, (int) $requestedPage) : 1;
$companyPage = $repository->getCompaniesPage($filters, $currentUser, $page);
require __DIR__ . '/partials/header.php';
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Company network</span>
            <h1>AIUB alumni companies</h1>
        </div>
        <a class="btn btn--secondary btn--outlined" href="index.php?page=alumni">Browse alumni</a>
    </div>

    <form class="panel directory-filter-form company-filter-form" method="get" action="index.php">
        <input type="hidden" name="page" value="companies">
        <label>Company or keyword<input type="search" name="keyword" value="<?php echo htmlspecialchars($filters['keyword']); ?>" placeholder="Company, industry, or description"></label>
        <label>Industry<input type="search" name="industry" value="<?php echo htmlspecialchars($filters['industry']); ?>" placeholder="Industry"></label>
        <label>Location<input type="search" name="location" value="<?php echo htmlspecialchars($filters['location']); ?>" placeholder="Location"></label>
        <div class="directory-filter-actions">
            <button class="btn btn--primary" type="submit">Search companies</button>
            <a class="btn btn--secondary" href="index.php?page=companies">Clear</a>
        </div>
    </form>

    <p class="demo-note"><?php echo (int) $companyPage['total']; ?> companies. Counts include verified alumni whose profile visibility permits listing.</p>

    <?php if (!$companyPage['items']): ?>
        <div class="message-panel"><h2>No companies found</h2><p>Try a broader search or clear the filters.</p></div>
    <?php else: ?>
        <div class="directory-grid">
            <?php foreach ($companyPage['items'] as $company): ?>
                <article class="directory-card company-card">
                    <span class="badge badge--soft"><?php echo htmlspecialchars($company['industry']); ?></span>
                    <h2><?php echo htmlspecialchars($company['name']); ?></h2>
                    <?php if ($company['location']): ?><p><?php echo htmlspecialchars($company['location']); ?></p><?php endif; ?>
                    <?php if ($company['description']): ?><p><?php echo htmlspecialchars($company['description']); ?></p><?php endif; ?>
                    <p class="company-alumni-count"><strong><?php echo (int) $company['alumni_count']; ?></strong> verified visible AIUB alumni</p>
                    <div class="card-actions">
                        <a class="btn btn--secondary" href="index.php?page=company-detail&amp;id=<?php echo (int) $company['id']; ?>">View company</a>
                        <?php if ($company['website'] && $repository->isValidExternalUrl($company['website'])): ?>
                            <a class="btn btn--ghost" href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" rel="noopener noreferrer">Company website</a>
                        <?php elseif ($company['website']): ?>
                            <span class="contact-note">Company website unavailable.</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($companyPage['pages'] > 1): ?>
        <?php $query = array_filter(array_merge(['page' => 'companies'], $filters), static fn ($value): bool => $value !== ''); ?>
        <nav class="pagination" aria-label="Company directory pages">
            <?php if ($page > 1): ?>
                <?php $previousQuery = array_merge($query, ['page_number' => $page - 1]); ?>
                <a class="btn btn--secondary" href="index.php?<?php echo htmlspecialchars(http_build_query($previousQuery)); ?>">Previous</a>
            <?php endif; ?>
            <span>Page <?php echo (int) $page; ?> of <?php echo (int) $companyPage['pages']; ?></span>
            <?php if ($page < $companyPage['pages']): ?>
                <?php $nextQuery = array_merge($query, ['page_number' => $page + 1]); ?>
                <a class="btn btn--secondary" href="index.php?<?php echo htmlspecialchars(http_build_query($nextQuery)); ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
