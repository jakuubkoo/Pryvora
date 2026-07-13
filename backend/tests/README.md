# Running Tests

## First Time Setup

1. Copy the test environment template:
   ```bash
   cp .env.test.dist .env.test
   ```

2. Update `.env.test` with your actual test values:
   - Generate encryption key: `openssl rand -base64 32`
   - Generate JWT passphrase: `openssl rand -hex 32`

3. The bootstrap will automatically:
   - Create the test database
   - Run migrations
   - Set up the schema

## Running Tests

```bash
# Run all tests
docker compose exec backend php vendor/bin/phpunit

# Run with detailed output
docker compose exec backend php vendor/bin/phpunit --testdox

# Run specific test file
docker compose exec backend php vendor/bin/phpunit tests/Controller/AuthControllerTest.php

# Run specific test method
docker compose exec backend php vendor/bin/phpunit --filter test_login_with_valid_credentials
```

## Test Database

The test database is automatically managed:
- **Dropped** before each test run (clean slate)
- **Created** if it doesn't exist
- **Migrated** to latest schema

This ensures tests always run against a fresh database.

## CI/CD

Tests run automatically in GitHub Actions on:
- Pull requests to `develop` or `main`
- Pushes to `develop` or `main`
- Only when backend files change

## Important Files

- `.env.test.dist` - Template (committed to git)
- `.env.test` - Your actual test config (NOT committed, in .gitignore)
- `bootstrap.php` - Automatic database setup
- `phpunit.dist.xml` - PHPUnit configuration

