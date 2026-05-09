<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Tests\DatabaseTestCase;

/**
 * Integration tests for Repository with real database.
 *
 * Tests Repository functionality using SQLite in-memory database.
 * Verifies that ORM operations work correctly with a real database.
 */
class RepositoryIntegrationTest extends DatabaseTestCase
{
    #[Autowired] protected EntityManagerInterface $entityManager;
    protected RepoUserRepository $repository;

    protected function getSchema(): string
    {
        return "
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                age INTEGER,
                status TEXT DEFAULT 'active',
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                created_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ";
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create repository instance
        $this->repository = new RepoUserRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
    }

    // ==================== Basic CRUD Tests ====================

    public function testCreateAndFind(): void
    {
        // Create user
        $user = new RepoUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->age = 30;

        $this->repository->create($user);

        // Verify user was created
        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Find user
        $found = $this->repository->find($user->id);

        $this->assertNotNull($found);
        $this->assertEquals('John Doe', $found->name);
        $this->assertEquals('john@example.com', $found->email);
        $this->assertEquals(30, $found->age);
    }

    public function testUpdate(): void
    {
        // Insert test data
        $userId = $this->insert('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 25,
            'status' => 'active'
        ]);

        // Find and update
        $user = $this->repository->find($userId);
        $user->name = 'Jane Smith';
        $user->age = 26;

        $this->repository->update($user);

        // Verify update
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Jane Smith',
            'age' => 26
        ]);
    }

    public function testDelete(): void
    {
        // Insert test data
        $userId = $this->insert('users', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'age' => 40
        ]);

        $this->assertDatabaseCount('users', 1);

        // Delete
        $user = $this->repository->find($userId);
        $this->repository->delete($user);

        // Verify deletion
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    // ==================== Query Tests ====================

    public function testFindAll(): void
    {
        // Insert test data
        $this->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        $this->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);
        $this->insert('users', ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 40]);

        // Find all
        $users = $this->repository->all();

        $this->assertCount(3, $users);
        $this->assertEquals('User 1', $users[0]->name);
        $this->assertEquals('User 2', $users[1]->name);
        $this->assertEquals('User 3', $users[2]->name);
    }

    public function testFindWithConditions(): void
    {
        // Insert test data
        $this->insert('users', ['name' => 'Young User', 'email' => 'young@example.com', 'age' => 18]);
        $this->insert('users', ['name' => 'Adult User', 'email' => 'adult@example.com', 'age' => 30]);
        $this->insert('users', ['name' => 'Senior User', 'email' => 'senior@example.com', 'age' => 65]);

        // Find users older than 25 using public API with correct operator format
        $users = $this->repository->all(['age>' => 25]);

        $this->assertCount(2, $users);
        $this->assertEquals('Adult User', $users[0]->name);
        $this->assertEquals('Senior User', $users[1]->name);
    }

    public function testCount(): void
    {
        // Insert test data
        $this->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        $this->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);

        // Count using public API
        $users = $this->repository->all();
        $count = \count($users);

        $this->assertEquals(2, $count);
    }

    public function testExists(): void
    {
        // Insert test data
        $userId = $this->insert('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 25
        ]);

        $this->assertTrue($this->repository->exists(['id' => $userId]));
        $this->assertFalse($this->repository->exists(['id' => 999]));
    }

    // ==================== Batch Operations Tests ====================

    public function testCreateMany(): void
    {
        $users = [
            new RepoUser(['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]),
            new RepoUser(['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]),
            new RepoUser(['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 40]),
        ];

        $this->repository->createMany($users);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['name' => 'User 1']);
        $this->assertDatabaseHas('users', ['name' => 'User 2']);
        $this->assertDatabaseHas('users', ['name' => 'User 3']);
    }

    public function testUpdateAll(): void
    {
        // Insert test data with different ages
        $this->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com', 'status' => 'active', 'age' => 25]);
        $this->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com', 'status' => 'active', 'age' => 30]);
        $this->insert('users', ['name' => 'User 3', 'email' => 'user3@example.com', 'status' => 'active', 'age' => 35]);

        // Verify initial state
        $this->assertDatabaseCount('users', 3);

        // Update users older than 28 to inactive
        $affected = $this->repository->updateAll(
            ['age>' => 28],          // Filter: users older than 28
            ['status' => 'inactive']
        );

        $this->assertEquals(2, $affected);  // User 2 and User 3
        $this->assertEquals(1, $this->countRows('users', ['status' => 'active']));   // User 1
        $this->assertEquals(2, $this->countRows('users', ['status' => 'inactive'])); // User 2, User 3
    }

    public function testDeleteAll(): void
    {
        // Insert test data
        $this->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        $this->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);
        $this->insert('users', ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 40]);

        $this->assertDatabaseCount('users', 3);

        // Delete users older than 25 using public API with correct operator format
        $deleted = $this->repository->deleteAll(['age>' => 25]);

        $this->assertEquals(2, $deleted);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['name' => 'User 1']);
    }
}

// ==================== Test Entities ====================

#[Table('users')]
class RepoUser extends Entity
{
    #[PrimaryKey]
    #[Column('id')]
    public ?int $id = null;

    #[Column('name')]
    public string $name;

    #[Column('email')]
    public string $email;

    #[Column('age')]
    public ?int $age = null;

    #[Column('status')]
    public string $status = 'active';

    #[Column('created_at')]
    public ?string $created_at = null;

    #[Column('updated_at')]
    public ?string $updated_at = null;
}

class RepoUserRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return RepoUser::class;
    }
}
