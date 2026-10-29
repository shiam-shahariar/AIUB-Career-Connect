<?php $departments = $repository->getDepartments(); require __DIR__ . '/partials/header.php'; ?>
<section class="section container auth-page">
    <div class="auth-card auth-card--wide">
        <div class="auth-card__intro">
            <span class="eyebrow">Create account</span>
            <h1>Register as a Student or Alumni</h1>
            <p>Join the AIUB community and build a profile that helps you discover opportunities or support others.</p>
        </div>

        <form method="post" action="index.php" class="form-grid two-col" data-enhanced-form>
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <label>
                Full name
                <input type="text" name="name" placeholder="Your full name" autocomplete="name" maxlength="150" required>
            </label>

            <label>
                Account type
                <select name="role" required>
                    <option value="student">Student</option>
                    <option value="alumni">Alumni</option>
                </select>
            </label>

            <label>
                Email address
                <input type="email" name="email" placeholder="you@aiub.edu" autocomplete="email" maxlength="180" required>
            </label>

            <label>
                Department
                <select name="department" required>
                    <option value="">Select your department</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>"><?php echo htmlspecialchars($department); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Student ID
                <input type="text" name="student_id" maxlength="50" autocomplete="off" required>
            </label>

            <label>
                Graduation year
                <input type="number" name="graduation_year" min="1900" max="<?php echo (int) date('Y') + 10; ?>" required>
            </label>

            <label>
                Password
                <input type="password" name="password" placeholder="At least 8 characters" minlength="8" maxlength="72" autocomplete="new-password" required>
            </label>

            <label>
                Confirm password
                <input type="password" name="confirm_password" placeholder="Repeat password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </label>

            <div class="form-actions form-actions--wide">
                <button type="submit" class="btn btn--primary btn--full">Create account</button>
            </div>
        </form>

        <div class="auth-switch">
            <span>Already a member?</span>
            <a href="index.php?page=login">Go to login</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
