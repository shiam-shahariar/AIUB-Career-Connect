<?php
class DataRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isLoginAllowed(string $email, string $ipAddress): bool
    {
        $this->pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)');
        $emailHash = hash('sha256', strtolower(trim($email)));
        $ipHash = hash('sha256', $ipAddress);
        $emailStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email_hash = :email_hash AND succeeded = 0 AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)'
        );
        $emailStatement->execute([':email_hash' => $emailHash]);
        if ((int) $emailStatement->fetchColumn() >= 5) {
            return false;
        }
        $ipStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = :ip_hash AND succeeded = 0 AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)'
        );
        $ipStatement->execute([':ip_hash' => $ipHash]);
        return (int) $ipStatement->fetchColumn() < 20;
    }

    public function recordLoginAttempt(string $email, string $ipAddress, bool $succeeded): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (email_hash, ip_hash, succeeded) VALUES (:email_hash, :ip_hash, :succeeded)'
        );
        $statement->execute([
            ':email_hash' => hash('sha256', strtolower(trim($email))),
            ':ip_hash' => hash('sha256', $ipAddress),
            ':succeeded' => $succeeded ? 1 : 0,
        ]);
    }

    public function findUserByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash, u.role, u.status, u.must_change_password, '
            . 'COALESCE(p.department, d.name, "") AS department '
            . 'FROM users u LEFT JOIN profiles p ON p.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = u.department_id '
            . 'WHERE u.email = :email AND u.status = "active" LIMIT 1'
        );
        $statement->execute([':email' => trim($email)]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function findUserById(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.must_change_password, COALESCE(p.department, d.name, "") AS department '
            . 'FROM users u LEFT JOIN profiles p ON p.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = u.department_id '
            . 'WHERE u.id = :id AND u.status = "active" LIMIT 1'
        );
        $statement->execute([':id' => $userId]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function createUser(array $input): ?array
    {
        $name = $this->inputString($input, 'name');
        $email = $this->inputString($input, 'email');
        $role = $this->inputString($input, 'role');
        $department = $this->inputString($input, 'department');
        $passwordHash = $this->inputString($input, 'password_hash');
        $studentId = $this->inputString($input, 'student_id');
        $graduationYear = $this->inputString($input, 'graduation_year');
        $year = $graduationYear === '' ? null : filter_var($graduationYear, FILTER_VALIDATE_INT);
        if (!$this->validText($name, 150)
            || !$this->validText($email, 180)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || strtolower((string) substr(strrchr($email, '@') ?: '', 1)) !== 'aiub.edu'
            || !$this->validText($department, 100)
            || !$this->validText($studentId, 50)
            || ($graduationYear !== '' && ($year === false || $year < 1900 || $year > (int) date('Y') + 10))
            || !in_array($role, ['student', 'alumni'], true)
            || !password_get_info($passwordHash)['algo']) {
            return null;
        }

        $this->pdo->beginTransaction();
        try {
            $departmentStatement = $this->pdo->prepare('SELECT id FROM departments WHERE LOWER(name) = LOWER(:name) LIMIT 1');
            $departmentStatement->execute([':name' => $department]);
            $departmentId = $departmentStatement->fetchColumn();
            $userStatement = $this->pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, status, department_id) '
                . 'VALUES (:name, :email, :password_hash, :role, "active", :department_id)'
            );
            $userStatement->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':role' => $role,
                ':department_id' => $departmentId === false ? null : (int) $departmentId,
            ]);
            $userId = (int) $this->pdo->lastInsertId();
            $profileStatement = $this->pdo->prepare(
                'INSERT INTO profiles (user_id, full_name, student_id, department, degree, graduation_year, visibility, email_contact_visibility, verification_status) '
                . 'VALUES (:user_id, :name, :student_id, :department, "", :graduation_year, "public", "disabled", "pending")'
            );
            $profileStatement->execute([
                ':user_id' => $userId,
                ':name' => $name,
                ':student_id' => $studentId,
                ':department' => $department,
                ':graduation_year' => $year === false ? null : $year,
            ]);
            $this->pdo->commit();
            return $this->findUserById($userId);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getUserProfile(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id AS user_id, u.name, u.email, u.role, p.id AS profile_id, '
            . 'COALESCE(p.full_name, u.name) AS full_name, COALESCE(p.department, d.name, "") AS department, '
            .             'COALESCE(p.degree, "") AS degree, p.student_id, p.graduation_year, COALESCE(p.skills, "") AS skills, '
            . 'COALESCE(p.bio, "") AS bio, p.phone, p.location, p.portfolio_url, p.linkedin_url, '
            . 'p.job_title, p.industry, p.company_id, p.verification_status, p.review_note, COALESCE(c.name, p.current_company, "") AS company '
            . 'FROM users u LEFT JOIN profiles p ON p.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = u.department_id '
            . 'LEFT JOIN companies c ON c.id = p.company_id '
            . 'WHERE u.id = :user_id AND u.status = "active" LIMIT 1'
        );
        $statement->execute([':user_id' => $userId]);
        $profile = $statement->fetch();
        return $profile ?: null;
    }

    public function saveUserProfile(int $userId, string $role, array $input): bool
    {
        $name = $this->inputString($input, 'full_name');
        $email = $this->inputString($input, 'email');
        $department = $this->inputString($input, 'department');
        if (!$this->userHasRole($userId, $role)
            || !$this->validText($name, 150)
            || !$this->validText($email, 180)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || !$this->validText($department, 100)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $previousStatement = $this->pdo->prepare(
                'SELECT u.email, p.department, p.student_id, p.graduation_year, p.degree, p.job_title, p.company_id, p.current_company, p.verification_status '
                . 'FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = :user_id FOR UPDATE'
            );
            $previousStatement->execute([':user_id' => $userId]);
            $previous = $previousStatement->fetch();
            $departmentStatement = $this->pdo->prepare('SELECT id FROM departments WHERE LOWER(name) = LOWER(:name) LIMIT 1');
            $departmentStatement->execute([':name' => $department]);
            $departmentId = $departmentStatement->fetchColumn();
            $userStatement = $this->pdo->prepare(
                'UPDATE users SET name = :name, email = :email, department_id = :department_id '
                . 'WHERE id = :user_id AND role = :role AND status = "active"'
            );
            $userStatement->execute([
                ':name' => $name,
                ':email' => $email,
                ':department_id' => $departmentId === false ? null : (int) $departmentId,
                ':user_id' => $userId,
                ':role' => $role,
            ]);
            if ($role !== 'admin') {
                $yearValue = $this->inputString($input, 'graduation_year');
                $studentId = $this->inputString($input, 'student_id');
                $degree = $this->inputString($input, 'degree');
                $skills = $this->inputString($input, 'skills');
                $bio = $this->inputString($input, 'bio');
                $companyName = $this->inputString($input, 'company');
                $phone = $this->inputString($input, 'phone');
                $location = $this->inputString($input, 'location');
                $portfolioUrl = $this->inputString($input, 'portfolio_url');
                $linkedinUrl = $this->inputString($input, 'linkedin_url');
                $jobTitle = $this->inputString($input, 'job_title');
                $industry = $this->inputString($input, 'industry');
                $year = $yearValue === '' ? null : filter_var($yearValue, FILTER_VALIDATE_INT);
                if (($yearValue !== '' && ($year === false || $year < 1900 || $year > (int) date('Y') + 10))
                    || !$this->validText($studentId, 50, true)
                    || !$this->validText($degree, 180, true)
                    || !$this->validText($skills, 2000, true)
                    || !$this->validText($bio, 5000, true)
                    || !$this->validText($companyName, 150, true)
                    || !$this->validText($phone, 30, true)
                    || !$this->validText($location, 100, true)
                    || !$this->validText($jobTitle, 150, true)
                    || !$this->validText($industry, 100, true)
                    || !$this->validText($portfolioUrl, 255, true)
                    || !$this->validText($linkedinUrl, 255, true)
                    || ($portfolioUrl !== '' && !$this->validHttpUrl($portfolioUrl))
                    || ($linkedinUrl !== '' && !$this->validHttpUrl($linkedinUrl))) {
                    $this->pdo->rollBack();
                    return false;
                }
                $companyStatement = $this->pdo->prepare(
                    'SELECT id, name FROM companies WHERE status = "active" AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1'
                );
                $companyStatement->execute([':name' => $companyName]);
                $companyRecord = $companyName === '' ? false : $companyStatement->fetch();
                $companyId = $companyRecord ? (int) $companyRecord['id'] : null;
                if ($companyRecord) {
                    $companyName = $companyRecord['name'];
                }
                $requiresReview = $role === 'alumni'
                    && ($previous['verification_status'] ?? '') === 'verified'
                    && (
                        strcasecmp((string) ($previous['email'] ?? ''), $email) !== 0
                        || (string) ($previous['department'] ?? '') !== $department
                        || (string) ($previous['student_id'] ?? '') !== $studentId
                        || (string) ($previous['graduation_year'] ?? '') !== (string) ($year === false || $year === null ? '' : $year)
                        || (string) ($previous['degree'] ?? '') !== $degree
                        || (string) ($previous['job_title'] ?? '') !== $jobTitle
                        || (int) ($previous['company_id'] ?? 0) !== (int) ($companyId ?? 0)
                        || (string) ($previous['current_company'] ?? '') !== $companyName
                    );
                $profileStatement = $this->pdo->prepare(
                    'INSERT INTO profiles (user_id, full_name, student_id, department, degree, graduation_year, skills, bio, phone, location, portfolio_url, linkedin_url, job_title, industry, company_id, current_company, visibility, email_contact_visibility, verification_status) '
                    . 'VALUES (:user_id, :name, :student_id, :department, :degree, :graduation_year, :skills, :bio, :phone, :location, :portfolio_url, :linkedin_url, :job_title, :industry, :company_id, :company_name, "public", "disabled", "pending") '
                    . 'ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), department = VALUES(department), degree = VALUES(degree), '
                    . 'student_id = VALUES(student_id), '
                    . 'graduation_year = VALUES(graduation_year), skills = VALUES(skills), bio = VALUES(bio), '
                    . 'phone = VALUES(phone), location = VALUES(location), portfolio_url = VALUES(portfolio_url), '
                    . 'linkedin_url = VALUES(linkedin_url), job_title = VALUES(job_title), industry = VALUES(industry), '
                    . 'company_id = VALUES(company_id), current_company = VALUES(current_company), '
                    . 'verification_status = IF(:requires_review = 1 AND verification_status = "verified", "pending", verification_status), '
                    . 'verified_at = IF(:clear_verified_at = 1, NULL, verified_at), verified_by = IF(:clear_verified_by = 1, NULL, verified_by), '
                    . 'review_note = IF(:clear_review_note = 1, NULL, review_note)'
                );
                $profileStatement->execute([
                    ':user_id' => $userId,
                    ':name' => $name,
                    ':student_id' => $studentId === '' ? null : $studentId,
                    ':department' => $department,
                    ':degree' => $degree,
                    ':graduation_year' => $year === false ? null : $year,
                    ':skills' => $skills,
                    ':bio' => $bio,
                    ':phone' => $phone === '' ? null : $phone,
                    ':location' => $location === '' ? null : $location,
                    ':portfolio_url' => $portfolioUrl === '' ? null : $portfolioUrl,
                    ':linkedin_url' => $linkedinUrl === '' ? null : $linkedinUrl,
                    ':job_title' => $jobTitle === '' ? null : $jobTitle,
                    ':industry' => $industry === '' ? null : $industry,
                    ':company_id' => $companyId,
                    ':company_name' => $companyName,
                    ':requires_review' => $requiresReview ? 1 : 0,
                    ':clear_verified_at' => $requiresReview ? 1 : 0,
                    ':clear_verified_by' => $requiresReview ? 1 : 0,
                    ':clear_review_note' => $requiresReview ? 1 : 0,
                ]);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getDepartments(): array
    {
        return $this->pdo->query(
            'SELECT name FROM departments UNION SELECT department AS name FROM profiles WHERE department <> "" '
            . 'UNION SELECT department AS name FROM opportunities WHERE department <> "" ORDER BY name'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function createDepartment(string $name, int $actorId): bool
    {
        $name = trim($name);
        if ($actorId < 1 || !$this->validText($name, 100)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $duplicate = $this->pdo->prepare(
                'SELECT 1 FROM departments WHERE LOWER(TRIM(name)) = LOWER(TRIM(:department_name)) '
                . 'UNION ALL SELECT 1 FROM profiles WHERE TRIM(department) <> "" AND LOWER(TRIM(department)) = LOWER(TRIM(:profile_department)) '
                . 'UNION ALL SELECT 1 FROM opportunities WHERE TRIM(department) <> "" AND LOWER(TRIM(department)) = LOWER(TRIM(:opportunity_department)) '
                . 'LIMIT 1'
            );
            $duplicate->execute([
                ':department_name' => $name,
                ':profile_department' => $name,
                ':opportunity_department' => $name,
            ]);
            if ($duplicate->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }

            $statement = $this->pdo->prepare('INSERT INTO departments (name) VALUES (:name)');
            $statement->execute([':name' => $name]);
            $departmentId = (int) $this->pdo->lastInsertId();
            $this->writeAudit($actorId, 'department_created', 'department', $departmentId, ['name' => $name]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (($error->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $error;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getAlumni(array $filters = [], ?array $viewer = null, bool $includeUnverified = false): array
    {
        return $this->queryAlumni($filters, $viewer, $includeUnverified);
    }

    public function getAlumniPage(array $filters, ?array $viewer, int $page, int $pageSize = 12): array
    {
        $pageSize = max(1, min(50, $pageSize));
        $page = max(1, $page);
        $items = $this->queryAlumni($filters, $viewer, false, $pageSize, ($page - 1) * $pageSize);
        $total = $this->countAlumni($filters, $viewer);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function getAlumniReviewQueue(): array
    {
        return $this->pdo->query(
            "SELECT p.id AS profile_id, u.id AS user_id, u.name, u.email, p.student_id, p.department, p.degree, p.graduation_year, "
            . "COALESCE(c.name, p.current_company, '') AS company, p.job_title, p.skills, p.bio, p.linkedin_url, "
            . "p.verification_status, p.review_note FROM profiles p JOIN users u ON u.id = p.user_id "
            . "LEFT JOIN companies c ON c.id = p.company_id WHERE u.role = 'alumni' AND u.status = 'active' "
            . "AND p.verification_status IN ('pending', 'rejected') ORDER BY p.updated_at ASC LIMIT 100"
        )->fetchAll();
    }

    public function reviewAlumni(int $profileId, string $status, string $note, int $actorId): bool
    {
        if (!in_array($status, ['verified', 'rejected', 'pending'], true) || $profileId < 1) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $recipientStatement = $this->pdo->prepare(
                'SELECT user_id FROM profiles WHERE id = :profile_id AND user_id IN (SELECT id FROM users WHERE role = "alumni")'
            );
            $recipientStatement->execute([':profile_id' => $profileId]);
            $recipientId = $recipientStatement->fetchColumn();
            if ($recipientId === false) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare(
                'UPDATE profiles SET verification_status = :status, review_note = :note, verified_at = :verified_at, verified_by = :actor_id WHERE id = :profile_id AND user_id IN (SELECT id FROM users WHERE role = "alumni")'
            );
            $statement->execute([
                ':status' => $status,
                ':note' => $note === '' ? null : $note,
                ':verified_at' => $status === 'verified' ? date('Y-m-d H:i:s') : null,
                ':actor_id' => $actorId,
                ':profile_id' => $profileId,
            ]);
            $changed = $statement->rowCount() === 1;
            if (!$changed) {
                $exists = $this->pdo->prepare(
                    'SELECT 1 FROM profiles WHERE id = :profile_id AND user_id IN (SELECT id FROM users WHERE role = "alumni")'
                );
                $exists->execute([':profile_id' => $profileId]);
                if (!$exists->fetchColumn()) {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            if ($changed) {
                $this->writeAudit($actorId, 'alumni_' . $status, 'profile', $profileId, ['review_note' => $note]);
                $this->createNotification(
                    (int) $recipientId,
                    'Alumni verification updated',
                    'Your alumni profile verification status is now ' . $status . '.',
                    'index.php?page=profile'
                );
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function saveProfilePrivacy(int $userId, string $visibility, string $emailVisibility): bool
    {
        if (!in_array($visibility, ['public', 'logged_in', 'private'], true)
            || !in_array($emailVisibility, ['logged_in', 'disabled'], true)) {
            return false;
        }

        $exists = $this->pdo->prepare(
            "SELECT 1 FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = :user_id AND u.role = 'alumni' LIMIT 1"
        );
        $exists->execute([':user_id' => $userId]);
        if (!$exists->fetchColumn()) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'UPDATE profiles SET visibility = :visibility, email_contact_visibility = :email_visibility, email_contact = :email_contact WHERE user_id = :user_id AND EXISTS (SELECT 1 FROM users WHERE id = :user_id_check AND role = "alumni")'
        );
        $statement->execute([
            ':visibility' => $visibility,
            ':email_visibility' => $emailVisibility,
            ':email_contact' => $emailVisibility === 'disabled' ? 'no' : 'yes',
            ':user_id' => $userId,
            ':user_id_check' => $userId,
        ]);
        return true;
    }

    public function getProfilePrivacy(int $userId): array
    {
        $defaults = ['visibility' => 'public', 'email_contact_visibility' => 'disabled'];
        $statement = $this->pdo->prepare(
            "SELECT p.visibility, p.email_contact_visibility FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = :user_id AND u.role = 'alumni' LIMIT 1"
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetch() ?: $defaults;
    }

    public function hasAlumniProfile(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.user_id = :user_id AND u.role = 'alumni' LIMIT 1"
        );
        $statement->execute([':user_id' => $userId]);
        return (bool) $statement->fetchColumn();
    }

    public function hasVerifiedAlumniProfile(int $userId): bool
    {
        return $this->userHasVerifiedAlumni($userId);
    }

    public function getCompaniesPage(array $filters, ?array $viewer, int $page, int $pageSize = 12): array
    {
        $pageSize = max(1, min(50, $pageSize));
        $page = max(1, $page);
        [$where, $params] = $this->companyFilterSql($filters);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM companies c WHERE c.status = 'active' {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $items = $this->queryCompanies($filters, $viewer, $pageSize, ($page - 1) * $pageSize);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function getCompanies(array $filters = [], ?array $viewer = null): array
    {
        return $this->queryCompanies($filters, $viewer);
    }

    public function getCompany(int $companyId, ?array $viewer): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $visibleClause = $viewer ? "p.visibility IN ('public', 'logged_in')" : "p.visibility = 'public'";
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name, c.industry, c.website, c.location, c.description, '
            . "COUNT(DISTINCT p.id) AS alumni_count FROM companies c LEFT JOIN profiles p ON p.company_id = c.id AND p.verification_status = 'verified' "
            . "AND {$visibleClause} AND p.user_id IN (SELECT id FROM users WHERE role = 'alumni' AND status = 'active') "
            . 'WHERE c.status = "active" AND c.id = :id GROUP BY c.id, c.name, c.industry, c.website, c.location, c.description LIMIT 1'
        );
        $statement->execute([':id' => $companyId]);
        $company = $statement->fetch();
        return $company ?: null;
    }

    public function getCompanyAlumniPage(int $companyId, ?array $viewer, int $page, int $pageSize = 12): array
    {
        $pageSize = max(1, min(50, $pageSize));
        $page = max(1, $page);
        $items = $this->queryAlumni([], $viewer, false, $pageSize, ($page - 1) * $pageSize, $companyId);
        $total = $this->countAlumni([], $viewer, $companyId);
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function saveCompany(array $input, int $actorId): bool
    {
        $idValue = $input['id'] ?? 0;
        $id = is_scalar($idValue) ? (int) $idValue : -1;
        $name = $this->inputString($input, 'name');
        $industry = $this->inputString($input, 'industry');
        $website = $this->inputString($input, 'website');
        $location = $this->inputString($input, 'location');
        $description = $this->inputString($input, 'description');
        if ($id < 0 || $name === ''
            || $this->characterCount($name) > 200
            || $this->characterCount($industry) > 100
            || $this->characterCount($website) > 255
            || $this->characterCount($location) > 150
            || $this->characterCount($description) > 16000) {
            return false;
        }

        $duplicateSql = 'SELECT id FROM companies WHERE status = "active" AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND LOWER(TRIM(COALESCE(location, ""))) = LOWER(TRIM(:location))';
        if ($id > 0) {
            $duplicateSql .= ' AND id <> :id';
        }
        $duplicateSql .= ' LIMIT 1';
        $duplicate = $this->pdo->prepare($duplicateSql);
        $duplicateParams = [':name' => $name, ':location' => $location];
        if ($id > 0) {
            $duplicateParams[':id'] = $id;
        }
        $duplicate->execute($duplicateParams);
        if ($duplicate->fetchColumn()) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            if ($id === 0) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO companies (name, industry, website, location, description) VALUES (:name, :industry, :website, :location, :description)'
                );
                $statement->execute([
                    ':name' => $name,
                    ':industry' => $industry,
                    ':website' => $website === '' ? null : $website,
                    ':location' => $location === '' ? null : $location,
                    ':description' => $description === '' ? null : $description,
                ]);
                $id = (int) $this->pdo->lastInsertId();
                $this->writeAudit($actorId, 'company_created', 'company', $id, ['name' => $name]);
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE companies SET name = :name, industry = :industry, website = :website, location = :location, description = :description WHERE id = :id AND status = "active"'
                );
                $statement->execute([
                    ':name' => $name,
                    ':industry' => $industry,
                    ':website' => $website === '' ? null : $website,
                    ':location' => $location === '' ? null : $location,
                    ':description' => $description === '' ? null : $description,
                    ':id' => $id,
                ]);
                if ($statement->rowCount() !== 1) {
                    $exists = $this->pdo->prepare('SELECT 1 FROM companies WHERE id = :id AND status = "active"');
                    $exists->execute([':id' => $id]);
                    if (!$exists->fetchColumn()) {
                        $this->pdo->rollBack();
                        return false;
                    }
                }
                $this->writeAudit($actorId, 'company_updated', 'company', $id, ['name' => $name]);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function mergeCompanies(int $sourceId, int $targetId, int $actorId): bool
    {
        if ($sourceId < 1 || $targetId < 1 || $sourceId === $targetId) {
            return false;
        }
        $valid = $this->pdo->prepare(
            'SELECT id, name, COALESCE(location, "") AS location FROM companies WHERE id IN (:source, :target) AND status = "active"'
        );
        $valid->execute([':source' => $sourceId, ':target' => $targetId]);
        $records = $valid->fetchAll();
        if (count($records) !== 2
            || strcasecmp(trim($records[0]['name']), trim($records[1]['name'])) !== 0
            || strcasecmp(trim($records[0]['location']), trim($records[1]['location'])) !== 0) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE profiles SET company_id = :target WHERE company_id = :source')
                ->execute([':target' => $targetId, ':source' => $sourceId]);
            $this->pdo->prepare('UPDATE opportunities SET company_id = :target WHERE company_id = :source')
                ->execute([':target' => $targetId, ':source' => $sourceId]);
            $statement = $this->pdo->prepare('UPDATE companies SET status = "merged", merged_into_id = :target WHERE id = :source AND status = "active"');
            $statement->execute([':target' => $targetId, ':source' => $sourceId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            $this->writeAudit($actorId, 'company_merged', 'company', $sourceId, ['merged_into_id' => $targetId]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getOpportunityForAdmin(int $opportunityId): ?array
    {
        if ($opportunityId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT id, title, company, type, department, location, remote_mode, deadline, description, application_url, status, company_id '
            . 'FROM opportunities WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $opportunityId]);
        $opportunity = $statement->fetch();
        return $opportunity ?: null;
    }

    public function saveOpportunity(array $input, int $actorId): bool
    {
        $idValue = $this->inputString($input, 'id');
        $id = $idValue === '' ? 0 : filter_var($idValue, FILTER_VALIDATE_INT);
        $title = $this->inputString($input, 'title');
        $company = $this->inputString($input, 'company');
        $type = $this->inputString($input, 'type');
        $department = $this->inputString($input, 'department');
        $location = $this->inputString($input, 'location');
        $remoteMode = $this->inputString($input, 'remote_mode');
        $deadline = $this->inputString($input, 'deadline');
        $description = $this->inputString($input, 'description');
        $applicationUrl = $this->inputString($input, 'application_url');
        $status = $this->inputString($input, 'status');
        $companyIdValue = $this->inputString($input, 'company_id');
        $companyId = $companyIdValue === '' ? 0 : filter_var($companyIdValue, FILTER_VALIDATE_INT);

        if ($id === false || $id < 0 || $companyId === false || $companyId < 0
            || !$this->validText($title, 200)
            || !$this->validText($company, 150, $companyId > 0)
            || !in_array($type, ['Internship', 'Job'], true)
            || !$this->validText($department, 100)
            || !$this->validText($location, 120)
            || !$this->validText($remoteMode, 50)
            || !$this->validDate($deadline)
            || !$this->validText($description, 10000)
            || !$this->validHttpUrl($applicationUrl)
            || !in_array($status, ['draft', 'published', 'closed', 'archived'], true)
            || ($status === 'published' && $deadline < date('Y-m-d'))) {
            return false;
        }

        if ($companyId > 0) {
            $companyStatement = $this->pdo->prepare(
                'SELECT name FROM companies WHERE id = :id AND status = "active" LIMIT 1'
            );
            $companyStatement->execute([':id' => $companyId]);
            $companyName = $companyStatement->fetchColumn();
            if ($companyName === false) {
                return false;
            }
            $company = (string) $companyName;
        } else {
            $companyId = null;
        }

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $statement = $this->pdo->prepare(
                    'UPDATE opportunities SET title = :title, company = :company, type = :type, department = :department, '
                    . 'location = :location, remote_mode = :remote_mode, deadline = :deadline, description = :description, '
                    . 'application_url = :application_url, status = :status, company_id = :company_id WHERE id = :id'
                );
                $statement->execute([
                    ':title' => $title, ':company' => $company, ':type' => $type,
                    ':department' => $department, ':location' => $location, ':remote_mode' => $remoteMode,
                    ':deadline' => $deadline, ':description' => $description, ':application_url' => $applicationUrl,
                    ':status' => $status, ':company_id' => $companyId, ':id' => $id,
                ]);
                if ($statement->rowCount() === 0 && !$this->getOpportunityForAdmin($id)) {
                    $this->pdo->rollBack();
                    return false;
                }
                $this->writeAudit($actorId, 'opportunity_content_updated', 'opportunity', $id);
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO opportunities (title, company, type, department, location, remote_mode, deadline, description, application_url, status, company_id, created_by) '
                    . 'VALUES (:title, :company, :type, :department, :location, :remote_mode, :deadline, :description, :application_url, :status, :company_id, :created_by)'
                );
                $statement->execute([
                    ':title' => $title, ':company' => $company, ':type' => $type,
                    ':department' => $department, ':location' => $location, ':remote_mode' => $remoteMode,
                    ':deadline' => $deadline, ':description' => $description, ':application_url' => $applicationUrl,
                    ':status' => $status, ':company_id' => $companyId, ':created_by' => $actorId,
                ]);
                $id = (int) $this->pdo->lastInsertId();
                $this->writeAudit($actorId, 'opportunity_created', 'opportunity', $id);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updateOpportunityStatus(int $opportunityId, string $status, int $actorId, ?int $companyId = null): bool
    {
        if (!in_array($status, ['draft', 'published', 'closed', 'archived'], true)
            || $opportunityId < 1
            || ($companyId !== null && $companyId < 0)) {
            return false;
        }
        if ($status === 'published') {
                $validDeadline = $this->pdo->prepare('SELECT 1 FROM opportunities WHERE id = :id AND deadline >= CURRENT_DATE');
                $validDeadline->execute([':id' => $opportunityId]);
                if (!$validDeadline->fetchColumn()) {
                    return false;
                }
        }
        if ($companyId !== null && $companyId > 0) {
            $validCompany = $this->pdo->prepare('SELECT 1 FROM companies WHERE id = :id AND status = "active"');
            $validCompany->execute([':id' => $companyId]);
            if (!$validCompany->fetchColumn()) {
                return false;
            }
        }
        $this->pdo->beginTransaction();
        try {
            $currentStatement = $this->pdo->prepare('SELECT status, company_id FROM opportunities WHERE id = :id FOR UPDATE');
            $currentStatement->execute([':id' => $opportunityId]);
            $current = $currentStatement->fetch();
            if (!$current) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare('UPDATE opportunities SET status = :status, company_id = :company_id WHERE id = :id');
            $statement->execute([
                ':status' => $status,
                ':company_id' => $companyId && $companyId > 0 ? $companyId : null,
                ':id' => $opportunityId,
            ]);
            $statusChanged = $current['status'] !== $status;
            $companyChanged = (int) ($current['company_id'] ?? 0) !== (int) ($companyId && $companyId > 0 ? $companyId : 0);
            if ($companyId !== null && $companyChanged) {
                $this->writeAudit(
                    $actorId,
                    $companyId > 0 ? 'opportunity_company_linked' : 'opportunity_company_unlinked',
                    'opportunity',
                    $opportunityId,
                    $companyId > 0 ? ['company_id' => $companyId] : []
                );
            }
            if ($statusChanged) {
                $this->writeAudit($actorId, 'opportunity_' . $status, 'opportunity', $opportunityId);
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getAuditLog(int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.id, l.actor_id, u.name AS actor_name, l.action, l.target_type, l.target_id, l.details, l.created_at FROM admin_audit_log l LEFT JOIN users u ON u.id = l.actor_id ORDER BY l.created_at DESC, l.id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getAdminUsers(string $search = '', string $role = '', string $status = ''): array
    {
        $conditions = ["u.role IN ('student', 'alumni')"];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(u.name LIKE :name_search OR u.email LIKE :email_search OR p.student_id LIKE :student_search)';
            $needle = '%' . $search . '%';
            $parameters[':name_search'] = $needle;
            $parameters[':email_search'] = $needle;
            $parameters[':student_search'] = $needle;
        }
        if (in_array($role, ['student', 'alumni'], true)) {
            $conditions[] = 'u.role = :role';
            $parameters[':role'] = $role;
        }
        if (in_array($status, ['active', 'pending', 'inactive'], true)) {
            $conditions[] = 'u.status = :status';
            $parameters[':status'] = $status;
        }
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.role, u.status, u.must_change_password, u.created_at, '
            . 'p.student_id, p.graduation_year FROM users u LEFT JOIN profiles p ON p.user_id = u.id '
            . 'WHERE ' . implode(' AND ', $conditions) . ' ORDER BY u.created_at DESC, u.id DESC LIMIT 100'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function updateManagedUser(int $userId, string $role, string $status, int $actorId): bool
    {
        if ($userId < 1 || $actorId < 1 || $userId === $actorId
            || !in_array($role, ['student', 'alumni'], true)
            || !in_array($status, ['active', 'pending', 'inactive'], true)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE users SET role = :role, status = :status WHERE id = :id AND role IN ("student", "alumni")'
            );
            $statement->execute([':role' => $role, ':status' => $status, ':id' => $userId]);
            if ($statement->rowCount() === 0) {
                $exists = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id AND role IN ("student", "alumni")');
                $exists->execute([':id' => $userId]);
                if (!$exists->fetchColumn()) {
                    $this->pdo->rollBack();
                    return false;
                }
            }
            if ($role === 'student') {
                $this->pdo->prepare(
                    'UPDATE profiles SET verification_status = "pending", verified_at = NULL, verified_by = NULL WHERE user_id = :id'
                )->execute([':id' => $userId]);
            }
            $this->writeAudit($actorId, 'user_account_updated', 'user', $userId, ['role' => $role, 'status' => $status]);
            if ($status !== 'active') {
                $this->createNotification($userId, 'Account status changed', 'Your account status changed. Sign-in access reflects this status.', 'index.php?page=login');
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function deleteManagedUser(int $userId, int $actorId): bool
    {
        if ($userId < 1 || $actorId < 1 || $userId === $actorId) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare('SELECT role FROM users WHERE id = :id FOR UPDATE');
            $user->execute([':id' => $userId]);
            $role = $user->fetchColumn();
            if (!in_array($role, ['student', 'alumni'], true)) {
                $this->pdo->rollBack();
                return false;
            }

            $deleteTargetReports = $this->pdo->prepare(
                'DELETE FROM profile_reports WHERE reporter_id = :reporter_id '
                . 'OR profile_id IN (SELECT id FROM profiles WHERE user_id = :profile_user_id)'
            );
            $deleteTargetReports->execute([
                ':reporter_id' => $userId,
                ':profile_user_id' => $userId,
            ]);
            $this->pdo->prepare('UPDATE profile_reports SET reviewed_by = NULL WHERE reviewed_by = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('UPDATE profiles SET verified_by = NULL WHERE verified_by = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('UPDATE opportunities SET created_by = NULL WHERE created_by = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('UPDATE admin_audit_log SET actor_id = NULL WHERE actor_id = :id')
                ->execute([':id' => $userId]);

            $this->pdo->prepare(
                'DELETE FROM mentorship_requests WHERE student_id = :student_id OR alumni_id = :alumni_id'
            )->execute([':student_id' => $userId, ':alumni_id' => $userId]);
            $this->pdo->prepare(
                'DELETE FROM referral_requests WHERE student_id = :student_id '
                . 'OR referral_opportunity_id IN (SELECT id FROM referral_opportunities WHERE alumni_id = :alumni_id)'
            )->execute([':student_id' => $userId, ':alumni_id' => $userId]);
            $this->pdo->prepare('DELETE FROM referral_opportunities WHERE alumni_id = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM applications WHERE user_id = :id')->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM alert_preferences WHERE user_id = :id')->execute([':id' => $userId]);
            $this->pdo->prepare('UPDATE opportunity_events SET user_id = NULL WHERE user_id = :id')
                ->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM alumni_directory WHERE user_id = :id')->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM mentorship_profiles WHERE user_id = :id')->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM notifications WHERE user_id = :id')->execute([':id' => $userId]);
            $this->pdo->prepare('DELETE FROM profiles WHERE user_id = :id')->execute([':id' => $userId]);

            $this->writeAudit($actorId, 'user_account_removed', 'user', $userId, ['role' => $role]);
            $deleteUser = $this->pdo->prepare(
                'DELETE FROM users WHERE id = :id AND role IN ("student", "alumni")'
            );
            $deleteUser->execute([':id' => $userId]);
            if ($deleteUser->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function resetManagedUserPassword(int $userId, int $actorId): ?string
    {
        if ($userId < 1 || $actorId < 1 || $userId === $actorId) {
            return null;
        }
        $temporaryPassword = bin2hex(random_bytes(12));
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE users SET password_hash = :password_hash, must_change_password = 1 WHERE id = :id AND role IN ("student", "alumni")'
            );
            $statement->execute([':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT), ':id' => $userId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
            $this->writeAudit($actorId, 'user_password_reset_required', 'user', $userId);
            $this->pdo->commit();
            return $temporaryPassword;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if ($userId < 1 || strlen($newPassword) < 8 || strlen($newPassword) > 72
            || $currentPassword === '' || $newPassword === $currentPassword) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id AND status = "active"');
        $statement->execute([':id' => $userId]);
        $passwordHash = $statement->fetchColumn();
        if ($passwordHash === false || !password_verify($currentPassword, (string) $passwordHash)) {
            return false;
        }
        $update = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, must_change_password = 0 WHERE id = :id AND status = "active"'
        );
        return $update->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);
    }

    public function getNotifications(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, message, link, created_at, read_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 100'
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function getUnreadNotificationCount(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $statement->execute([':user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function markNotificationsRead(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND read_at IS NULL'
        );
        return $statement->execute([':user_id' => $userId]);
    }

    public function submitProfileReport(int $profileId, int $reporterId, string $reason, string $details): bool
    {
        if ($profileId < 1 || $reporterId < 1 || $reason === '') {
            return false;
        }
        $abuseCheck = $this->pdo->prepare(
            'SELECT COUNT(*) FROM profile_reports WHERE profile_id = :profile_id AND reporter_id = :reporter_id '
            . 'AND status = "open" AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)'
        );
        $abuseCheck->execute([':profile_id' => $profileId, ':reporter_id' => $reporterId]);
        if ((int) $abuseCheck->fetchColumn() > 0) {
            return false;
        }
        $rateCheck = $this->pdo->prepare(
            'SELECT COUNT(*) FROM profile_reports WHERE reporter_id = :reporter_id AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)'
        );
        $rateCheck->execute([':reporter_id' => $reporterId]);
        if ((int) $rateCheck->fetchColumn() >= 3) {
            return false;
        }
        $statement = $this->pdo->prepare(
            "INSERT INTO profile_reports (profile_id, reporter_id, reason, details) SELECT p.id, :reporter_id, :reason, :details FROM profiles p JOIN users u ON u.id = p.user_id WHERE p.id = :profile_id AND u.id <> :reporter_check AND u.role = 'alumni' AND p.verification_status = 'verified' AND p.visibility IN ('public', 'logged_in')"
        );
        $statement->execute([
            ':profile_id' => $profileId,
            ':reporter_id' => $reporterId,
            ':reporter_check' => $reporterId,
            ':reason' => $reason,
            ':details' => $details === '' ? null : $details,
        ]);
        return $statement->rowCount() === 1;
    }

    public function getOpenProfileReports(): array
    {
        return $this->pdo->query(
            "SELECT r.id, r.profile_id, r.reason, r.details, r.created_at, p.full_name, u.name AS reporter_name FROM profile_reports r JOIN profiles p ON p.id = r.profile_id JOIN users u ON u.id = r.reporter_id WHERE r.status = 'open' ORDER BY r.created_at ASC LIMIT 100"
        )->fetchAll();
    }

    public function reviewProfileReport(int $reportId, string $status, int $actorId): bool
    {
        if ($reportId < 1 || !in_array($status, ['reviewed', 'dismissed'], true)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $reporterStatement = $this->pdo->prepare('SELECT reporter_id FROM profile_reports WHERE id = :id AND status = "open"');
            $reporterStatement->execute([':id' => $reportId]);
            $reporterId = $reporterStatement->fetchColumn();
            if ($reporterId === false) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare(
                'UPDATE profile_reports SET status = :status, reviewed_by = :actor_id, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id AND status = "open"'
            );
            $statement->execute([':status' => $status, ':actor_id' => $actorId, ':id' => $reportId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            $this->writeAudit($actorId, 'profile_report_' . $status, 'profile_report', $reportId);
            $this->createNotification(
                (int) $reporterId,
                'Profile report reviewed',
                'An administrator reviewed your profile report.',
                'index.php?page=alumni'
            );
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getMentorshipProfile(int $alumniId): array
    {
        $defaults = ['topics' => '', 'expertise' => '', 'availability' => '', 'accepting_requests' => false];
        $statement = $this->pdo->prepare('SELECT topics, expertise, availability, accepting_requests FROM mentorship_profiles WHERE user_id = :user_id');
        $statement->execute([':user_id' => $alumniId]);
        $profile = $statement->fetch();
        return $profile
            ? array_replace($defaults, $profile, ['accepting_requests' => (bool) $profile['accepting_requests']])
            : $defaults;
    }

    public function saveMentorshipProfile(int $alumniId, string $topics, string $expertise, string $availability, bool $accepting): bool
    {
        if (!$this->validText($topics, 500, true)
            || !$this->validText($expertise, 3000, true)
            || !$this->validText($availability, 300, true)
            || !$this->userHasRole($alumniId, 'alumni')) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO mentorship_profiles (user_id, topics, expertise, availability, accepting_requests) VALUES (:user_id, :topics, :expertise, :availability, :accepting) ON DUPLICATE KEY UPDATE topics = VALUES(topics), expertise = VALUES(expertise), availability = VALUES(availability), accepting_requests = VALUES(accepting_requests)'
        );
        $statement->execute([
            ':user_id' => $alumniId,
            ':topics' => $topics,
            ':expertise' => $expertise,
            ':availability' => $availability,
            ':accepting' => $accepting ? 1 : 0,
        ]);
        return true;
    }

    public function getMentorsPage(?array $viewer, string $search, int $page, int $pageSize = 12): array
    {
        $visibility = $viewer ? "p.visibility IN ('public', 'logged_in')" : "p.visibility = 'public'";
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $searchClause = '';
        $parameters = [];
        if (trim($search) !== '') {
            $pattern = '%' . strtr(trim($search), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $searchClause = " AND (p.full_name LIKE :name ESCAPE '!' OR m.topics LIKE :topics ESCAPE '!' OR m.expertise LIKE :expertise ESCAPE '!' OR COALESCE(c.name, p.current_company, '') LIKE :company ESCAPE '!')";
            $parameters = [':name' => $pattern, ':topics' => $pattern, ':expertise' => $pattern, ':company' => $pattern];
        }
        $base = "FROM mentorship_profiles m JOIN users u ON u.id = m.user_id JOIN profiles p ON p.user_id = u.id "
            . "LEFT JOIN companies c ON c.id = p.company_id WHERE u.role = 'alumni' AND u.status = 'active' "
            . "AND p.verification_status = 'verified' AND {$visibility} AND m.accepting_requests = 1{$searchClause}";
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) ' . $base);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $statement = $this->pdo->prepare(
            'SELECT u.id, p.full_name AS name, p.department, p.graduation_year, COALESCE(c.name, p.current_company, \'\') AS company, '
            . 'p.job_title, COALESCE(c.industry, p.industry, \'\') AS industry, p.bio, m.topics, m.expertise, m.availability '
            . $base . ' ORDER BY p.full_name LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function getMentorshipRequests(int $userId, string $role): array
    {
        if (!in_array($role, ['student', 'alumni'], true)) {
            return [];
        }
        $column = $role === 'student' ? 'r.student_id' : 'r.alumni_id';
        $statement = $this->pdo->prepare(
            "SELECT r.id, r.student_id, r.alumni_id, r.topic, r.goal, r.status, r.created_at, r.share_profile, r.share_contact, r.alumni_share_contact, "
            . "student.name AS student_name, alumni.name AS alumni_name, "
            . "CASE WHEN r.status = 'accepted' AND r.share_contact = 1 THEN student.email ELSE NULL END AS student_contact_email, "
            . "CASE WHEN r.status = 'accepted' AND r.alumni_share_contact = 1 THEN alumni.email ELSE NULL END AS alumni_contact_email, "
            . "CASE WHEN r.share_profile = 1 THEN sp.department ELSE NULL END AS student_department, "
            . "CASE WHEN r.share_profile = 1 THEN sp.graduation_year ELSE NULL END AS student_graduation_year, "
            . "CASE WHEN r.share_profile = 1 THEN sp.skills ELSE NULL END AS student_skills "
            . "FROM mentorship_requests r JOIN users student ON student.id = r.student_id JOIN users alumni ON alumni.id = r.alumni_id "
            . "LEFT JOIN profiles sp ON sp.user_id = student.id WHERE {$column} = :user_id ORDER BY r.created_at DESC LIMIT 100"
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function submitMentorshipRequest(
        int $studentId,
        int $alumniId,
        string $topic,
        string $goal,
        bool $shareProfile,
        bool $shareContact,
        ?array $viewer
    ): bool
    {
        if (!$this->validText($topic, 150) || !$this->validText($goal, 1000)
            || !$this->userHasRole($studentId, 'student') || $studentId === $alumniId) {
            return false;
        }
        $visibility = $viewer ? "p.visibility IN ('public', 'logged_in')" : "p.visibility = 'public'";
        $mentorStatement = $this->pdo->prepare(
            "SELECT 1 FROM mentorship_profiles m JOIN users u ON u.id = m.user_id JOIN profiles p ON p.user_id = u.id "
            . "WHERE u.id = :alumni_id AND u.role = 'alumni' AND u.status = 'active' AND p.verification_status = 'verified' "
            . "AND {$visibility} AND m.accepting_requests = 1 LIMIT 1"
        );
        $mentorStatement->execute([':alumni_id' => $alumniId]);
        if (!$mentorStatement->fetchColumn()) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $duplicate = $this->pdo->prepare("SELECT 1 FROM mentorship_requests WHERE student_id = :student AND alumni_id = :alumni AND status = 'pending' LIMIT 1");
            $duplicate->execute([':student' => $studentId, ':alumni' => $alumniId]);
            if ($duplicate->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO mentorship_requests (student_id, alumni_id, topic, goal, share_profile, share_contact) '
                . 'VALUES (:student, :alumni, :topic, :goal, :share_profile, :share_contact)'
            );
            $statement->execute([
                ':student' => $studentId, ':alumni' => $alumniId, ':topic' => $topic, ':goal' => $goal,
                ':share_profile' => $shareProfile ? 1 : 0, ':share_contact' => $shareContact ? 1 : 0,
            ]);
            $this->createNotification($alumniId, 'New mentorship request', 'A student sent you a mentorship request.', 'index.php?page=mentorship');
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updateMentorshipRequest(int $requestId, int $actorId, string $status, bool $shareContact = false): bool
    {
        if ($requestId < 1 || $actorId < 1
            || !in_array($status, ['accepted', 'declined', 'completed', 'cancelled'], true)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
        $requestStatement = $this->pdo->prepare('SELECT student_id, alumni_id, status FROM mentorship_requests WHERE id = :id FOR UPDATE');
        $requestStatement->execute([':id' => $requestId]);
        $request = $requestStatement->fetch();
        if (!$request) {
            $this->pdo->rollBack();
            return false;
        }
        $isAlumni = (int) $request['alumni_id'] === $actorId;
        $isStudent = (int) $request['student_id'] === $actorId;
        $validTransition = ($isAlumni && $request['status'] === 'pending' && in_array($status, ['accepted', 'declined'], true))
            || ($isAlumni && $request['status'] === 'accepted' && in_array($status, ['completed', 'cancelled'], true))
            || ($isStudent && in_array($request['status'], ['pending', 'accepted'], true) && $status === 'cancelled');
        if (!$validTransition) {
            $this->pdo->rollBack();
            return false;
        }
        $statement = $this->pdo->prepare(
            'UPDATE mentorship_requests SET status = :status, alumni_share_contact = :share_contact WHERE id = :id AND status = :previous_status'
        );
        $statement->execute([
            ':status' => $status,
            ':share_contact' => $isAlumni && $status === 'accepted' && $shareContact ? 1 : 0,
            ':id' => $requestId,
            ':previous_status' => $request['status'],
        ]);
        if ($statement->rowCount() !== 1) {
            $this->pdo->rollBack();
            return false;
        }
        $recipientId = $isAlumni ? (int) $request['student_id'] : (int) $request['alumni_id'];
        $this->createNotification($recipientId, 'Mentorship request updated', 'A mentorship request status changed to ' . $status . '.', 'index.php?page=mentorship');
        $this->pdo->commit();
        return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getReferralOpportunities(?array $viewer, ?int $ownerId = null): array
    {
        $visibility = $ownerId !== null
            ? '1 = 1'
            : ($viewer ? "p.visibility IN ('public', 'logged_in')" : "p.visibility = 'public'");
        $ownerFilter = $ownerId === null
            ? " AND r.status = 'active' AND r.deadline >= CURRENT_DATE"
            : ' AND r.alumni_id = :owner_id';
        $statement = $this->pdo->prepare(
            "SELECT r.id, r.alumni_id, u.name AS alumni_name, r.title, r.company, r.type, r.department, r.deadline, r.description, r.application_url, r.status, r.created_at FROM referral_opportunities r JOIN users u ON u.id = r.alumni_id JOIN profiles p ON p.user_id = u.id WHERE u.role = 'alumni' AND u.status = 'active' AND p.verification_status = 'verified' AND {$visibility}{$ownerFilter} ORDER BY r.created_at DESC LIMIT 100"
        );
        if ($ownerId !== null) {
            $statement->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
        }
        $statement->execute();
        $referrals = $statement->fetchAll();
        foreach ($referrals as &$referral) {
            $referral['application_available'] = $this->isValidExternalUrl((string) $referral['application_url']);
        }
        unset($referral);
        return $referrals;
    }

    public function createReferralOpportunity(int $alumniId, array $input): bool
    {
        $idValue = $this->inputString($input, 'id');
        $id = $idValue === '' ? 0 : filter_var($idValue, FILTER_VALIDATE_INT);
        $title = $this->inputString($input, 'title');
        $company = $this->inputString($input, 'company');
        $type = $this->inputString($input, 'type');
        $department = $this->inputString($input, 'department');
        $deadline = $this->inputString($input, 'deadline');
        $description = $this->inputString($input, 'description');
        $applicationUrl = $this->inputString($input, 'application_url');
        if ($id === false || $id < 0 || !$this->userHasVerifiedAlumni($alumniId)
            || !$this->validText($title, 200)
            || !$this->validText($company, 150)
            || !in_array($type, ['Internship', 'Job'], true)
            || !$this->validText($department, 100)
            || !$this->validDate($deadline)
            || $deadline < date('Y-m-d')
            || !$this->validText($description, 5000)
            || !$this->validHttpUrl($applicationUrl)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $statement = $this->pdo->prepare(
                    'UPDATE referral_opportunities SET title = :title, company = :company, type = :type, department = :department, '
                    . 'deadline = :deadline, description = :description, application_url = :url '
                    . 'WHERE id = :id AND alumni_id = :alumni AND status = "active"'
                );
                $statement->execute([
                    ':title' => $title, ':company' => $company, ':type' => $type,
                    ':department' => $department, ':deadline' => $deadline, ':description' => $description,
                    ':url' => $applicationUrl, ':id' => $id, ':alumni' => $alumniId,
                ]);
                if ($statement->rowCount() === 0) {
                    $exists = $this->pdo->prepare(
                        'SELECT 1 FROM referral_opportunities WHERE id = :id AND alumni_id = :alumni AND status = "active"'
                    );
                    $exists->execute([':id' => $id, ':alumni' => $alumniId]);
                    if (!$exists->fetchColumn()) {
                        $this->pdo->rollBack();
                        return false;
                    }
                }
                $this->writeAudit($alumniId, 'referral_opportunity_updated', 'referral_opportunity', $id);
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO referral_opportunities (alumni_id, title, company, type, department, deadline, description, application_url) '
                    . 'VALUES (:alumni, :title, :company, :type, :department, :deadline, :description, :url)'
                );
                $statement->execute([
                    ':alumni' => $alumniId, ':title' => $title, ':company' => $company,
                    ':type' => $type, ':department' => $department, ':deadline' => $deadline,
                    ':description' => $description, ':url' => $applicationUrl,
                ]);
                $this->writeAudit($alumniId, 'referral_opportunity_created', 'referral_opportunity', (int) $this->pdo->lastInsertId());
            }
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getReferralOpportunityForOwner(int $referralId, int $alumniId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, company, type, department, deadline, description, application_url, status '
            . 'FROM referral_opportunities WHERE id = :id AND alumni_id = :alumni AND status = "active" LIMIT 1'
        );
        $statement->execute([':id' => $referralId, ':alumni' => $alumniId]);
        $referral = $statement->fetch();
        return $referral ?: null;
    }

    public function getReferralRequests(int $userId, string $role): array
    {
        if (!in_array($role, ['student', 'alumni'], true)) {
            return [];
        }
        $column = $role === 'student' ? 'rr.student_id' : 'ro.alumni_id';
        $statement = $this->pdo->prepare(
            "SELECT rr.id, rr.referral_opportunity_id, rr.student_id, ro.alumni_id, ro.title, ro.company, ro.deadline, rr.message, rr.status, rr.created_at, "
            . "rr.share_profile, rr.share_contact, rr.alumni_share_contact, student.name AS student_name, alumni.name AS alumni_name, "
            . "CASE WHEN rr.status = 'accepted' AND rr.share_contact = 1 THEN student.email ELSE NULL END AS student_contact_email, "
            . "CASE WHEN rr.status = 'accepted' AND rr.alumni_share_contact = 1 THEN alumni.email ELSE NULL END AS alumni_contact_email, "
            . "CASE WHEN rr.share_profile = 1 THEN p.department ELSE NULL END AS student_department, "
            . "CASE WHEN rr.share_profile = 1 THEN p.graduation_year ELSE NULL END AS student_graduation_year, "
            . "CASE WHEN rr.share_profile = 1 THEN p.skills ELSE NULL END AS student_skills "
            . "FROM referral_requests rr JOIN referral_opportunities ro ON ro.id = rr.referral_opportunity_id "
            . "JOIN users student ON student.id = rr.student_id JOIN users alumni ON alumni.id = ro.alumni_id "
            . "LEFT JOIN profiles p ON p.user_id = student.id WHERE {$column} = :user_id ORDER BY rr.created_at DESC LIMIT 100"
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function submitReferralRequest(
        int $studentId,
        int $referralId,
        string $message,
        bool $shareProfile,
        bool $shareContact,
        ?array $viewer
    ): bool
    {
        if ($referralId < 1 || !$this->validText($message, 1000, true) || !$this->userHasRole($studentId, 'student')) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
        $visibility = $viewer ? "p.visibility IN ('public', 'logged_in')" : "p.visibility = 'public'";
        $referralStatement = $this->pdo->prepare(
            "SELECT r.alumni_id FROM referral_opportunities r JOIN users u ON u.id = r.alumni_id JOIN profiles p ON p.user_id = u.id "
            . "WHERE r.id = :referral_id AND r.status = 'active' AND r.deadline >= CURRENT_DATE AND u.role = 'alumni' "
            . "AND u.status = 'active' AND p.verification_status = 'verified' AND {$visibility} LIMIT 1"
        );
        $referralStatement->execute([':referral_id' => $referralId]);
        $referralOwner = $referralStatement->fetchColumn();
        if ($referralOwner === false || (int) $referralOwner === $studentId) {
            $this->pdo->rollBack();
            return false;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO referral_requests (referral_opportunity_id, student_id, message, share_profile, share_contact) '
            . 'VALUES (:referral, :student, :message, :share_profile, :share_contact) '
            . 'ON DUPLICATE KEY UPDATE message = IF(status = "pending", VALUES(message), message), '
            . 'share_profile = IF(status = "pending", VALUES(share_profile), share_profile), '
            . 'share_contact = IF(status = "pending", VALUES(share_contact), share_contact)'
        );
        $statement->execute([
            ':referral' => $referralId, ':student' => $studentId, ':message' => $message,
            ':share_profile' => $shareProfile ? 1 : 0, ':share_contact' => $shareContact ? 1 : 0,
        ]);
        $pendingRequest = $this->pdo->prepare(
            'SELECT 1 FROM referral_requests WHERE referral_opportunity_id = :referral AND student_id = :student AND status = "pending" LIMIT 1'
        );
        $pendingRequest->execute([':referral' => $referralId, ':student' => $studentId]);
        $isPending = (bool) $pendingRequest->fetchColumn();
        if ($isPending && $statement->rowCount() > 0) {
            $this->createNotification((int) $referralOwner, 'New referral request', 'A student requested referral consideration for a role you shared.', 'index.php?page=referrals');
        }
        $this->pdo->commit();
        return $isPending;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updateReferralRequest(int $requestId, int $actorId, string $status, bool $shareContact = false): bool
    {
        if ($requestId < 1 || $actorId < 1 || !in_array($status, ['accepted', 'declined', 'cancelled'], true)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
        $requestStatement = $this->pdo->prepare(
            'SELECT rr.student_id, ro.alumni_id, rr.status FROM referral_requests rr '
            . 'JOIN referral_opportunities ro ON ro.id = rr.referral_opportunity_id WHERE rr.id = :id FOR UPDATE'
        );
        $requestStatement->execute([':id' => $requestId]);
        $request = $requestStatement->fetch();
        if (!$request) {
            $this->pdo->rollBack();
            return false;
        }
        $isAlumni = (int) $request['alumni_id'] === $actorId;
        $isStudent = (int) $request['student_id'] === $actorId;
        $validTransition = ($isAlumni && $request['status'] === 'pending' && in_array($status, ['accepted', 'declined'], true))
            || ($isStudent && $request['status'] === 'pending' && $status === 'cancelled');
        if (!$validTransition) {
            $this->pdo->rollBack();
            return false;
        }
        $statement = $this->pdo->prepare(
            'UPDATE referral_requests SET status = :status, alumni_share_contact = :share_contact WHERE id = :id AND status = :previous_status'
        );
        $statement->execute([
            ':status' => $status,
            ':share_contact' => $isAlumni && $status === 'accepted' && $shareContact ? 1 : 0,
            ':id' => $requestId,
            ':previous_status' => $request['status'],
        ]);
        if ($statement->rowCount() !== 1) {
            $this->pdo->rollBack();
            return false;
        }
        $recipientId = $isAlumni ? (int) $request['student_id'] : (int) $request['alumni_id'];
        $this->createNotification($recipientId, 'Referral request updated', 'A referral request status changed to ' . $status . '.', 'index.php?page=referrals');
        $this->pdo->commit();
        return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updateReferralOpportunityStatus(int $referralId, int $alumniId, string $status): bool
    {
        if ($referralId < 1 || !$this->userHasRole($alumniId, 'alumni') || !in_array($status, ['active', 'closed'], true)) {
            return false;
        }
        $this->pdo->beginTransaction();
        try {
            $currentStatement = $this->pdo->prepare(
                'SELECT status FROM referral_opportunities WHERE id = :id AND alumni_id = :alumni FOR UPDATE'
            );
            $currentStatement->execute([':id' => $referralId, ':alumni' => $alumniId]);
            $currentStatus = $currentStatement->fetchColumn();
            if ($currentStatus === false) {
                $this->pdo->rollBack();
                return false;
            }
            if ($currentStatus === $status) {
                $this->pdo->commit();
                return true;
            }
            $statement = $this->pdo->prepare(
                'UPDATE referral_opportunities SET status = :status WHERE id = :id AND alumni_id = :alumni'
            );
            $statement->execute([':status' => $status, ':id' => $referralId, ':alumni' => $alumniId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            $this->writeAudit($alumniId, 'referral_opportunity_' . $status, 'referral_opportunity', $referralId);
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function getApplications(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.opportunity_id, a.status, a.application_date, a.private_note, a.updated_at, o.title, o.company, o.type, o.department, o.deadline, o.status AS opportunity_status FROM applications a JOIN opportunities o ON o.id = a.opportunity_id WHERE a.user_id = :user_id ORDER BY a.updated_at DESC LIMIT 100'
        );
        $statement->execute([':user_id' => $userId]);
        return $statement->fetchAll();
    }

    public function saveApplication(int $userId, int $opportunityId, string $status, string $applicationDate, string $privateNote): bool
    {
        $statuses = ['saved', 'applied', 'interview', 'offer', 'rejected'];
        if (!$this->userHasRole($userId, 'student') || $opportunityId < 1 || !in_array($status, $statuses, true)
            || !$this->validText($privateNote, 4000, true)
            || ($applicationDate !== '' && !$this->validDate($applicationDate))
            || ($applicationDate !== '' && $applicationDate > date('Y-m-d'))) {
            return false;
        }
        if ($status === 'saved') {
            $applicationDate = '';
        }
        $opportunityStatement = $this->pdo->prepare(
            'SELECT status, deadline FROM opportunities WHERE id = :id LIMIT 1'
        );
        $opportunityStatement->execute([':id' => $opportunityId]);
        $opportunity = $opportunityStatement->fetch();
        if (!$opportunity) {
            return false;
        }
        $existing = $this->pdo->prepare('SELECT 1 FROM applications WHERE user_id = :user_id AND opportunity_id = :opportunity_id');
        $existing->execute([':user_id' => $userId, ':opportunity_id' => $opportunityId]);
        $alreadyTracked = (bool) $existing->fetchColumn();
        if (!$alreadyTracked && ($opportunity['status'] !== 'published' || $opportunity['deadline'] < date('Y-m-d'))) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO applications (user_id, opportunity_id, status, application_date, private_note) VALUES (:user_id, :opportunity_id, :status, :application_date, :private_note) ON DUPLICATE KEY UPDATE status = VALUES(status), application_date = VALUES(application_date), private_note = VALUES(private_note)'
        );
        return $statement->execute([
            ':user_id' => $userId, ':opportunity_id' => $opportunityId,
            ':status' => $status, ':application_date' => $applicationDate === '' ? null : $applicationDate,
            ':private_note' => $privateNote === '' ? null : $privateNote,
        ]);
    }

    public function getAlertPreferences(int $userId): array
    {
        $defaults = ['frequency' => 'off', 'department' => '', 'keywords' => ''];
        $statement = $this->pdo->prepare('SELECT frequency, department, keywords FROM alert_preferences WHERE user_id = :user_id');
        $statement->execute([':user_id' => $userId]);
        return array_replace($defaults, $statement->fetch() ?: []);
    }

    public function saveAlertPreferences(int $userId, string $frequency, string $department, string $keywords): bool
    {
        if (!$this->userHasRole($userId, 'student')
            || !in_array($frequency, ['off', 'daily', 'weekly'], true)
            || !$this->validText($department, 100, true)
            || !$this->validText($keywords, 500, true)) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO alert_preferences (user_id, frequency, department, keywords) VALUES (:user_id, :frequency, :department, :keywords) ON DUPLICATE KEY UPDATE frequency = VALUES(frequency), department = VALUES(department), keywords = VALUES(keywords)'
        );
        return $statement->execute([
            ':user_id' => $userId, ':frequency' => $frequency,
            ':department' => $department, ':keywords' => $keywords,
        ]);
    }

    public function getOpportunityAlerts(int $userId): array
    {
        if (!$this->userHasRole($userId, 'student')) {
            return [];
        }
        $preferences = $this->getAlertPreferences($userId);
        if ($preferences['frequency'] === 'off') {
            return [];
        }
        $days = $preferences['frequency'] === 'daily' ? 1 : 7;
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $profileStatement = $this->pdo->prepare(
            'SELECT COALESCE(p.department, d.name, "") FROM users u LEFT JOIN profiles p ON p.user_id = u.id '
            . 'LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = :user_id LIMIT 1'
        );
        $profileStatement->execute([':user_id' => $userId]);
        $profileDepartment = (string) ($profileStatement->fetchColumn() ?: '');
        $department = trim($preferences['department']) ?: trim($profileDepartment);
        $keywords = array_values(array_filter(array_map('trim', explode(',', $preferences['keywords']))));
        $matchingConditions = [];
        $parameters = [':cutoff' => $cutoff];
        if ($department !== '') {
            $matchingConditions[] = 'LOWER(o.department) = LOWER(:department)';
            $parameters[':department'] = $department;
        }
        foreach ($keywords as $index => $keyword) {
            $escapedKeyword = strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $matchingConditions[] = "(o.title LIKE :keyword_title_{$index} ESCAPE '!' OR COALESCE(c.name, o.company) LIKE :keyword_company_{$index} ESCAPE '!' OR o.description LIKE :keyword_description_{$index} ESCAPE '!')";
            $pattern = '%' . $escapedKeyword . '%';
            $parameters[":keyword_title_{$index}"] = $pattern;
            $parameters[":keyword_company_{$index}"] = $pattern;
            $parameters[":keyword_description_{$index}"] = $pattern;
        }
        if (!$matchingConditions) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, '
            . 'o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, '
            . 'o.status FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id '
            . 'WHERE o.status = "published" AND o.deadline >= CURRENT_DATE AND o.published_at >= :cutoff '
            . 'AND (' . implode(' OR ', $matchingConditions) . ') ORDER BY o.published_at DESC, o.id DESC LIMIT 10'
        );
        $statement->execute($parameters);
        $results = $statement->fetchAll();
        foreach ($results as &$opportunity) {
            $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        }
        unset($opportunity);
        return $results;
    }

    public function recordOpportunityEvent(int $opportunityId, ?int $userId, string $eventType): bool
    {
        if ($opportunityId < 1 || !in_array($eventType, ['view', 'click'], true)) {
            return false;
        }
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT']
            : '';
        if ($userAgent !== '' && preg_match('/bot|crawler|spider|slurp|facebookexternalhit|preview/i', $userAgent)) {
            return false;
        }
        $visitorHash = $userId === null ? hash('sha256', session_id()) : null;
        $duplicateSql = $userId === null
            ? 'SELECT 1 FROM opportunity_events WHERE opportunity_id = :opportunity_id AND event_type = :event_type AND visitor_hash = :visitor_hash AND occurred_at >= CURRENT_DATE LIMIT 1'
            : 'SELECT 1 FROM opportunity_events WHERE opportunity_id = :opportunity_id AND event_type = :event_type AND user_id = :user_id AND occurred_at >= CURRENT_DATE LIMIT 1';
        $duplicate = $this->pdo->prepare($duplicateSql);
        $duplicateParameters = [
            ':opportunity_id' => $opportunityId,
            ':event_type' => $eventType,
        ];
        if ($userId === null) {
            $duplicateParameters[':visitor_hash'] = $visitorHash;
        } else {
            $duplicateParameters[':user_id'] = $userId;
        }
        $duplicate->execute($duplicateParameters);
        if ($duplicate->fetchColumn()) {
            return true;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO opportunity_events (opportunity_id, user_id, visitor_hash, event_type) VALUES (:opportunity_id, :user_id, :visitor_hash, :event_type)'
        );
        return $statement->execute([
            ':opportunity_id' => $opportunityId,
            ':user_id' => $userId,
            ':visitor_hash' => $visitorHash,
            ':event_type' => $eventType,
        ]);
    }

    public function getEngagementStats(): array
    {
        $count = fn (string $sql): int => (int) $this->pdo->query($sql)->fetchColumn();
        $applicationRows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM applications GROUP BY status')->fetchAll();
        $applications = array_fill_keys(['saved', 'applied', 'interview', 'offer', 'rejected'], 0);
        foreach ($applicationRows as $row) {
            $applications[$row['status']] = (int) $row['total'];
        }
        $mentorshipRows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM mentorship_requests GROUP BY status')->fetchAll();
        $mentorship = array_fill_keys(['pending', 'accepted', 'declined', 'completed', 'cancelled'], 0);
        foreach ($mentorshipRows as $row) {
            if (isset($mentorship[$row['status']])) {
                $mentorship[$row['status']] = (int) $row['total'];
            }
        }
        $referralRows = $this->pdo->query('SELECT status, COUNT(*) AS total FROM referral_requests GROUP BY status')->fetchAll();
        $referrals = array_fill_keys(['pending', 'accepted', 'declined', 'cancelled'], 0);
        foreach ($referralRows as $row) {
            if (isset($referrals[$row['status']])) {
                $referrals[$row['status']] = (int) $row['total'];
            }
        }
        return [
            'active_users' => $count("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'verified_alumni' => $count("SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id WHERE u.role = 'alumni' AND u.status = 'active' AND p.verification_status = 'verified'"),
            'opportunity_views' => $count("SELECT COUNT(*) FROM opportunity_events WHERE event_type = 'view'"),
            'opportunity_clicks' => $count("SELECT COUNT(*) FROM opportunity_events WHERE event_type = 'click'"),
            'mentorship' => $mentorship,
            'referrals' => $referrals,
            'applications' => $applications,
        ];
    }

    public function getCareerActivity(int $userId): array
    {
        $activity = [
            'applications' => 0,
            'mentorship_requests' => 0,
            'referral_requests' => 0,
        ];
        foreach ([
            'applications' => 'SELECT COUNT(*) FROM applications WHERE user_id = :user_id',
            'referral_requests' => 'SELECT COUNT(*) FROM referral_requests WHERE student_id = :user_id',
        ] as $key => $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([':user_id' => $userId]);
            $activity[$key] = (int) $statement->fetchColumn();
        }
        $mentorshipColumn = $this->userHasRole($userId, 'alumni') ? 'alumni_id' : 'student_id';
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM mentorship_requests WHERE {$mentorshipColumn} = :user_id"
        );
        $statement->execute([':user_id' => $userId]);
        $activity['mentorship_requests'] = (int) $statement->fetchColumn();
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM referral_requests rr JOIN referral_opportunities ro ON ro.id = rr.referral_opportunity_id WHERE ro.alumni_id = :user_id'
        );
        $statement->execute([':user_id' => $userId]);
        if ($this->userHasRole($userId, 'alumni')) {
            $activity['referral_requests'] = (int) $statement->fetchColumn();
        }
        return $activity;
    }

    public function getRecommendedOpportunities(array $user): array
    {
        if (!$user || ($user['role'] ?? '') !== 'student') {
            return [];
        }
        $profileStatement = $this->pdo->prepare(
            'SELECT department, skills FROM profiles WHERE user_id = :user_id LIMIT 1'
        );
        $profileStatement->execute([':user_id' => (int) $user['id']]);
        $profile = $profileStatement->fetch();
        if (!$profile) {
            return [];
        }
        $department = trim((string) $profile['department']);
        $skills = (string) ($profile['skills'] ?? '');
        $keywords = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,;]+/', $skills) ?: []
        )));
        $conditions = [];
        $parameters = [];
        if ($department !== '') {
            $conditions[] = 'LOWER(o.department) = LOWER(:department)';
            $parameters[':department'] = $department;
        }
        foreach ($keywords as $index => $keyword) {
            if (strlen($keyword) < 2) {
                continue;
            }
            $pattern = '%' . $keyword . '%';
            $titleParameter = ':keyword_title_' . $index;
            $companyParameter = ':keyword_company_' . $index;
            $descriptionParameter = ':keyword_description_' . $index;
            $conditions[] = "(o.title LIKE {$titleParameter} OR o.company LIKE {$companyParameter} OR o.description LIKE {$descriptionParameter})";
            $parameters[$titleParameter] = $pattern;
            $parameters[$companyParameter] = $pattern;
            $parameters[$descriptionParameter] = $pattern;
        }
        if (!$conditions) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, '
            . 'o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, o.status, '
            . 'o.company_id, c.name AS company_name FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id '
            . 'WHERE o.status = "published" AND o.deadline >= CURRENT_DATE AND (' . implode(' OR ', $conditions) . ') '
            . 'ORDER BY o.published_at DESC, o.id DESC LIMIT 5'
        );
        $statement->execute($parameters);
        $opportunities = $statement->fetchAll();
        foreach ($opportunities as &$opportunity) {
            $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        }
        unset($opportunity);
        return $opportunities;
    }

    private function userHasRole(int $userId, string $role): bool
    {
        if ($userId < 1 || !in_array($role, ['student', 'alumni', 'admin'], true)) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :id AND role = :role AND status = "active"');
        $statement->execute([':id' => $userId, ':role' => $role]);
        return (bool) $statement->fetchColumn();
    }

    private function userHasVerifiedAlumni(int $userId): bool
    {
        if (!$this->userHasRole($userId, 'alumni')) {
            return false;
        }
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM profiles WHERE user_id = :user_id AND verification_status = 'verified' LIMIT 1"
        );
        $statement->execute([':user_id' => $userId]);
        return (bool) $statement->fetchColumn();
    }

    private function validText(string $value, int $maxLength, bool $allowEmpty = false): bool
    {
        $length = $this->characterCount($value);
        return ($allowEmpty || $value !== '') && $length <= $maxLength;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validHttpUrl(string $value): bool
    {
        return $this->isValidExternalUrl($value);
    }

    public function getHomeStats(?array $viewer = null): array
    {
        $visibility = $viewer
            ? "p.visibility IN ('public', 'logged_in')"
            : "p.visibility = 'public'";
        $count = fn (string $sql): int => (int) $this->pdo->query($sql)->fetchColumn();
        return [
            'student_profiles' => $count(
                "SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id WHERE u.role = 'student' AND u.status = 'active'"
            ),
            'verified_alumni' => $count(
                "SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id WHERE u.role = 'alumni' AND u.status = 'active' AND p.verification_status = 'verified' AND {$visibility}"
            ),
            'mentors' => $count(
                "SELECT COUNT(*) FROM mentorship_profiles m JOIN profiles p ON p.user_id = m.user_id JOIN users u ON u.id = p.user_id WHERE u.role = 'alumni' AND u.status = 'active' AND p.verification_status = 'verified' AND {$visibility} AND m.accepting_requests = 1"
            ),
            'published_opportunities' => $count(
                "SELECT COUNT(*) FROM opportunities WHERE status = 'published' AND deadline >= CURRENT_DATE"
            ),
        ];
    }

    public function getOpportunities(): array
    {
        $statement = $this->pdo->query(
            'SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, '
            . 'o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, o.status, '
            . 'o.company_id, c.name AS company_name FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id '
            . 'ORDER BY o.published_at DESC, o.id DESC'
        );
        $opportunities = $statement->fetchAll();
        foreach ($opportunities as &$opportunity) {
            $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        }
        unset($opportunity);
        return $opportunities;
    }

    public function getFeaturedOpportunities(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, "
            . "o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, o.status, "
            . "o.company_id FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id "
            . "WHERE o.status = 'published' AND o.deadline >= :today "
            . 'ORDER BY o.published_at DESC, o.id DESC LIMIT 3'
        );
        $statement->execute([':today' => date('Y-m-d')]);
        $opportunities = $statement->fetchAll();
        foreach ($opportunities as &$opportunity) {
            $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        }
        unset($opportunity);
        return $opportunities;
    }

    public function searchOpportunities(array $filters): array
    {
        return $this->getOpportunitySearchPage($filters)['items'];
    }

    public function getOpportunitySearchPage(array $filters, int $page = 1, int $pageSize = 12): array
    {
        $keyword = $this->inputString($filters, 'keyword');
        $type = $this->inputString($filters, 'type');
        $department = $this->inputString($filters, 'department');
        $location = $this->inputString($filters, 'location');
        $remote = $this->inputString($filters, 'remote');
        $sort = $this->inputString($filters, 'sort');
        if (!in_array($sort, ['deadline', 'title'], true)) {
            $sort = '';
        }
        $page = max(1, $page);
        $pageSize = max(1, min(50, $pageSize));
        $conditions = ["o.status = 'published'", 'o.deadline >= CURRENT_DATE'];
        $parameters = [];
        if ($keyword !== '') {
            $escapedKeyword = strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $keywordConditions = [];
            foreach (['title', 'description'] as $field) {
                $keywordConditions[] = "o.{$field} LIKE :{$field}_keyword ESCAPE '!'";
                $parameters[":{$field}_keyword"] = '%' . $escapedKeyword . '%';
            }
            $keywordConditions[] = "COALESCE(c.name, o.company) LIKE :company_keyword ESCAPE '!'";
            $parameters[':company_keyword'] = '%' . $escapedKeyword . '%';
            $conditions[] = '(' . implode(' OR ', $keywordConditions) . ')';
        }
        if (in_array($type, ['Job', 'Internship'], true)) {
            $conditions[] = 'o.type = :type';
            $parameters[':type'] = $type;
        }
        if ($department !== '') {
            $conditions[] = 'LOWER(o.department) = LOWER(:department)';
            $parameters[':department'] = $department;
        }
        if ($location !== '') {
            $conditions[] = 'o.location = :location';
            $parameters[':location'] = $location;
        }
        if ($remote !== '') {
            $conditions[] = 'o.remote_mode = :remote';
            $parameters[':remote'] = $remote;
        }
        $where = implode(' AND ', $conditions);
        $countStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id WHERE ' . $where
        );
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $orderBy = match ($sort) {
            'deadline' => 'o.deadline ASC, o.published_at DESC, o.id DESC',
            'title' => 'o.title ASC, o.id DESC',
            default => 'o.published_at DESC, o.id DESC',
        };
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, '
            . 'o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, o.status, '
            . 'o.company_id FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id WHERE '
            . $where . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        $opportunities = $statement->fetchAll();
        foreach ($opportunities as &$opportunity) {
            $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        }
        unset($opportunity);
        return [
            'items' => $opportunities,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function getOpportunityLocations(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT location FROM opportunities WHERE status = 'published' AND deadline >= CURRENT_DATE AND location <> '' ORDER BY location"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getOpportunityRemoteModes(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT remote_mode FROM opportunities WHERE status = 'published' AND deadline >= CURRENT_DATE AND remote_mode <> '' ORDER BY remote_mode"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function isValidExternalUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        foreach (['example.com', 'example.org', 'example.net', 'example.test', 'localhost'] as $placeholderDomain) {
            if ($host === $placeholderDomain || str_ends_with($host, '.' . $placeholderDomain)) {
                return false;
            }
        }
        return true;
    }

    public function getActiveOpportunity(int $opportunityId): ?array
    {
        if ($opportunityId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.title, COALESCE(c.name, o.company) AS company, o.type, o.department, o.location, '
            . 'o.remote_mode AS remote, o.deadline, o.published_at, o.description, o.application_url, o.status, '
            . 'o.company_id FROM opportunities o LEFT JOIN companies c ON c.id = o.company_id '
            . 'WHERE o.id = :id AND o.status = "published" AND o.deadline >= CURRENT_DATE LIMIT 1'
        );
        $statement->execute([':id' => $opportunityId]);
        $opportunity = $statement->fetch();
        if (!$opportunity) {
            return null;
        }
        $opportunity['application_available'] = $this->isValidExternalUrl((string) $opportunity['application_url']);
        return $opportunity;
    }

    private function queryAlumni(array $filters, ?array $viewer, bool $includeUnverified, ?int $limit = null, int $offset = 0, ?int $companyId = null): array
    {
        [$where, $params] = $this->alumniFilterSql($filters, $viewer, $includeUnverified);
        if ($companyId !== null) {
            $where .= ' AND p.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $sql = 'SELECT p.id AS profile_id, u.id, u.name, u.email, p.department, p.graduation_year, '
            . 'COALESCE(c.name, p.current_company, "") AS company, p.company_id, p.job_title, '
            . 'COALESCE(c.industry, p.industry, "") AS industry, '
            . 'CASE WHEN p.email_contact_visibility = "logged_in" AND :viewer_email IS NOT NULL THEN u.email ELSE NULL END AS contact_email, '
            . 'p.email_contact_visibility AS email_visibility, p.verification_status, p.review_note, p.visibility, p.bio, p.skills, p.linkedin_url '
            . "FROM profiles p JOIN users u ON u.id = p.user_id LEFT JOIN companies c ON c.id = p.company_id WHERE u.role = 'alumni' AND u.status = 'active' {$where} "
            . 'ORDER BY p.graduation_year DESC, p.full_name ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':viewer_email', $viewer ? $viewer['email'] : null, $viewer ? PDO::PARAM_STR : PDO::PARAM_NULL);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        if ($limit !== null) {
            $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
            $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }
        $statement->execute();
        $people = [];
        foreach ($statement->fetchAll() as $row) {
            $row['verified'] = $row['verification_status'] === 'verified';
            $row['email_contact'] = $row['contact_email'] !== null;
            $row['email'] = $row['contact_email'] ?? '';
            unset($row['contact_email']);
            $row['skills'] = array_values(array_filter(array_map('trim', explode(',', (string) ($row['skills'] ?? '')))));
            $people[] = $row;
        }
        return $people;
    }

    private function countAlumni(array $filters, ?array $viewer, ?int $companyId = null): int
    {
        [$where, $params] = $this->alumniFilterSql($filters, $viewer, false);
        if ($companyId !== null) {
            $where .= ' AND p.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM profiles p JOIN users u ON u.id = p.user_id LEFT JOIN companies c ON c.id = p.company_id WHERE u.role = 'alumni' AND u.status = 'active' {$where}"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    private function alumniFilterSql(array $filters, ?array $viewer, bool $includeUnverified): array
    {
        $clauses = [];
        $params = [];
        if ($includeUnverified) {
            $clauses[] = "p.verification_status IN ('pending', 'rejected')";
        } else {
            $clauses[] = "p.verification_status = 'verified'";
            $clauses[] = "p.visibility <> 'private'";
            if (!$viewer) {
                $clauses[] = "p.visibility = 'public'";
            }
        }
        $fields = [
            'name' => 'p.full_name',
            'department' => 'p.department',
            'company' => 'COALESCE(c.name, p.current_company, "")',
            'industry' => 'COALESCE(c.industry, p.industry, "")',
            'job_title' => 'p.job_title',
        ];
        foreach ($fields as $key => $field) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $parameter = ':filter_' . $key;
                $clauses[] = "{$field} LIKE {$parameter} ESCAPE '!'";
                $params[$parameter] = '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            }
        }
        $year = trim((string) ($filters['graduation_year'] ?? ''));
        if ($year !== '' && ctype_digit($year)) {
            $clauses[] = 'p.graduation_year = :filter_graduation_year';
            $params[':filter_graduation_year'] = (int) $year;
        }
        return [' AND ' . implode(' AND ', $clauses), $params];
    }

    private function companyFilterSql(array $filters): array
    {
        $clauses = [];
        $params = [];
        foreach (['keyword' => ['c.name', 'c.industry', 'c.description'], 'industry' => ['c.industry'], 'location' => ['c.location']] as $key => $columns) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $searches = [];
            foreach ($columns as $index => $column) {
                $parameter = ':company_' . $key . '_' . $index;
                $searches[] = "{$column} LIKE {$parameter} ESCAPE '!'";
                $params[$parameter] = '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            }
            $clauses[] = '(' . implode(' OR ', $searches) . ')';
        }
        return [$clauses ? ' AND ' . implode(' AND ', $clauses) : '', $params];
    }

    private function queryCompanies(array $filters, ?array $viewer, ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->companyFilterSql($filters);
        $visibleClause = $viewer
            ? "p.visibility IN ('public', 'logged_in')"
            : "p.visibility = 'public'";
        $sql = 'SELECT c.id, c.name, c.industry, c.website, c.location, c.description, '
            . "COUNT(DISTINCT p.id) AS alumni_count FROM companies c LEFT JOIN profiles p ON p.company_id = c.id AND p.verification_status = 'verified' AND {$visibleClause} AND p.user_id IN (SELECT id FROM users WHERE role = 'alumni' AND status = 'active') "
            . "WHERE c.status = 'active' {$where} GROUP BY c.id, c.name, c.industry, c.website, c.location, c.description ORDER BY c.name ASC";
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        if ($limit !== null) {
            $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
            $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    private function inputString(array $input, string $key): string
    {
        return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
    }

    private function characterCount(string $value): int
    {
        $count = preg_match_all('/./us', $value);
        return $count === false ? PHP_INT_MAX : $count;
    }

    private function writeAudit(?int $actorId, string $action, string $targetType, ?int $targetId, array $details = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_audit_log (actor_id, action, target_type, target_id, details) VALUES (:actor_id, :action, :target_type, :target_id, :details)'
        );
        $statement->execute([
            ':actor_id' => $actorId,
            ':action' => $action,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':details' => $details ? json_encode($details, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    private function createNotification(int $userId, string $title, string $message, string $link): void
    {
        if ($userId < 1 || !$this->validText($title, 160) || !$this->validText($message, 500)
            || !$this->validText($link, 255)) {
            throw new InvalidArgumentException('Notification data is invalid.');
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, link) VALUES (:user_id, :title, :message, :link)'
        );
        $statement->execute([':user_id' => $userId, ':title' => $title, ':message' => $message, ':link' => $link]);
    }

    private function closeExpiredOpportunities(): void
    {
        $expired = $this->pdo->query(
            "SELECT id FROM opportunities WHERE status = 'published' AND deadline < CURRENT_DATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!$expired) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare("UPDATE opportunities SET status = 'closed' WHERE id = :id AND status = 'published'");
            foreach ($expired as $id) {
                $update->execute([':id' => (int) $id]);
                if ($update->rowCount() === 1) {
                    $this->writeAudit(null, 'opportunity_auto_closed', 'opportunity', (int) $id);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
