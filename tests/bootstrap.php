<?php

require_once __DIR__ . '/../mini-engine.php';

class TestEnvironment
{
    private static bool $docker_managed = false;
    private static string $db_host = 'localhost';
    private static string $db_port = '5432';

    public static function setup(): void
    {
        // Try Docker first
        if (self::tryDocker()) {
            return;
        }

        // Docker not available, try local PostgreSQL
        if (self::tryLocalPostgres()) {
            return;
        }

        // Neither available, show setup instructions
        self::showSetupInstructions();
        exit(1);
    }

    private static function tryDocker(): bool
    {
        echo "Checking Docker availability...\n";

        // Check if docker-compose command exists
        exec('which docker-compose 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            echo "  docker-compose not found\n";
            return false;
        }

        // Check Docker permission
        exec('docker ps 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            echo "  No Docker permission or Docker not running\n";
            return false;
        }

        echo "  Docker available, starting containers...\n";

        // Start docker-compose
        exec('docker-compose up -d 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            echo "  Failed to start Docker containers\n";
            return false;
        }

        // Wait for database to be actually ready by testing connection
        echo "  Waiting for database to be ready...\n";
        $max_attempts = 30;
        $host = 'localhost';
        $port = '65432';
        $db_name = 'mini_engine_test';
        $user = 'mini_engine';
        $password = 'mini_engine_pass';

        for ($i = 0; $i < $max_attempts; $i++) {
            try {
                $pdo = new PDO(
                    "pgsql:host={$host};port={$port};dbname={$db_name}",
                    $user,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 1
                    ]
                );

                // Test if we can actually query
                $pdo->query('SELECT 1');

                echo "  Docker database ready!\n";
                self::$docker_managed = true;
                self::$db_port = '65432';

                // Update environment variables
                putenv('DB_PORT=65432');
                $_ENV['DB_PORT'] = '65432';

                return true;

            } catch (PDOException $e) {
                // Database not ready yet, continue waiting
                sleep(1);
            }
        }

        echo "  Database connection timeout\n";
        return false;
    }

    private static function tryLocalPostgres(): bool
    {
        echo "Checking local PostgreSQL...\n";

        $host = 'localhost';
        $port = '5432';
        $db_name = 'mini_engine_test';
        $user = 'mini_engine';
        $password = 'mini_engine_pass';

        try {
            // Try to connect to test database
            $pdo = new PDO(
                "pgsql:host={$host};port={$port};dbname={$db_name}",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            echo "  Local PostgreSQL database found and accessible!\n";
            self::$db_port = '5432';

            // Update environment variables
            putenv('DB_PORT=5432');
            $_ENV['DB_PORT'] = '5432';

            return true;

        } catch (PDOException $e) {
            echo "  Local PostgreSQL not configured for testing\n";
            return false;
        }
    }

    private static function showSetupInstructions(): void
    {
        echo "\n";
        echo "================================================================================\n";
        echo "  Test Environment Not Configured\n";
        echo "================================================================================\n";
        echo "\n";
        echo "Please choose one of the following setup methods:\n";
        echo "\n";
        echo "Option 1: Use Docker (Recommended)\n";
        echo "----------------------------------------\n";
        echo "1. Install Docker and Docker Compose\n";
        echo "2. Start Docker daemon\n";
        echo "3. Run tests again, containers will start automatically\n";
        echo "\n";
        echo "Option 2: Use Local PostgreSQL\n";
        echo "----------------------------------------\n";
        echo "1. Install and start PostgreSQL\n";
        echo "2. Execute the following SQL commands to create test database:\n";
        echo "\n";
        echo "   CREATE USER mini_engine WITH PASSWORD 'mini_engine_pass';\n";
        echo "   CREATE DATABASE mini_engine_test OWNER mini_engine;\n";
        echo "   GRANT ALL PRIVILEGES ON DATABASE mini_engine_test TO mini_engine;\n";
        echo "\n";
        echo "   Or use command line:\n";
        echo "\n";
        echo "   sudo -u postgres psql -c \"CREATE USER mini_engine WITH PASSWORD 'mini_engine_pass';\"\n";
        echo "   sudo -u postgres psql -c \"CREATE DATABASE mini_engine_test OWNER mini_engine;\"\n";
        echo "   sudo -u postgres psql -c \"GRANT ALL PRIVILEGES ON DATABASE mini_engine_test TO mini_engine;\"\n";
        echo "\n";
        echo "3. Run tests again\n";
        echo "\n";
        echo "================================================================================\n";
        echo "\n";
    }

    public static function teardown(): void
    {
        if (!self::$docker_managed) {
            return;
        }

        echo "\nStopping Docker containers...\n";
        exec('docker-compose down 2>&1', $output, $return_code);

        if ($return_code === 0) {
            echo "Docker containers stopped.\n";
        } else {
            echo "Warning: Failed to stop Docker containers\n";
        }
    }
}

// Setup test environment
TestEnvironment::setup();

// Register shutdown function
register_shutdown_function(function () {
    TestEnvironment::teardown();
});
