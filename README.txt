AIUB-Career-Connect

Project goal:
AIUB Career Connect is a PHP/MySQL career network and placement portal for AIUB students, alumni, and administrators. Accounts, profiles, companies, opportunities, and career-support workflows use MySQL as their source of truth. PHP sessions hold authentication state, request-protection tokens, activity-timeout state, and short-lived page messages; application records are stored in MySQL.

Included:
- Student and alumni registration, password-hashed login, logout, and database-backed profiles
- Alumni directory, contact consent, verification, and profile privacy controls
- Company directory, admin create/edit/merge tools, and verified visible alumni counts
- Existing opportunity search, lifecycle controls, and student application tracking
- Mentorship requests, alumni referral listings, and in-app opportunity alerts
- Aggregate admin engagement and audit information
- Responsive layouts, page-specific titles, and optimized image assets

Project structure:
Assets/              Project images and logos
Controller/          Authentication and opportunity request logic
DataBase/            Fresh-install schema and one-time upgrade scripts
Design/Style.css      Shared responsive styles
Javascript/Script.js  Browser interactions and AJAX filtering
Model/                MySQL connection and repository queries
View/                 User-facing PHP pages and shared partials
config.sample.php     Example local database configuration
index.php              Front controller
setup_admin.php        CLI-only initial administrator provisioning
Project-Documentation.txt

Local setup with XAMPP:
1. Start Apache and MySQL.
2. For a new installation, import the consolidated current schema at DataBase/aiub_career_connect.sql into a new/empty database. It includes all current tables, columns, enum values, foreign keys, and indexes through increment 7. Do not import it over an existing database.
3. Copy `config.sample.php` to `config.php` and set the local database values. `config.php` is ignored by Git and must not be shared.
4. For the first administrator only, set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and a unique `INITIAL_ADMIN_PASSWORD` (12-72 characters) in your local environment, then run `php setup_admin.php` from this folder. The script refuses to run if any administrator already exists and cannot be run over HTTP. Clear the temporary environment variables afterwards.
5. Alternatively, set DB_HOST, DB_NAME, DB_USER, and DB_PASS in the PHP environment; environment values override `config.php`.
6. Place this folder in the web server root and open http://localhost/AIUB-Career-Connect/index.php.

Upgrade existing databases only, applying each migration once and in order:
- Increment 1 database: import increment_2_upgrade.sql, then increment_3_upgrade.sql, increment_4_upgrade.sql, increment_5_upgrade.sql, increment_6_upgrade.sql, and increment_7_upgrade.sql.
- Increment 2 database: import increment_3_upgrade.sql, then increment_4_upgrade.sql, increment_5_upgrade.sql, increment_6_upgrade.sql, and increment_7_upgrade.sql.
- Increment 3 database: import increment_4_upgrade.sql, then increment_5_upgrade.sql, increment_6_upgrade.sql, and increment_7_upgrade.sql.
- Increment 4 database: import increment_5_upgrade.sql, increment_6_upgrade.sql, and increment_7_upgrade.sql.
- Increment 5 database: import increment_6_upgrade.sql and increment_7_upgrade.sql.
- Increment 6 database: import increment_7_upgrade.sql.
- Do not run upgrade scripts on a fresh installation or rerun a migration already applied.
- The increment_2 through increment_7 files are retained only for upgrading older databases. They are not needed for a new installation using the consolidated schema.
- Increment 4 preserves rows but deactivates the known seeded test accounts, hides clearly marked sample company records, and archives known example.com/example.test opportunity destinations.
- Increment 5 converts existing tables to utf8mb4 and adds temporary-password change state, contact-consent fields, in-app notifications, and login-attempt tracking.
- Increment 6 adds the student ID and graduation-year profile fields used during registration. Existing profiles retain NULL values until updated.
- Increment 7 indexes login-attempt timestamps to keep expired-attempt cleanup efficient.

Application behavior:
- New accounts must use an `@aiub.edu` email and provide a student ID and graduation year. Administrator accounts are provisioned separately; public registration cannot create an administrator.
- The `@aiub.edu` check validates the address format only; mailbox ownership is not verified. Email verification and self-service password recovery are not available because no outbound email service is configured.
- If MySQL is unavailable, the application shows an explicit service error and does not switch to session-only data.
- The profile editor writes to the same user/profile records used by directories and dashboards. Admins edit only their account identity; students and alumni edit career details.
- Administrators can create, edit, duplicate, publish, close, and archive opportunities, and manage student/alumni account status and role. Verified alumni can create, edit, close, and reopen their own referral listings.
- The application and company views never link to reserved example domains. Missing or invalid destinations are clearly marked unavailable.
- The homepage counts active student profiles, visible verified alumni, verified visible alumni who opted into mentorship, and currently published opportunities from database queries. Counts are exact.
- Recommendations are shown only when the database recommendation query returns matches. Alerts and account/request updates are available in the in-app notification center.
- The application tracker is private to each student and does not confirm that an employer received an application. Admin insights are aggregate counts and do not expose private notes or request text.
- Mentorship and referral records persist in MySQL. Referral acceptance means consideration only, not a guaranteed referral.
- The job board supports location, work-mode, department, type, keyword, sorting, and paginated filters; students can save an opportunity directly to their private tracker.
- Profile skills can be edited as tags, and the application tracker includes status filters and clearer deadline/listing-state cues.
- The front controller uses the Asia/Dhaka timezone for application dates and deadlines.

Database content:
- The fresh schema includes no test users, sample opportunities, sample companies, or public test credentials.
- Create real accounts through registration and provision the first administrator as described above.
- Admin-managed opportunities and alumni referral listings are separate workflows and data models. Add only verified, current opportunities and valid HTTPS employer URLs.
