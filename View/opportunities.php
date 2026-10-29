<?php
$departments = $repository->getDepartments();
$locations = $repository->getOpportunityLocations();
$remoteModes = $repository->getOpportunityRemoteModes();
$filterValues = [];
foreach (['keyword', 'type', 'department', 'location', 'remote', 'sort'] as $filter) {
    $filterValues[$filter] = isset($_GET[$filter]) && is_string($_GET[$filter]) ? trim($_GET[$filter]) : '';
}
$requestedPage = isset($_GET['page_number']) && is_string($_GET['page_number']) ? $_GET['page_number'] : '1';
$page = ctype_digit($requestedPage) ? max(1, (int) $requestedPage) : 1;
$searchPage = $repository->getOpportunitySearchPage($filterValues, $page);
$page = $searchPage['page'];
$opportunities = $searchPage['items'];
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
require __DIR__ . '/partials/header.php';
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Job board</span>
            <h1>Explore internships and jobs</h1>
            <p class="results-count"><?php echo (int) $searchPage['total']; ?> current opportunities</p>
        </div>
    </div>

    <div class="filter-panel panel">
        <form id="opportunity-filter-form" class="filter-form" method="get" action="index.php">
            <input type="hidden" name="page" value="opportunities">
            <label>
                Keyword
                <input type="search" name="keyword" value="<?php echo htmlspecialchars($filterValues['keyword']); ?>" placeholder="Search title, company or details">
            </label>
            <label>
                Type
                <select name="type">
                    <option value="">All types</option>
                    <?php foreach (['Internship', 'Job'] as $type): ?>
                        <option value="<?php echo $type; ?>"<?php echo $filterValues['type'] === $type ? ' selected' : ''; ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Department
                <select name="department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>"<?php echo $filterValues['department'] === $department ? ' selected' : ''; ?>><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Location
                <select name="location">
                    <option value="">All locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location); ?>"<?php echo $filterValues['location'] === $location ? ' selected' : ''; ?>><?php echo htmlspecialchars($location); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Work mode
                <select name="remote">
                    <option value="">All work modes</option>
                    <?php foreach ($remoteModes as $remoteMode): ?>
                        <option value="<?php echo htmlspecialchars($remoteMode); ?>"<?php echo $filterValues['remote'] === $remoteMode ? ' selected' : ''; ?>><?php echo htmlspecialchars($remoteMode); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Sort by
                <select name="sort">
                    <option value="">Most recent</option>
                    <option value="deadline"<?php echo $filterValues['sort'] === 'deadline' ? ' selected' : ''; ?>>Deadline soonest</option>
                    <option value="title"<?php echo $filterValues['sort'] === 'title' ? ' selected' : ''; ?>>Title A–Z</option>
                </select>
            </label>
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary">Apply filters</button>
                <a class="btn btn--secondary" href="index.php?page=opportunities">Clear filters</a>
            </div>
        </form>
        <div id="active-opportunity-filters" class="active-filter-chips" aria-label="Active filters"<?php echo count(array_filter($filterValues, static fn ($value): bool => $value !== '')) === 0 ? ' hidden' : ''; ?>>
            <?php
            $filterLabels = ['keyword' => 'Keyword', 'type' => 'Type', 'department' => 'Department', 'location' => 'Location', 'remote' => 'Work mode', 'sort' => 'Sort'];
            foreach ($filterValues as $key => $value):
                if ($value === '') continue;
                $displayValue = $key === 'sort'
                    ? (['deadline' => 'Deadline soonest', 'title' => 'Title A–Z'][$value] ?? 'Most recent')
                    : $value;
            ?>
                <button type="button" class="filter-chip" data-remove-filter="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($filterLabels[$key] . ': ' . $displayValue); ?> <span aria-hidden="true">×</span></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="opportunity-results" class="listing-grid opportunity-grid" aria-live="polite" aria-busy="false">
        <?php if (!$opportunities): ?>
            <div class="message-panel opportunity-empty-state"><h2>No matching opportunities</h2><p>Try broader filters or clear them to browse all current listings.</p></div>
        <?php endif; ?>
        <?php foreach ($opportunities as $opportunity): ?>
            <?php require __DIR__ . '/partials/opportunity-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <nav id="opportunity-pagination" class="pagination" aria-label="Opportunity pages"<?php echo $searchPage['pages'] <= 1 ? ' hidden' : ''; ?>>
        <?php if ($page > 1): ?>
            <a class="btn btn--secondary" href="<?php echo htmlspecialchars('index.php?' . http_build_query(array_merge(['page' => 'opportunities'], array_filter($filterValues, static fn ($value): bool => $value !== ''), ['page_number' => $page - 1]))); ?>">Previous</a>
        <?php endif; ?>
        <span>Page <?php echo (int) $page; ?> of <?php echo (int) $searchPage['pages']; ?></span>
        <?php if ($page < $searchPage['pages']): ?>
            <a class="btn btn--secondary" href="<?php echo htmlspecialchars('index.php?' . http_build_query(array_merge(['page' => 'opportunities'], array_filter($filterValues, static fn ($value): bool => $value !== ''), ['page_number' => $page + 1]))); ?>">Next</a>
        <?php endif; ?>
    </nav>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
