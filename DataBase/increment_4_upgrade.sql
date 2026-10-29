USE aiub_career_connect;

UPDATE users
SET status = 'inactive',
    password_hash = CONCAT('!disabled-seed-account-', id)
WHERE email IN ('student@aiub.edu', 'alumni@aiub.edu', 'admin@aiub.edu')
  AND password_hash IN (
      '$2y$10$cgIeowUZ5AQIG.53qdCjc.WLKqGpzskp2Nmy7Dmuth4GBnsQ8FGoS',
      '$2y$10$IX2PJI9ZSAKaVgeL57cd.em7uzYxUpj.Ajyq6kRbH0kRTlhRvHrza',
      '$2y$10$qfngcj55NVtL5ZnoOcEmB.UlEvtsO9G2JXgUlTru1HGpTd2XW3xum'
  );

UPDATE users
SET status = 'inactive',
    password_hash = CONCAT('!disabled-seed-account-', id)
WHERE email LIKE '%@example.test'
  AND password_hash = '!directory-demo-account';

UPDATE profiles
SET visibility = 'private',
    verification_status = 'rejected',
    email_contact = 'no',
    email_contact_visibility = 'disabled'
WHERE user_id IN (
    SELECT id FROM users
    WHERE status = 'inactive'
      AND (email LIKE '%@example.test' OR email IN ('student@aiub.edu', 'alumni@aiub.edu', 'admin@aiub.edu'))
);

UPDATE companies
SET status = 'inactive'
WHERE description LIKE 'Demo company record%';

UPDATE opportunities
SET status = 'archived'
WHERE LOWER(application_url) REGEXP '^https?://([^/]+\\.)?example\\.(com|org|net|test)(/|:|$)';

UPDATE referral_opportunities
SET status = 'closed'
WHERE LOWER(application_url) REGEXP '^https?://([^/]+\\.)?example\\.(com|org|net|test)(/|:|$)';
