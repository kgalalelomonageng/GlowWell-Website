GlowWell Beauty Clinic
GlowWell is a PHP appointment-booking and clinic-management application. It includes customer registration, treatment browsing and booking, staff availability management, appointment processing, reporting, and CSV exports. It stores application data in MySQL and starts with empty tables and no preconfigured accounts.

Run with XAMPP
Keep this project in XAMPP's htdocs directory.
Open the XAMPP Control Panel and start Apache and MySQL.
Open http://localhost/phpmyadmin/ and import database/glowwell.sql. It creates the glowwell database and application tables, and upgrades the customer phone column if needed; it does not delete existing records.
For assessment/testing screenshots only, also import database/glowwell_sample_data.sql. It adds clearly labelled test accounts, services, staff schedules, and upcoming, completed, and cancelled appointments. Sign in with assessment.admin@glowwell.test or assessment.customer@glowwell.test, using GlowWellAssess2026! for either account. Do not use these shared test credentials for a live clinic; skip this step for a clean production database.
Visit http://localhost/GlowWell-Website/.
By default, the app connects to MySQL at 127.0.0.1:3306 as root with an empty password, which matches a standard local XAMPP setup. If you have set a MySQL root password or use another database account, configure GLOWWELL_DB_PASSWORD and, if needed, GLOWWELL_DB_USER in Apache's environment. Do not commit database passwords to the project.

Run with AMPPS
Keep this project in AMPPS's www directory.
Open AMPPS and start Apache and MySQL.
Open AMPPS phpMyAdmin and import database/glowwell.sql. It creates any missing application tables and upgrades the customer phone column if needed; it does not delete existing records.
For assessment/testing screenshots only, import database/glowwell_sample_data.sql as described above.
Visit http://localhost/GlowWell-Website/.
When no GLOWWELL_DB_PASSWORD environment variable is set, the app reads AMPPS's local MySQL password from ampps/data/my.conf if that file is available. If AMPPS is installed elsewhere or you use a separate database account, set GLOWWELL_DB_HOST, GLOWWELL_DB_PORT, GLOWWELL_DB_NAME, GLOWWELL_DB_USER, and GLOWWELL_DB_PASSWORD in Apache's environment.

Installation on another server
Install PHP 8.0 or later with pdo_mysql enabled and MySQL 8.0.16 or later.
Create the glowwell database and import database/glowwell.sql using the MySQL client or phpMyAdmin.
Set GLOWWELL_DB_HOST, GLOWWELL_DB_PORT, GLOWWELL_DB_NAME, GLOWWELL_DB_USER, and GLOWWELL_DB_PASSWORD in the PHP web-server environment. The host, port, and database default to 127.0.0.1, 3306, and glowwell. When set, these environment values take precedence over AMPPS defaults.
Open the application through your configured web server.
The main SQL schema creates the application tables without adding accounts or clinic records. Import database/glowwell_sample_data.sql only when sample data is needed for assessment or testing. It is safe to re-run without duplicating its labelled records; do not import it into a live clinic database.

First administrator
Create your own account from the registration page. Public registration creates customer accounts only. To establish the first administrator, promote the trusted account you just registered by running this statement in the glowwell database, replacing the email with that account's address:

UPDATE users SET role = 'admin' WHERE email = 'your-admin-email@example.com';
Sign out and back in to access the administrator area. Do not promote an account you do not control. Add the clinic's actual team members, working schedules, and treatments before customers book appointments. Or use glowwell.admin@glowwell.com for password GlowWellAssess2026!

When MySQL is unavailable or misconfigured, the application reports a connection error and does not switch to another data store.

Main workflows
Customer
Register or sign in.
Browse treatments and prices.
Book a treatment with an active team member during their working hours.
View upcoming and past visits; cancel an upcoming appointment while it is still pending.
Administrator
Review the clinic dashboard.
Add, edit, and delete treatments.
Create and update team profiles; set each team member's weekly working hours; deactivate a team member or delete one without appointment history.
Review bookings, confirm pending appointments, complete confirmed appointments, or cancel them.
Filter appointment reports by date and status, review completed service value, and download filtered results as CSV.
Database overview
users: customer and administrator accounts; unique email, phone number, and password hash.
staff: clinic team profiles and activation status.
staff_schedules: a team's weekly availability; one row per working weekday.
services: treatment description, duration, and price.
appointments: customer, treatment, team member, date, time, and status, with foreign keys to preserve booking history.
The main SQL files create the database tables, indexes, and constraints. The separate assessment-data script adds the test accounts and example clinic records. When upgrading an existing database, the schema adds the phone field if it is missing; existing users' phone numbers remain blank until updated.

Project files
index.php: public landing page.
authentication/: sign-in, registration, sign-out.
customer/: customer dashboard, appointment booking, and appointment history.
admin/: clinic dashboard, team and schedule management, service management, appointment management, and reports.
config/db.php: MySQL connection.
database/: MySQL schema.
database/glowwell_sample_data.sql: optional assessment/testing dataset; never import into a live clinic.
assets/: shared responsive CSS and browser-side validation/interactions.
User-Guide.html: step-by-step guide to installing and using the application.
