# Barangay Talusan Waste Collection Scheduling and SMS Notification System

This project is a PHP + MySQL application for Barangay Talusan.

## Project stack
- PHP
- MySQL
- Apache/XAMPP
- HTML, CSS, JavaScript

## File structure
- admin.php
- app.js
- config.php
- index.php
- login.php
- logo.jpg
- logout.php
- README.md
- schema.sql
- style.css
- sms.php
- retry.php

## Setup
1. Start Apache and MySQL in XAMPP.
2. Import `schema.sql` into MySQL using phpMyAdmin.
3. Open the project in `C:\xampp\htdocs\talusan-waste`.
4. Visit:
   - public page: `http://localhost/talusan-waste/index.php`
   - admin login: `http://localhost/talusan-waste/login.php`

## Default admin login
- Username: `secretary`
- Password: `Admin@12345`

Important: change the password before production use.

## Security
- **Logout:** the *Log out* button (admin sidebar) sends a CSRF-protected POST to `logout.php`, which clears the session data, destroys the session and deletes the session cookie.
- **Idle sign-out:** admins are signed out automatically after 30 minutes of inactivity (`SESSION_IDLE_TIMEOUT` in `config.php`).
- **Login attempt limit:** after **3 consecutive failed logins** for a username, that account is locked for **5 minutes** (`LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCK_SECONDS` in `config.php`). Counters are stored in the `login_throttle` table (created automatically on first visit to `login.php`, and included in `schema.sql`). A successful login resets the counter.

## SMS gateway
This project uses a mock SMS gateway by default. No real SMS is sent.

For real SMS:
- set `SMS_PROVIDER` to `http`
- provide actual values in `config.php` or a local override file
- never commit real API keys to GitHub

## Retry script
The retry script is:
- `retry.php`

Run it manually:
```bash
php retry.php
```