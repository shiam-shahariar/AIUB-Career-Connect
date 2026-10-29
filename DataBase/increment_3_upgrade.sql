USE aiub_career_connect;

CREATE TABLE IF NOT EXISTS mentorship_profiles (
    user_id INT PRIMARY KEY,
    topics VARCHAR(500) NOT NULL DEFAULT '',
    expertise TEXT,
    availability VARCHAR(300) NOT NULL DEFAULT '',
    accepting_requests TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS mentorship_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    alumni_id INT NOT NULL,
    topic VARCHAR(150) NOT NULL,
    goal VARCHAR(1000) NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (alumni_id) REFERENCES users(id),
    INDEX idx_mentorship_student (student_id, created_at),
    INDEX idx_mentorship_alumni (alumni_id, status, created_at)
);

CREATE TABLE IF NOT EXISTS referral_opportunities (
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
);

CREATE TABLE IF NOT EXISTS referral_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referral_opportunity_id INT NOT NULL,
    student_id INT NOT NULL,
    message VARCHAR(1000) NOT NULL DEFAULT '',
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referral_opportunity_id) REFERENCES referral_opportunities(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    UNIQUE KEY uq_referral_student (referral_opportunity_id, student_id),
    INDEX idx_referral_requests_student (student_id, created_at)
);

CREATE TABLE IF NOT EXISTS applications (
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
);

CREATE TABLE IF NOT EXISTS alert_preferences (
    user_id INT PRIMARY KEY,
    frequency ENUM('off', 'daily', 'weekly') NOT NULL DEFAULT 'off',
    department VARCHAR(100) NOT NULL DEFAULT '',
    keywords VARCHAR(500) NOT NULL DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS opportunity_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    event_type ENUM('view', 'click') NOT NULL,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_opportunity_events_type_time (event_type, occurred_at),
    INDEX idx_opportunity_events_opportunity (opportunity_id, event_type)
);
