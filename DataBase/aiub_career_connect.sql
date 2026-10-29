-- Consolidated current schema for new installations (database schema through increment 7).
-- Import only into a new/empty database. Existing installations must use the upgrade scripts.
CREATE DATABASE IF NOT EXISTS aiub_career_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aiub_career_connect;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    industry VARCHAR(100) NOT NULL DEFAULT '',
    website VARCHAR(255) DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    description TEXT,
    status ENUM('active', 'inactive', 'merged') NOT NULL DEFAULT 'active',
    merged_into_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merged_into_id) REFERENCES companies(id),
    INDEX idx_companies_name_location (name(150), location(40)),
    INDEX idx_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'alumni', 'admin') NOT NULL,
    status ENUM('active', 'pending', 'inactive') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    student_id VARCHAR(50) DEFAULT NULL,
    department VARCHAR(100) NOT NULL,
    degree VARCHAR(180) NOT NULL,
    graduation_year INT DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    skills TEXT,
    bio TEXT,
    portfolio_url VARCHAR(255) DEFAULT NULL,
    linkedin_url VARCHAR(255) DEFAULT NULL,
    current_company VARCHAR(150) DEFAULT NULL,
    company_id INT DEFAULT NULL,
    job_title VARCHAR(150) DEFAULT NULL,
    industry VARCHAR(100) DEFAULT NULL,
    email_contact ENUM('yes', 'no') DEFAULT 'no',
    email_contact_visibility ENUM('logged_in', 'disabled') NOT NULL DEFAULT 'disabled',
    visibility ENUM('public', 'logged_in', 'private') DEFAULT 'public',
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    review_note TEXT DEFAULT NULL,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_profiles_alumni_review (verification_status, updated_at),
    INDEX idx_profiles_directory (department, graduation_year, visibility),
    INDEX idx_profiles_job (industry, job_title),
    INDEX idx_profiles_company_visibility (company_id, verification_status, visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    company VARCHAR(150) NOT NULL,
    type ENUM('Internship', 'Job') NOT NULL,
    department VARCHAR(100) NOT NULL,
    location VARCHAR(120) NOT NULL,
    remote_mode VARCHAR(50) NOT NULL,
    deadline DATE NOT NULL,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT NOT NULL,
    application_url VARCHAR(255) NOT NULL,
    status ENUM('draft', 'published', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    company_id INT DEFAULT NULL,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    INDEX idx_opportunities_status_deadline (status, deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alumni_directory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(150) DEFAULT NULL,
    job_title VARCHAR(150) DEFAULT NULL,
    industry VARCHAR(100) DEFAULT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    email_contact_visible ENUM('public', 'logged_in', 'disabled') NOT NULL DEFAULT 'disabled',
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT DEFAULT NULL,
    details JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id),
    INDEX idx_audit_target (target_type, target_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    status ENUM('open', 'reviewed', 'dismissed') NOT NULL DEFAULT 'open',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES profiles(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_reports_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mentorship_profiles (
    user_id INT PRIMARY KEY,
    topics VARCHAR(500) NOT NULL DEFAULT '',
    expertise TEXT,
    availability VARCHAR(300) NOT NULL DEFAULT '',
    accepting_requests TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mentorship_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    alumni_id INT NOT NULL,
    topic VARCHAR(150) NOT NULL,
    goal VARCHAR(1000) NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    share_profile TINYINT(1) NOT NULL DEFAULT 0,
    share_contact TINYINT(1) NOT NULL DEFAULT 0,
    alumni_share_contact TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (alumni_id) REFERENCES users(id),
    INDEX idx_mentorship_student (student_id, created_at),
    INDEX idx_mentorship_alumni (alumni_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE referral_opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    company VARCHAR(150) NOT NULL,
    type ENUM('Internship', 'Job') NOT NULL,
    department VARCHAR(100) NOT NULL,
    deadline DATE NOT NULL,
    description TEXT NOT NULL,
    application_url VARCHAR(255) NOT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES users(id),
    INDEX idx_referral_opportunities_status (status, deadline),
    INDEX idx_referral_opportunities_owner (alumni_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE referral_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referral_opportunity_id INT NOT NULL,
    student_id INT NOT NULL,
    message VARCHAR(1000) NOT NULL DEFAULT '',
    status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    share_profile TINYINT(1) NOT NULL DEFAULT 0,
    share_contact TINYINT(1) NOT NULL DEFAULT 0,
    alumni_share_contact TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referral_opportunity_id) REFERENCES referral_opportunities(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    UNIQUE KEY uq_referral_student (referral_opportunity_id, student_id),
    INDEX idx_referral_requests_student (student_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    opportunity_id INT NOT NULL,
    status ENUM('saved', 'applied', 'interview', 'offer', 'rejected') NOT NULL DEFAULT 'saved',
    application_date DATE DEFAULT NULL,
    private_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    UNIQUE KEY uq_application_user_opportunity (user_id, opportunity_id),
    INDEX idx_applications_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alert_preferences (
    user_id INT PRIMARY KEY,
    frequency ENUM('off', 'daily', 'weekly') NOT NULL DEFAULT 'off',
    department VARCHAR(100) NOT NULL DEFAULT '',
    keywords VARCHAR(500) NOT NULL DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE opportunity_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    visitor_hash CHAR(64) DEFAULT NULL,
    event_type ENUM('view', 'click') NOT NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_opportunity_events_type_time (event_type, occurred_at),
    INDEX idx_opportunity_events_opportunity (opportunity_id, event_type),
    INDEX idx_opportunity_events_visitor (visitor_hash, event_type, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email (email_hash, succeeded, attempted_at),
    INDEX idx_login_attempts_ip (ip_hash, succeeded, attempted_at),
    INDEX idx_login_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments (name) VALUES
('Computer Science'),
('Business Administration'),
('EEE');
