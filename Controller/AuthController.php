<?php
class AuthController
{
    private DataRepository $repository;

    public function __construct(DataRepository $repository)
    {
        $this->repository = $repository;
    }

    public function currentUser(): ?array
    {
        if (!isset($_SESSION['user'])) {
            return null;
        }

        $user = $this->repository->findUserById((int) ($_SESSION['user']['id'] ?? 0));
        if (!$user) {
            unset($_SESSION['user']);
            return null;
        }
        $_SESSION['user'] = $this->sessionIdentity($user);
        return $_SESSION['user'];
    }

    public function login(array $post): array
    {
        $email = trim((string) ($post['email'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR']
            : 'unknown';

        if (!$this->repository->isLoginAllowed($email, $ipAddress)) {
            return ['success' => false, 'message' => 'Too many sign-in attempts. Wait 15 minutes and try again.'];
        }
        if ($email === '' || $password === '') {
            $this->repository->recordLoginAttempt($email, $ipAddress, false);
            return ['success' => false, 'message' => 'Email and password are required.'];
        }

        $user = $this->repository->findUserByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->repository->recordLoginAttempt($email, $ipAddress, false);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        $this->repository->recordLoginAttempt($email, $ipAddress, true);
        $_SESSION['user'] = $this->sessionIdentity($user);

        return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!'];
    }

    public function register(array $post): array
    {
        $name = trim((string) ($post['name'] ?? ''));
        $email = trim((string) ($post['email'] ?? ''));
        $role = trim((string) ($post['role'] ?? 'student'));
        $department = trim((string) ($post['department'] ?? ''));
        $studentId = trim((string) ($post['student_id'] ?? ''));
        $graduationYear = trim((string) ($post['graduation_year'] ?? ''));
        $password = (string) ($post['password'] ?? '');
        $confirm = (string) ($post['confirm_password'] ?? '');

        if (isset($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Log out before creating another account.'];
        }
        if ($name === '' || $email === '' || $department === '' || $studentId === '' || $graduationYear === '' || $password === '' || $confirm === '') {
            return ['success' => false, 'message' => 'Please complete all fields.'];
        }

        $nameLength = preg_match_all('/./us', $name);
        $emailLength = preg_match_all('/./us', $email);
        $departmentLength = preg_match_all('/./us', $department);
        $studentIdLength = preg_match_all('/./us', $studentId);
        $graduationYearValue = filter_var($graduationYear, FILTER_VALIDATE_INT);
        if ($nameLength === false || $nameLength > 150) {
            return ['success' => false, 'message' => 'Enter a name of 150 characters or fewer.'];
        }
        if ($emailLength === false || $emailLength > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['success' => false, 'message' => 'Enter a valid email address of 180 characters or fewer.'];
        }
        if ($departmentLength === false || $departmentLength > 100) {
            return ['success' => false, 'message' => 'Choose a department with 100 characters or fewer.'];
        }
        if ($studentIdLength === false || $studentIdLength > 50) {
            return ['success' => false, 'message' => 'Enter a student ID of 50 characters or fewer.'];
        }
        if ($graduationYearValue === false || $graduationYearValue < 1900 || $graduationYearValue > (int) date('Y') + 10) {
            return ['success' => false, 'message' => 'Enter a graduation year between 1900 and ' . ((int) date('Y') + 10) . '.'];
        }
        if (strtolower((string) substr(strrchr($email, '@') ?: '', 1)) !== 'aiub.edu') {
            return ['success' => false, 'message' => 'Register with your AIUB email address ending in @aiub.edu.'];
        }
        if ($password !== $confirm) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            return ['success' => false, 'message' => 'Use a password between 8 and 72 characters.'];
        }

        if (!in_array($role, ['student', 'alumni'], true)) {
            return ['success' => false, 'message' => 'Invalid account type selected.'];
        }

        try {
            $user = $this->repository->createUser([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'department' => $department,
                'student_id' => $studentId,
                'graduation_year' => $graduationYear,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                return ['success' => false, 'message' => 'An account with this email already exists.'];
            }
            throw $error;
        }

        if (!$user) {
            return ['success' => false, 'message' => 'The account could not be created. Review the submitted fields.'];
        }
        $_SESSION['user'] = $this->sessionIdentity($user);

        return ['success' => true, 'message' => 'Your account has been created successfully.'];
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }

    private function sessionIdentity(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'department' => (string) ($user['department'] ?? ''),
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
        ];
    }
}
