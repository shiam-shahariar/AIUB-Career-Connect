<?php
require __DIR__ . '/partials/header.php';
if (!$currentUser || !in_array($currentUser['role'], ['student', 'alumni'], true)):
?>
<section class="section container">
    <div class="message-panel">
        <h1>AIUB account required</h1>
        <p>Sign in as a student or alumnus to use mentorship.</p>
        <a class="btn btn--primary" href="index.php?page=login&amp;next=mentorship">Login</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; return; ?>
<?php endif; ?>
<?php
$isStudent = $currentUser['role'] === 'student';
$mentorSearch = isset($_GET['mentor_search']) && is_string($_GET['mentor_search']) ? trim($_GET['mentor_search']) : '';
$mentorPage = isset($_GET['mentor_page']) && is_string($_GET['mentor_page']) && ctype_digit($_GET['mentor_page'])
    ? max(1, (int) $_GET['mentor_page'])
    : 1;
$mentorResults = $isStudent ? $repository->getMentorsPage($currentUser, $mentorSearch, $mentorPage) : [];
$mentors = $mentorResults['items'] ?? [];
$requests = $repository->getMentorshipRequests((int) $currentUser['id'], $currentUser['role']);
$pendingMentors = [];
foreach ($requests as $request) {
    if ($request['status'] === 'pending') {
        $pendingMentors[(int) $request['alumni_id']] = true;
    }
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token']);
?>
<section class="section container">
    <div class="page-header">
        <div>
            <span class="eyebrow">Career support</span>
            <h1><?php echo $isStudent ? 'Request alumni mentorship' : 'Mentorship requests'; ?></h1>
        </div>
        <a class="btn btn--secondary btn--outlined" href="index.php?page=dashboard">Back to dashboard</a>
    </div>

    <p class="demo-note">Requests are private. Contact details are shared only after acceptance and only when both people opt in.</p>

    <?php if ($isStudent): ?>
        <form method="get" action="index.php" class="filter-panel panel">
            <input type="hidden" name="page" value="mentorship">
            <label>Search mentors
                <input type="search" name="mentor_search" value="<?php echo htmlspecialchars($mentorSearch); ?>" placeholder="Name, company, or topic">
            </label>
            <button class="btn btn--secondary" type="submit">Search</button>
        </form>
        <?php if (!$mentors): ?>
            <div class="message-panel"><h2>No matching mentors</h2><p>Try a different search or check back after alumni opt in to mentorship.</p></div>
        <?php else: ?>
            <div class="directory-grid">
                <?php foreach ($mentors as $mentor): ?>
                    <article class="directory-card network-card">
                        <span class="badge badge--soft">Verified alumnus</span>
                        <h2><?php echo htmlspecialchars($mentor['name']); ?></h2>
                        <p><?php echo htmlspecialchars(trim(($mentor['job_title'] ?? '') . (($mentor['company'] ?? '') !== '' ? ' at ' . $mentor['company'] : ''))); ?></p>
                        <p><strong>Topics:</strong> <?php echo htmlspecialchars($mentor['topics']); ?></p>
                        <?php if ($mentor['expertise'] !== ''): ?><p><?php echo nl2br(htmlspecialchars($mentor['expertise'])); ?></p><?php endif; ?>
                        <?php if ($mentor['availability'] !== ''): ?><p><strong>Availability:</strong> <?php echo htmlspecialchars($mentor['availability']); ?></p><?php endif; ?>
                        <?php if (isset($pendingMentors[(int) $mentor['id']])): ?>
                            <p class="request-status-note">You already have a pending request with this mentor.</p>
                        <?php else: ?>
                            <details class="mentor-request-details">
                                <summary class="btn btn--primary">Request mentorship</summary>
                            <form method="post" action="index.php" class="form-grid">
                                <input type="hidden" name="action" value="submit_mentorship_request">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="alumni_id" value="<?php echo (int) $mentor['id']; ?>">
                                <label>Career topic
                                    <input type="text" name="topic" maxlength="150" required>
                                </label>
                                <label>What would you like help with?
                                    <textarea name="goal" rows="3" maxlength="1000" required></textarea>
                                </label>
                                <label class="checkbox-label"><input type="checkbox" name="share_profile" value="1"> Share my department, graduation year, and skills with this alumnus</label>
                                <label class="checkbox-label"><input type="checkbox" name="share_contact" value="1"> Share my email if this request is accepted</label>
                                <button class="btn btn--primary" type="submit">Send mentorship request</button>
                            </form>
                            </details>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (($mentorResults['pages'] ?? 1) > 1): ?>
            <nav class="pagination" aria-label="Mentor pages">
                <?php if ($mentorPage > 1): ?><a class="btn btn--secondary" href="index.php?page=mentorship&amp;mentor_search=<?php echo urlencode($mentorSearch); ?>&amp;mentor_page=<?php echo $mentorPage - 1; ?>">Previous</a><?php endif; ?>
                <span>Page <?php echo $mentorPage; ?> of <?php echo (int) $mentorResults['pages']; ?></span>
                <?php if ($mentorPage < $mentorResults['pages']): ?><a class="btn btn--secondary" href="index.php?page=mentorship&amp;mentor_search=<?php echo urlencode($mentorSearch); ?>&amp;mentor_page=<?php echo $mentorPage + 1; ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <section class="panel admin-section">
        <h2><?php echo $isStudent ? 'Your mentorship requests' : 'Requests for you'; ?></h2>
        <?php if (!$requests): ?>
            <p>No mentorship requests yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <caption class="sr-only">Mentorship requests</caption>
                    <thead><tr><th scope="col"><?php echo $isStudent ? 'Alumnus' : 'Student'; ?></th><th scope="col">Topic</th><th scope="col">Goal</th><th scope="col">Shared details / contact</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($isStudent ? $request['alumni_name'] : $request['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['topic']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($request['goal'])); ?></td>
                                <td>
                                    <?php if (!$isStudent && $request['student_department'] !== null): ?>
                                        <strong>Department:</strong> <?php echo htmlspecialchars($request['student_department']); ?><br>
                                        <strong>Graduation:</strong> <?php echo htmlspecialchars((string) $request['student_graduation_year']); ?><br>
                                        <strong>Skills:</strong> <?php echo htmlspecialchars((string) $request['student_skills']); ?><br>
                                    <?php endif; ?>
                                    <?php $contactEmail = $isStudent ? $request['alumni_contact_email'] : $request['student_contact_email']; ?>
                                    <?php if ($contactEmail): ?><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a>
                                    <?php elseif ($request['status'] === 'accepted'): ?><small>Contact not shared. Both participants must opt in.</small>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($request['status'])); ?></td>
                                <td>
                                    <?php if (!$isStudent && $request['status'] === 'pending'): ?>
                                        <form method="post" action="index.php" class="request-actions">
                                            <input type="hidden" name="action" value="update_mentorship_request">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <label class="checkbox-label"><input type="checkbox" name="share_contact" value="1"> Share my email if accepted</label>
                                            <button class="btn btn--primary" name="status" value="accepted" type="submit">Accept</button>
                                            <button class="btn btn--ghost" name="status" value="declined" type="submit">Decline</button>
                                        </form>
                                    <?php elseif ($request['status'] === 'pending' || $request['status'] === 'accepted'): ?>
                                        <form method="post" action="index.php" class="request-actions">
                                            <input type="hidden" name="action" value="update_mentorship_request">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <?php if (!$isStudent && $request['status'] === 'accepted'): ?><button class="btn btn--secondary" name="status" value="completed" type="submit">Mark completed</button><?php endif; ?>
                                            <button class="btn btn--ghost" name="status" value="cancelled" type="submit"><?php echo $isStudent ? 'Withdraw request' : 'Cancel request'; ?></button>
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
