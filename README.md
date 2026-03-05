[![CI](https://github.com/albaaguado-uni/ayudawp-events-pro/actions/workflows/ci.yml/badge.svg)](https://github.com/albaaguado-uni/ayudawp-events-pro/actions/workflows/ci.yml)

# AyudaWP Events Pro

Professional events management plugin for WordPress with full CI/CD pipeline.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.0+ |
| PHPUnit | 9.x |
| WordPress | 6.6+ |
| Composer | 2.x |
| Node.js | 18.x |
| Xdebug / PCOV | Only needed for code coverage |

---

## Setup

### 1 · Clone the repository

```bash
git clone https://github.com/albaaguado-uni/ayudawp-events-pro.git
cd ayudawp-events-pro
```

### 2 · Install dependencies

```bash
composer install
npm install
```

### 3 · Install the WordPress test library

```bash
git clone --depth=1 --branch 6.6 https://github.com/WordPress/wordpress-develop.git wp-tests
cp wp-tests/wp-tests-config-sample.php wp-tests/tests/phpunit/wp-tests-config.php
```

Edit `wp-tests/tests/phpunit/wp-tests-config.php` with your local database credentials:

```php
define( 'DB_NAME', 'local' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', 'localhost:10005' );  // Check your Local by Flywheel DB port
define( 'ABSPATH', 'C:/Users/YourUser/Local Sites/your-site/app/public/' );
```

### 4 · Set the environment variable

```bash
# Linux / macOS / Git Bash
export WP_TESTS_DIR="$(pwd)/wp-tests/tests/phpunit"

# Windows (cmd)
set WP_TESTS_DIR=C:\path\to\plugin\wp-tests\tests\phpunit

# Windows (PowerShell)
$env:WP_TESTS_DIR = "C:\path\to\plugin\wp-tests\tests\phpunit"
```

---

## Running tests

```bash
# Run the full test suite (unit + integration)
vendor/bin/phpunit

# Run only unit tests
vendor/bin/phpunit --testsuite unit

# Run only integration tests
vendor/bin/phpunit --testsuite integration

# Run a single test file
vendor/bin/phpunit tests/CouponSystemTest.php

# Run tests matching a name pattern
vendor/bin/phpunit --filter coupon
vendor/bin/phpunit --filter registration
vendor/bin/phpunit --filter security

# Verbose output
vendor/bin/phpunit --verbose

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

---

## Code coverage

> Requires **Xdebug** or **PCOV**.

```bash
# HTML report → coverage/html/index.html
vendor/bin/phpunit --coverage-html coverage/html

# Text summary in the terminal
vendor/bin/phpunit --coverage-text

# Clover XML (for CI pipelines)
vendor/bin/phpunit --coverage-clover coverage/clover.xml
```

Coverage target: **≥ 80%** of all classes under `includes/`.

---

## Test file map

### Unit tests (`tests/`)

| File | Class under test | What it tests |
|------|-----------------|---------------|
| `SampleTest.php` | — | Basic sanity check |
| `PostTypeTest.php` | `Post_Type` | CPT registration, meta, queries |
| `AttendeeManagerTest.php` | `Attendees` | Register, duplicate detection, sanitisation |
| `CouponSystemTest.php` | `Coupon_System` | Create, validate, apply, deactivate |
| `NotificationSystemTest.php` | `Notification_System` | Render templates, send email |
| `FormValidationTest.php` | `Form_Validator` | Field rules, registration & event forms |
| `ShortcodeTest.php` | `Shortcodes` | HTML output, POST submission, nonce |
| `RestApiTest.php` | `REST_API` | Route registration, GET/POST endpoints, access control |
| `GoogleCalendarTest.php` | `Google_Calendar` | URL builder, ICS generator |
| `SecurityTest.php` | Cross-cutting | XSS, SQL injection, CSRF, access control |

### Integration tests (`tests/test-integration/`)

| File | Scenario |
|------|----------|
| `FullRegistrationFlowTest.php` | End-to-end: validate → register → notify → DB check |
| `PaymentProcessingTest.php` | Checkout: price calculation, coupon application, payment notification |

---

## Directory structure

```
ayudawp-events-pro/
├── .github/
│   └── workflows/
│       ├── ci.yml                 # Tests + coding standards (every push/PR)
│       ├── deploy.yml             # Auto-deploy (staging on develop, prod on release)
│       └── release.yml            # Auto-release with changelog
├── bin/
│   └── install-wp-tests.sh       # WP test suite installer for CI
├── includes/
│   ├── class-plugin.php
│   ├── class-post-type.php
│   ├── class-attendees.php
│   ├── class-installer.php
│   ├── class-shortcodes.php
│   ├── class-coupon-system.php
│   ├── class-notification-system.php
│   ├── class-form-validator.php
│   ├── class-rest-api.php
│   └── class-google-calendar.php
├── tests/
│   ├── bootstrap.php
│   ├── SampleTest.php
│   ├── PostTypeTest.php
│   ├── AttendeeManagerTest.php
│   ├── CouponSystemTest.php
│   ├── NotificationSystemTest.php
│   ├── FormValidationTest.php
│   ├── ShortcodeTest.php
│   ├── RestApiTest.php
│   ├── GoogleCalendarTest.php
│   ├── SecurityTest.php
│   └── test-integration/
│       ├── FullRegistrationFlowTest.php
│       └── PaymentProcessingTest.php
├── ayudawp-event-pro.php          # Plugin entry point
├── composer.json
├── package.json
├── phpunit.xml
├── .deployignore
└── README.md
```

---

## CI/CD Pipeline

The project uses **GitHub Actions** with three workflows:

### Continuous Integration (`ci.yml`)

Runs on every push and pull request to `main` and `develop`:

- **PHPCS** — WordPress coding standards check
- **PHPUnit** — Tests on PHP 8.0, 8.1 and 8.2 (matrix strategy)
- **ESLint + Jest** — JavaScript verification
- **Build** — Asset compilation

### Deploy (`deploy.yml`)

- **Push to `develop`** → Automatic deploy to staging
- **New release** → Automatic deploy to production + ZIP package

### Release (`release.yml`)

- **New tag `v*`** → Creates GitHub Release with auto-generated changelog

---

## Git workflow

```
main (production)  ← Protected, only stable code
  └── develop (development)  ← Integration branch
       ├── feature/new-feature
       └── feature/fix-bug
```

### Rules

- Never push directly to `main` or `develop`
- All changes go through Pull Requests
- Tests must pass before merging
- Each release uses semantic versioning

### Versioning (SemVer)

```
MAJOR.MINOR.PATCH → 2.3.1

MAJOR (2): Breaking changes
MINOR (3): New backward-compatible features
PATCH (1): Bug fixes
```

### Creating a release

```bash
# Merge develop into main
git checkout main
git merge develop
git push origin main

# Create version tag
git tag -a v1.1.0 -m "Release 1.1.0: Description of changes"
git push origin v1.1.0
```

---

## Deployment process

| Event | Environment | Action |
|-------|-------------|--------|
| Push to `develop` | Staging | Auto-deploy |
| Create release/tag | Production | Auto-deploy + ZIP upload |

### Rollback

If a deployment fails, revert to the previous tag:

```bash
git checkout v1.0.0
git tag -a v1.0.1 -m "Rollback to v1.0.0"
git push origin v1.0.1
```

---

## Configuring secrets (for real deployments)

Go to GitHub → Settings → Secrets and variables → Actions → New repository secret:

| Secret | Description |
|--------|-------------|
| `STAGING_FTP_SERVER` | FTP server for staging |
| `STAGING_FTP_USERNAME` | FTP username for staging |
| `STAGING_FTP_PASSWORD` | FTP password for staging |
| `PROD_SSH_HOST` | Production server host |
| `PROD_SSH_USERNAME` | SSH username |
| `PROD_SSH_KEY` | SSH private key |
| `SLACK_WEBHOOK` | Slack notification webhook URL |