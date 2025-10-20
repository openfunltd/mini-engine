<?php

use PHPUnit\Framework\TestCase;

class User extends MiniEngine_Table
{
    public function init()
    {
        $this->_columns['id'] = ['type' => 'serial'];
        $this->_columns['email'] = ['type' => 'text'];
        $this->_columns['displayname'] = ['type' => 'text'];
        $this->_columns['org_id'] = ['type' => 'bigint'];
        $this->_columns['permission'] = ['type' => 'bigint'];
        $this->_columns['last_logined_at'] = ['type' => 'bigint'];
        $this->_columns['data'] = ['type' => 'jsonb'];

        $this->_indexes['user_email'] = ['columns' => ['email'], 'unique' => true];
        $this->_indexes['user_org'] = ['columns' => ['org_id']];
    }
}

class MiniEngine_TableTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Initialize database connection via DATABASE_URL
        $db_url = sprintf('pgsql://%s:%s@%s:%s/%s',
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_HOST'],
            $_ENV['DB_PORT'],
            $_ENV['DB_NAME']
        );
        putenv("DATABASE_URL=$db_url");

        // Drop table if exists and create fresh
        $pdo = MiniEngine::getDb();
        $pdo->exec('DROP TABLE IF EXISTS "user"');
        User::createTable();

        // Insert test data
        User::insert([
            'email' => 'alice@example.com',
            'displayname' => 'Alice',
            'org_id' => 1,
            'permission' => 1,
            'last_logined_at' => 150,
            'data' => json_encode(['role' => 'admin']),
        ]);
        User::insert([
            'email' => 'bob@example.com',
            'displayname' => 'Bob',
            'org_id' => 1,
            'permission' => 0,
            'last_logined_at' => 50,
            'data' => json_encode(['role' => 'user']),
        ]);
        User::insert([
            'email' => 'charlie@example.com',
            'displayname' => 'Charlie',
            'org_id' => 2,
            'permission' => 1,
            'last_logined_at' => 200,
            'data' => json_encode(['role' => 'admin']),
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        $pdo = MiniEngine::getDb();
        $pdo->exec('DROP TABLE IF EXISTS "user"');
    }

    /**
     * Test that chaining array search followed by string search works correctly
     * This tests the bug where array search leaves ::col_ parameters in the params array
     * which causes "Invalid parameter number" error when followed by string search
     */
    public function testArraySearchFollowedByStringSearch()
    {
        // This should not throw an exception
        $count = User::search(['org_id' => 1])
            ->search('last_logined_at > 100')
            ->count();

        // Should return 1 (only Alice matches: org_id=1 AND last_logined_at > 100)
        $this->assertEquals(1, $count);
    }

    /**
     * Test that consecutive array searches work correctly (baseline test)
     */
    public function testConsecutiveArraySearches()
    {
        $count = User::search(['org_id' => 1])
            ->search(['last_logined_at' => 100])
            ->count();

        // Should return 0 (no user with org_id=1 AND last_logined_at=100)
        $this->assertEquals(0, $count);
    }

    /**
     * Test string search followed by array search
     */
    public function testStringSearchFollowedByArraySearch()
    {
        $count = User::search('last_logined_at > 100')
            ->search(['org_id' => 1])
            ->count();

        // Should return 1 (only Alice)
        $this->assertEquals(1, $count);
    }

    /**
     * Test multiple chained searches with mixed types
     */
    public function testMultipleChainedSearches()
    {
        $count = User::search(['org_id' => 1])
            ->search('last_logined_at > 50')
            ->search('last_logined_at < 200')
            ->count();

        // Should return 1 (only Alice: org_id=1, 50 < last_logined_at < 200)
        $this->assertEquals(1, $count);
    }

    /**
     * Test consecutive string searches
     */
    public function testConsecutiveStringSearches()
    {
        $count = User::search('last_logined_at > 100')
            ->search('org_id = 1')
            ->count();

        // Should return 1 (only Alice)
        $this->assertEquals(1, $count);
    }

    /**
     * Test array -> string -> array search chain
     */
    public function testArrayStringArraySearchChain()
    {
        $count = User::search(['org_id' => 1])
            ->search('last_logined_at > 100')
            ->search(['permission' => 1])
            ->count();

        // Should return 1 (only Alice: org_id=1, last_logined_at > 100, permission=1)
        $this->assertEquals(1, $count);
    }

    /**
     * Test that incomplete SQL string in search throws PDOException
     * This tests the error handling when passing an incomplete SQL condition like 'user = '
     */
    public function testIncompleteSqlStringThrowsException()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/syntax error/i');

        // Passing an incomplete SQL condition should throw PDOException with syntax error
        User::search('org_id = ')->first();
    }
}
