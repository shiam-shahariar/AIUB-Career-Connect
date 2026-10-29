<?php
require __DIR__ . '/partials/header.php';
if (!$currentUser || !in_array($currentUser['role'], ['student', 'alumni'], true)):
?>
<section class="section container">
    <div class="message-panel">
        <h1>AIUB account required</h1>
        <p>Sign in as a student or alumnus to use referral opportunities.</p>
        <a class="btn btn--primary" href="index.php?page=login&amp;next=referrals">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>
<?php
$isStudent = $currentUser['role'] === 'student';
$referrals = $isStudent
    ? $repository->getReferralOpportunities($currentUser)
    : $repository->getReferralOpportunities($currentUser, (int) $currentUser['id']);
$requests = $repository->getReferralRequests((int) $currentUser['id'], $currentUser['role']);
$editReferralId = isset($_GET['edit_referral']) && is_string($_GET['edit_referral']) && ctype_digit($_GET['edit_referral'])
    ? (int) $_GET['edit_referral']
    : 0;
$referralToEdit = !$isStudent && $editReferralId > 0
    ? $repository->getReferralOpportunityForOwner($editReferralId, (int) $currentUser['id'])
    : null;
$requestStatuses = [];
foreach ($requests as $request) {
    $requestStatuses[(int) $request['referral_opportunity_id']] = $request['status'];
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Career support</span>
            <h1><?php echo $isStudent ? 'Alumni referral opportunities' : 'Referral opportunities'; ?></h1>
        </div>
        <a class="btn btn--secondary btn--outlined" href="index.php?page=dashboard">Back to dashboard</a>
    </div>
    <p class="demo-note">A referral request is not a confirmed referral. Contact details are shared only after acceptance and when both participants opt in.</p>

    <?php if (!$isStudent): ?>
        <section class="panel">
            <h2>Share an opportunity where you may consider referrals</h2>
            <?php if (!$repository->hasVerifiedAlumniProfile((int) $currentUser['id'])): ?>
                <p>Your alumni profile must be verified before you can publish a referral opportunity.</p>
            <?php else: ?>
                <form method="post" action="index.php" class="form-grid two-col referral-opportunity-form">
                    <input type="hidden" name="action" value="create_referral_opportunity">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" value="<?php echo (int) ($referralToEdit['id'] ?? 0); ?>">
                    <h3 class="full-width"><?php echo $referralToEdit ? 'Edit referral opportunity' : 'Share a new referral opportunity'; ?></h3>
                    <label>Role title<input type="text" name="title" maxlength="200" required value="<?php echo htmlspecialchars($referralToEdit['title'] ?? ''); ?>"></label>
                    <label>Company<input type="text" name="company" maxlength="150" required value="<?php echo htmlspecialchars($referralToEdit['company'] ?? ''); ?>"></label>
                    <label>Type
                        <select name="type" required>
                            <?php foreach (['Job', 'Internship'] as $type): ?>
                                <option value="<?php echo $type; ?>"<?php echo ($referralToEdit['type'] ?? 'Job') === $type ? ' selected' : ''; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Department<input type="text" name="department" maxlength="100" required value="<?php echo htmlspecialchars($referralToEdit['department'] ?? ''); ?>"></label>
                    <label>Application deadline<input type="date" name="deadline" min="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($referralToEdit['deadline'] ?? ''); ?>"></label>
                    <label>Application link<input type="url" name="application_url" maxlength="255" placeholder="https://" required value="<?php echo htmlspecialchars($referralToEdit['application_url'] ?? ''); ?>"></label>
                    <label class="full-width">Opportunity details<textarea name="description" rows="4" maxlength="5000" required><?php echo htmlspecialchars($referralToEdit['description'] ?? ''); ?></textarea></label>
                    <div class="form-actions form-actions--wide">
                        <button class="btn btn--primary" type="submit"><?php echo $referralToEdit ? 'Save changes' : 'Share referral opportunity'; ?></button>
                        <?php if ($referralToEdit): ?><a class="btn btn--secondary" href="index.php?page=referrals">Cancel</a><?php endif; ?>
                    </div>
                </form>
                <?php if ($editReferralId > 0 && !$referralToEdit): ?><p class="message-panel">That active referral opportunity is unavailable.</p><?php endif; ?>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <?php if (!$referrals): ?>
            <div class="message-panel"><h2>No active alumni referral opportunities</h2><p>Alumni opportunities appear here when shared by a verified alumnus.</p></div>
        <?php else: ?>
            <div class="listing-grid">
                <?php foreach ($referrals as $referral): ?>
                    <article class="listing-card">
                        <div class="listing-card__meta">
                            <span class="badge badge--soft"><?php echo htmlspecialchars($referral['type']); ?></span>
                            <span>Deadline: <?php echo htmlspecialchars($referral['deadline']); ?></span>
                        </div>
                        <h2><?php echo htmlspecialchars($referral['title']); ?></h2>
                        <p class="company-name"><?php echo htmlspecialchars($referral['company']); ?></p>
                        <p><?php echo htmlspecialchars($referral['description']); ?></p>
                        <p>Shared by <?php echo htmlspecialchars($referral['alumni_name']); ?> · <?php echo htmlspecialchars($referral['department']); ?></p>
                        <?php if ($referral['application_available']): ?>
                            <a href="<?php echo htmlspecialchars($referral['application_url']); ?>" class="btn btn--secondary" target="_blank" rel="noopener noreferrer">View employer listing</a>
                        <?php else: ?>
                            <p class="contact-note" role="status">Employer destination unavailable.</p>
                        <?php endif; ?>
                        <?php if (isset($requestStatuses[(int) $referral['id']])): ?>
                            <p class="request-status-note">Your request is <?php echo htmlspecialchars($requestStatuses[(int) $referral['id']]); ?>. Follow its status below.</p>
                        <?php else: ?>
                            <form method="post" action="index.php" class="form-grid">
                                <input type="hidden" name="action" value="submit_referral_request">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="referral_id" value="<?php echo (int) $referral['id']; ?>">
                                <label>Message to the alumnus
                                    <textarea name="message" rows="3" maxlength="1000" placeholder="Briefly explain why you are interested."></textarea>
                                </label>
                                <label class="checkbox-label"><input type="checkbox" name="share_profile" value="1"> Share my department, graduation year, and skills</label>
                                <label class="checkbox-label"><input type="checkbox" name="share_contact" value="1"> Share my email if accepted</label>
                                <button class="btn btn--primary" type="submit">Request referral consideration</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$isStudent): ?>
        <section class="panel admin-section">
            <h2>Your shared opportunities</h2>
            <?php if (!$referrals): ?>
                <p>You have not shared any referral opportunities yet.</p>
            <?php else: ?>
                <?php foreach ($referrals as $referral): ?>
                    <div class="profile-item">
                        <div>
                            <strong><?php echo htmlspecialchars($referral['title']); ?></strong>
                            <small><?php echo htmlspecialchars($referral['company'] . ' · ' . $referral['deadline'] . ' · ' . ucfirst($referral['status'])); ?></small>
                        </div>
                        <?php if ($referral['status'] === 'active'): ?>
                            <a class="btn btn--secondary" href="index.php?page=referrals&amp;edit_referral=<?php echo (int) $referral['id']; ?>">Edit</a>
                        <?php endif; ?>
                        <form method="post" action="index.php">
                            <input type="hidden" name="action" value="update_referral_opportunity_status">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="referral_id" value="<?php echo (int) $referral['id']; ?>">
                            <button class="btn btn--secondary" name="status" value="<?php echo $referral['status'] === 'active' ? 'closed' : 'active'; ?>" type="submit">
                                <?php echo $referral['status'] === 'active' ? 'Close listing' : 'Reopen listing'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel admin-section">
        <h2><?php echo $isStudent ? 'Your referral requests' : 'Requests on your shared opportunities'; ?></h2>
        <?php if (!$requests): ?>
            <p>No referral requests yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Referral requests</caption>
                    <thead><tr><th scope="col"><?php echo $isStudent ? 'Alumnus' : 'Student'; ?></th><th scope="col">Opportunity</th><th scope="col">Message / shared details</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($isStudent ? $request['alumni_name'] : $request['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['title'] . ' — ' . $request['company']); ?></td>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($request['message'])); ?>
                                    <?php if (!$isStudent && $request['student_department'] !== null): ?>
                                        <br><strong>Department:</strong> <?php echo htmlspecialchars($request['student_department']); ?>
                                        <br><strong>Graduation:</strong> <?php echo htmlspecialchars((string) $request['student_graduation_year']); ?>
                                        <br><strong>Skills:</strong> <?php echo htmlspecialchars((string) $request['student_skills']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $contactEmail = $isStudent ? $request['alumni_contact_email'] : $request['student_contact_email']; ?>
                                    <?php if ($contactEmail): ?><a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a>
                                    <?php elseif ($request['status'] === 'accepted'): ?><small>Contact not shared by both participants.</small>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($request['status'])); ?></td>
                                <td>
                                    <?php if (!$isStudent && $request['status'] === 'pending'): ?>
                                        <form method="post" action="index.php" class="request-actions">
                                            <input type="hidden" name="action" value="update_referral_request">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <label class="checkbox-label"><input type="checkbox" name="share_contact" value="1"> Share my email if accepted</label>
                                            <button class="btn btn--primary" name="status" value="accepted" type="submit">Accept</button>
                                            <button class="btn btn--ghost" name="status" value="declined" type="submit">Decline</button>
                                        </form>
                                    <?php elseif ($isStudent && $request['status'] === 'pending'): ?>
                                        <form method="post" action="index.php" class="request-actions">
                                            <input type="hidden" name="action" value="update_referral_request">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <button class="btn btn--ghost" name="status" value="cancelled" type="submit">Withdraw request</button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
