<?php require __DIR__ . '/partials/header.php'; ?>
<?php
$homeImages = [
    'home-aiub-arial.jpg',
    'home-aiub-library.jpg',
    'home-aiub-campus.jpg',
    'home-d-building.jpg',
    'home-campus-hero.jpg',
];
shuffle($homeImages);
$stats = $repository->getHomeStats($currentUser);
$featuredOpportunities = $repository->getFeaturedOpportunities();
?>
<section class="hero hero--home">
    <?php if ($homeImages): ?>
        <div class="hero__slides" aria-hidden="true">
            <?php foreach ($homeImages as $index => $homeImage): ?>
                <?php $homeImageUrl = 'Assets/' . rawurlencode($homeImage) . '?v=' . (int) filemtime(__DIR__ . '/../Assets/' . $homeImage); ?>
                <img class="hero__slide<?php echo $index === 0 ? ' is-active' : ''; ?>" <?php if ($index === 0): ?>src="<?php echo htmlspecialchars($homeImageUrl); ?>" fetchpriority="high"<?php else: ?>data-src="<?php echo htmlspecialchars($homeImageUrl); ?>"<?php endif; ?> alt="" decoding="async">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="container hero__grid">
        <div class="hero__content">
            <span class="eyebrow">Career network for AIUB</span>
            <h1>Build your future with AIUB Career Connect.</h1>
            <p>Discover internships, explore alumni networks, and manage a stronger path from campus to career through one trusted AIUB platform.</p>
            <div class="action-row">
                <a href="index.php?page=opportunities" class="btn btn--primary">Browse opportunities</a>
                <a href="index.php?page=<?php echo $currentUser ? 'profile' : 'register'; ?>" class="btn btn--secondary"><?php echo $currentUser ? 'Edit profile' : 'Create profile'; ?></a>
            </div>
            <ul class="hero-stats">
                <li><strong data-slot-counter><?php echo (int) $stats['student_profiles']; ?></strong><span>Student profiles</span></li>
                <li><strong data-slot-counter><?php echo (int) $stats['mentors']; ?></strong><span>Available alumni mentors</span></li>
                <li><strong data-slot-counter><?php echo (int) $stats['published_opportunities']; ?></strong><span>Active listings</span></li>
            </ul>
        </div>
    </div>
</section>

<section class="section container home-user-workflows-section">
    <div class="section-heading home-user-workflows-heading">
        <span class="eyebrow">For every AIUB user</span>
        <h2>Designed Around Student &amp; Alumni Workflows</h2>
    </div>
    <div class="feature-grid home-user-workflows">
        <article class="feature-card">
            <div class="icon-badge">01</div>
            <h3>Students</h3>
            <p>Create a profile, save opportunities, and discover alumni and internship leads relevant to your field.</p>
        </article>
        <article class="feature-card">
            <div class="icon-badge">02</div>
            <h3>Alumni</h3>
            <p>Share career experiences, support students, and make contact preferences clear through a verified alumni directory.</p>
        </article>
    </div>
</section>

<section class="section alt-bg home-opportunity-section">
    <div class="container">
        <div class="split-layout">
            <div class="home-opportunity-copy">
                <span class="eyebrow">Opportunity pipeline</span>
                <h2>Find the right fit faster.</h2>
                <p>Students can browse internships and jobs, review company details, and continue to an employer when a valid application destination is available.</p>
            </div>
            <div class="mini-panel">
                <h3>Featured roles</h3>
                <?php if (!$featuredOpportunities): ?><p>No active opportunities are currently listed.</p><?php endif; ?>
                <?php foreach ($featuredOpportunities as $opportunity): ?>
                    <div class="listing-mini">
                        <span><?php echo htmlspecialchars($opportunity['type']); ?></span>
                        <strong><?php echo htmlspecialchars($opportunity['title']); ?></strong>
                        <small><?php echo htmlspecialchars($opportunity['company']); ?></small>
                        <?php if (!$opportunity['application_available']): ?><small>Application destination unavailable</small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
