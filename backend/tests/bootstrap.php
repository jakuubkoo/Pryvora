<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Create .env.test from .env.test.dist if it doesn't exist
$env_test_file = dirname(__DIR__).'/.env.test';
$env_test_dist_file = dirname(__DIR__).'/.env.test.dist';

if (!file_exists($env_test_file) && file_exists($env_test_dist_file))
{
    copy($env_test_dist_file, $env_test_file);
    echo "Created .env.test from .env.test.dist\n";
    echo "Please update .env.test with your actual test values!\n";
}

if (method_exists(Dotenv::class, 'bootEnv'))
{
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'])
{
    umask(0000);
}

// Set up test database automatically
if ($_SERVER['APP_ENV'] === 'test')
{
    echo "Setting up test database...\n";

    $commands = [
        'php bin/console doctrine:database:drop --if-exists --force --env=test --quiet',
        'php bin/console doctrine:database:create --if-not-exists --env=test --quiet',
        'php bin/console doctrine:migrations:migrate --no-interaction --env=test --quiet',

        // The database above is rebuilt every run, so ids restart at 1 — but the
        // rate limiter's cache is on disk and does not. Without this, a bucket
        // drained by one run is still drained for the next run's account 1, and any
        // test asserting a burst limit fails on every second run.
        'php bin/console cache:pool:clear cache.rate_limiter --env=test --quiet',
    ];

    foreach ($commands as $command)
    {
        $output = [];
        $return_var = 0;
        exec("cd " . dirname(__DIR__) . " && $command 2>&1", $output, $return_var);

        if ($return_var !== 0)
        {
            echo "Warning: Command failed: $command\n";
            echo implode("\n", $output) . "\n";
        }
    }

    echo "Test database ready.\n";
}
