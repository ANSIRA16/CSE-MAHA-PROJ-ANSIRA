# InternLink — College Internship Application Portal

A responsive PHP/MySQL starter project for a college internship portal.

## Requirements
- PHP 8.0+ with PDO MySQL enabled
- MySQL 8+ (or a compatible MariaDB version)
- Apache/XAMPP, WAMP, MAMP, or PHP's local development server

## Setup
1. Create a MySQL database named `internship_portal`.
2. Run the SQL in `database_schema.sql`.
3. Update the database host, name, username, and password in `config/database.php`.
4. Put the `internship-portal` folder in your web server's document root.
5. If using PHP's built-in server, run this from the project folder:
   `php -S 127.0.0.1:8000`
6. Open `http://127.0.0.1:8000/index.php`.

## Roles
- Student: register, login, maintain profile and skills, search internships, apply, track status.
- Company/Admin: register, login, post internships, view applicants, update application status.

## Database expectations
The included `database_schema.sql` defines the column names expected by this starter code. If you already have a database with different names, compare your table structure and adjust queries before using this code.

## Notes
- Passwords are stored using PHP `password_hash()` and verified with `password_verify()`.
- SQL statements use PDO prepared statements.
- State-changing forms use a CSRF token.
- Resume upload allows PDF/DOC/DOCX up to 3 MB. For production, store uploads outside the public web root and serve them through a permission-checking download endpoint.
- Company registration is open in this demo starter. For a real deployment, company accounts should be verified and privileged admin actions should require a separate admin role.
- Use HTTPS and configure secure session cookie settings before deploying publicly.
