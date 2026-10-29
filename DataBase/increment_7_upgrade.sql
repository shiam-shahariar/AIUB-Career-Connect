USE aiub_career_connect;

ALTER TABLE login_attempts
    ADD INDEX idx_login_attempts_attempted_at (attempted_at);
