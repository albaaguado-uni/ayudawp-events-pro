# AyudaWP Events Pro – Test Suite

Complete PHPUnit test suite for the **AyudaWP Events Pro** WordPress plugin.

---

## Requirements

| Tool | Minimum version |
|------|----------------|
| PHP | 7.4 |
| PHPUnit | 9.x |
| WordPress test library | Any recent version |
| Xdebug / PCOV | Only needed for code coverage |

---

## Setup

### 1 · Install the WordPress test library

```bash
# Using wp-cli scaffold (recommended)
wp scaffold plugin-tests ayudawp-events-pro

# Or install manually
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### 2 · Set the WP_TESTS_DIR environment variable

```bash
# Linux / macOS
export WP_TESTS_DIR=/tmp/wordpress-tests-lib

# Windows (cmd)
set WP_TESTS_DIR=C:\tmp\wordpress-tests-lib

# Windows (PowerShell)
$env:WP_TESTS_DIR = "C:\tmp\wordpress-tests-lib"
```

### 3 · Install Composer dependencies (optional)

```bash
composer install
```

---

## Running tests

```bash
# Run the full test suite (unit + integration)
phpunit

# Run only unit tests
phpunit --testsuite unit

# Run only integration tests
phpunit --testsuite integration

# Run a single test file
phpunit tests/test-coupon-system.php

# Run tests matching a name pattern
phpunit --filter coupon
phpunit --filter registration
phpunit --filter security

# Verbose output (shows test names as they run)
phpunit --verbose

# Stop on first failure
phpunit --stop-on-failure
```

---

## Code coverage

> Requires **Xdebug** (recommended) or **PCOV**.

```bash
# HTML report  →  coverage/html/index.html
phpunit --coverage-html coverage/html

# Text summary in the terminal
phpunit --coverage-text

# Clover XML (for CI pipelines)
phpunit --coverage-clover coverage/clover.xml
```

Coverage targets: **≥ 80 %** of all classes under `includes/`.

---

## Test file map

### Unit tests (`tests/`)

| File | Class under test |
|------|-----------------|
| `test-post-type.php` | `Post_Type` – CPT registration, meta, queries |
| `test-attendee-manager.php` | `Attendees` – register, duplicate detection, sanitisation |
| `test-coupon-system.php` | `Coupon_System` – create, validate, apply, deactivate |
| `test-notification-system.php` | `Notification_System` – render templates, send email |
| `test-form-validation.php` | `Form_Validator` – field rules, registration & event forms |
| `test-shortcode.php` | `Shortcodes` – HTML output, POST submission, nonce |
| `test-rest-api.php` | `REST_API` – route registration, GET/POST endpoints, access control |
| `test-google-calendar.php` | `Google_Calendar` – URL builder, ICS generator |
| `test-security.php` | Cross-cutting – XSS, SQL injection, CSRF, access control |

### Integration tests (`tests/test-integration/`)

| File | Scenario |
|------|----------|
| `test-full-registration-flow.php` | End-to-end: validate → register → notify → DB check |
| `test-payment-processing.php` | Checkout: price calculation, coupon application, payment notification |

---

## Directory structure

```
ayudawp-events-pro/
├── ayudawp-event-pro.php          # Plugin entry point
├── phpunit.xml                    # PHPUnit configuration + coverage
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
└── tests/
    ├── bootstrap.php
    ├── test-post-type.php
    ├── test-attendee-manager.php
    ├── test-coupon-system.php
    ├── test-notification-system.php
    ├── test-form-validation.php
    ├── test-shortcode.php
    ├── test-rest-api.php
    ├── test-google-calendar.php
    ├── test-security.php
    └── test-integration/
        ├── test-full-registration-flow.php
        └── test-payment-processing.php
```

---

## CI / GitHub Actions example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: xdebug, mysqli
          coverage: xdebug

      - name: Install WordPress test suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

      - name: Install Composer deps
        run: composer install --no-interaction

      - name: Run tests with coverage
        run: phpunit --coverage-clover coverage/clover.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: coverage/clover.xml
```
