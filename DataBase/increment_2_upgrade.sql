USE aiub_career_connect;

CREATE TABLE IF NOT EXISTS companies (
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
    INDEX idx_companies_name_location (name, location),
    INDEX idx_companies_status (status)
);

ALTER TABLE profiles
    ADD COLUMN company_id INT DEFAULT NULL AFTER current_company,
    ADD COLUMN email_contact_visibility ENUM('public', 'logged_in', 'disabled') NOT NULL DEFAULT 'disabled' AFTER email_contact,
    ADD COLUMN verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending' AFTER visibility,
    ADD COLUMN review_note TEXT DEFAULT NULL AFTER verification_status,
    ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER review_note,
    ADD COLUMN verified_by INT DEFAULT NULL AFTER verified_at,
    ADD INDEX idx_profiles_alumni_review (verification_status, updated_at),
    ADD INDEX idx_profiles_directory (department, graduation_year, visibility),
    ADD INDEX idx_profiles_job (industry, job_title),
    ADD INDEX idx_profiles_company_visibility (company_id, verification_status, visibility),
    ADD CONSTRAINT fk_profiles_company_id FOREIGN KEY (company_id) REFERENCES companies(id),
    ADD CONSTRAINT fk_profiles_verified_by FOREIGN KEY (verified_by) REFERENCES users(id);

ALTER TABLE opportunities
    MODIFY COLUMN status ENUM('draft', 'published', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    ADD COLUMN company_id INT DEFAULT NULL AFTER status,
    ADD INDEX idx_opportunities_status_deadline (status, deadline),
    ADD CONSTRAINT fk_opportunities_company_id FOREIGN KEY (company_id) REFERENCES companies(id);

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT DEFAULT NULL,
    details JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id),
    INDEX idx_audit_target (target_type, target_id, created_at)
);

CREATE TABLE IF NOT EXISTS profile_reports (
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
);

UPDATE opportunities o
JOIN companies c ON LOWER(TRIM(c.name)) = LOWER(TRIM(o.company))
SET o.company_id = c.id
WHERE o.company_id IS NULL;

INSERT INTO alumni_directory (user_id, company_name, job_title, industry, verification_status, email_contact_visible)
SELECT u.id, p.current_company, p.job_title, p.industry, p.verification_status, 'disabled'
FROM users u JOIN profiles p ON p.user_id = u.id
WHERE u.role = 'alumni'
  AND NOT EXISTS (SELECT 1 FROM alumni_directory d WHERE d.user_id = u.id);
