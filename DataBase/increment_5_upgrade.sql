USE aiub_career_connect;

ALTER DATABASE aiub_career_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE companies
    DROP INDEX idx_companies_name_location,
    ADD INDEX idx_companies_name_location (name(150), location(40));
ALTER TABLE departments CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE companies CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE profiles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE opportunities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE alumni_directory CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE admin_audit_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE profile_reports CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mentorship_profiles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mentorship_requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE referral_opportunities CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE referral_requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE applications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE alert_preferences CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE opportunity_events CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
UPDATE profiles SET email_contact_visibility = 'logged_in' WHERE email_contact_visibility = 'public';
ALTER TABLE profiles MODIFY email_contact_visibility ENUM('logged_in', 'disabled') NOT NULL DEFAULT 'disabled';
ALTER TABLE mentorship_requests
    MODIFY status ENUM('pending', 'accepted', 'declined', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN share_profile TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN share_contact TINYINT(1) NOT NULL DEFAULT 0 AFTER share_profile,
    ADD COLUMN alumni_share_contact TINYINT(1) NOT NULL DEFAULT 0 AFTER share_contact;
ALTER TABLE referral_requests
    MODIFY status ENUM('pending', 'accepted', 'declined', 'cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN share_profile TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN share_contact TINYINT(1) NOT NULL DEFAULT 0 AFTER share_profile,
    ADD COLUMN alumni_share_contact TINYINT(1) NOT NULL DEFAULT 0 AFTER share_contact;
ALTER TABLE opportunity_events
    ADD COLUMN visitor_hash CHAR(64) DEFAULT NULL AFTER user_id,
    ADD INDEX idx_opportunity_events_visitor (visitor_hash, event_type, occurred_at);

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
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email (email_hash, succeeded, attempted_at),
    INDEX idx_login_attempts_ip (ip_hash, succeeded, attempted_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
