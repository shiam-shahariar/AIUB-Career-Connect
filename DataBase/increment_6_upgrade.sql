USE aiub_career_connect;

ALTER TABLE profiles
    ADD COLUMN student_id VARCHAR(50) DEFAULT NULL AFTER full_name,
    ADD COLUMN graduation_year INT DEFAULT NULL AFTER degree;
