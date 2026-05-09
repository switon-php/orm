<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\RuntimeException;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Sharding\Exception\ShardingTooManyException;
use Switon\Sharding\ShardingManagerInterface;

/**
 * Advanced test cases for Sharding implementation.
 *
 * Tests critical sharding scenarios including:
 * - getUniqueShard() validation and exception handling
 * - Multiple database and table sharding
 * - Dynamic sharding expressions
 * - Edge cases and error conditions
 */
#[AllowMockObjectsWithoutExpectations]
class ShardingAdvancedTest extends TestCase
{
    #[Autowired] protected ShardingInterface $sharding;
    protected MockObject|EntityMetadataInterface $entityMetadata;
    protected MockObject|ShardingManagerInterface $shardingManager;

    protected function setUp(): void
    {
        parent::setUp();


        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->shardingManager = $this->createMock(ShardingManagerInterface::class);

        // Remove services if already resolved
        if ($this->container->has(EntityMetadataInterface::class)) {
            $this->container->remove(EntityMetadataInterface::class);
        }
        if ($this->container->has(ShardingManagerInterface::class)) {
            $this->container->remove(ShardingManagerInterface::class);
        }
        if ($this->container->has(ShardingInterface::class)) {
            $this->container->remove(ShardingInterface::class);
        }
        $this->container->set(EntityMetadataInterface::class, $this->entityMetadata);
        $this->container->set(ShardingManagerInterface::class, $this->shardingManager);

        $this->injector->inject($this);
    }

    // ==================== getUniqueShard() Tests ====================

    public function testGetUniqueShardReturnsCorrectShardForNonShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users';
        $context = ['user_id' => 123];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);

        $result = $this->sharding->getUniqueShard($entityClass, $context);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($connection, $result[0]);
        $this->assertSame($table, $result[1]);
    }

    public function testGetUniqueShardReturnsCorrectShardForSingleShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'users';
        $context = ['user_id' => 123];
        $resolvedShards = ['shard2' => ['users']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($resolvedShards);

        $result = $this->sharding->getUniqueShard($entityClass, $context);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('shard2', $result[0]);
        $this->assertSame('users', $result[1]);
    }

    public function testGetUniqueShardThrowsExceptionWhenMultipleDatabasesResolved(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'users';
        $context = ['region' => 'all']; // Context that resolves to multiple databases
        $resolvedShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($resolvedShards);

        $this->expectException(ShardingTooManyException::class);
        $this->expectExceptionMessage('Operation spans multiple databases');

        $this->sharding->getUniqueShard($entityClass, $context);
    }

    public function testGetUniqueShardThrowsExceptionWhenMultipleTablesResolved(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users_0,users_1,users_2';
        $context = ['user_id' => [1, 2]]; // Context that resolves to multiple tables
        $resolvedShards = [
            'main' => ['users_0', 'users_1'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($resolvedShards);

        $this->expectException(ShardingTooManyException::class);
        $this->expectExceptionMessage('Operation spans multiple tables');

        $this->sharding->getUniqueShard($entityClass, $context);
    }

    public function testGetUniqueShardThrowsExceptionWhenNoTablesInShard(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2'; // Must be sharded to trigger ShardingManager
        $table = 'users';
        $context = ['user_id' => 999];
        $resolvedShards = ['shard1' => []]; // Empty tables array

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($resolvedShards);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tables found in shard');

        $this->sharding->getUniqueShard($entityClass, $context);
    }

    public function testGetUniqueShardWithEntityContext(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3'; // Must be sharded
        $table = 'users';

        $entity = new TestEntity();
        $entity->id = 123;

        $resolvedShards = ['shard2' => ['users']]; // ShardingManager resolves to shard2

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $entity)->willReturn($resolvedShards);

        $result = $this->sharding->getUniqueShard($entityClass, $entity);

        $this->assertSame('shard2', $result[0]);
        $this->assertSame('users', $result[1]);
    }

    // ==================== getAnyShard() Edge Cases ====================

    public function testGetAnyShardThrowsExceptionWhenNoShardsAvailable(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2';
        $table = 'users';
        $allShards = []; // No shards available

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($allShards);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No shards found for entity');

        $this->sharding->getAnyShard($entityClass);
    }

    public function testGetAnyShardThrowsExceptionWhenShardHasNoTables(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2'; // Must be sharded
        $table = 'users';
        $allShards = ['shard1' => []]; // Shard exists but no tables

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($allShards);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tables found in shard');

        $this->sharding->getAnyShard($entityClass);
    }

    public function testGetAnyShardReturnsFirstShardFromMultipleShards(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'users';
        $allShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
            'shard3' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($allShards);

        $result = $this->sharding->getAnyShard($entityClass);

        $this->assertSame('shard1', $result[0]);
        $this->assertSame('users', $result[1]);
    }

    public function testGetAnyShardReturnsFirstTableFromMultipleTables(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users_0,users_1,users_2';
        $allShards = [
            'main' => ['users_0', 'users_1', 'users_2'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($allShards);

        $result = $this->sharding->getAnyShard($entityClass);

        $this->assertSame('main', $result[0]);
        $this->assertSame('users_0', $result[1]);
    }

    // ==================== Multiple Shards Scenarios ====================

    public function testGetMultipleShardsWithMultipleDatabases(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'users';
        $context = ['region' => ['us', 'eu']];
        $expectedShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(2, $result);
    }

    public function testGetMultipleShardsWithMultipleTables(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users_0,users_1,users_2,users_3';
        $context = ['user_id' => [1, 5, 9]];
        $expectedShards = [
            'main' => ['users_0', 'users_1', 'users_2'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(1, $result);
        $this->assertCount(3, $result['main']);
    }

    public function testGetMultipleShardsWithDynamicShardingExpression(): void
    {
        $entityClass = TestEntity::class;
        // Dynamic expression must include : or , to be detected as sharded
        $connection = 'db:region'; // Colon triggers sharding detection
        $table = 'users:user_id%10'; // Colon triggers sharding detection
        $context = ['region' => 'us', 'user_id' => 123];
        // ShardingManager resolves the expression
        $expectedShards = [
            'db_us' => ['users_3'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testGetMultipleShardsWithComplexSharding(): void
    {
        $entityClass = TestEntity::class;
        // Dynamic expression must include : or , to be detected as sharded
        $connection = 'shard1,shard2:region'; // Comma triggers sharding detection
        $table = 'users_0,users_1,users_2,users_3'; // Comma triggers sharding detection
        $context = ['region' => ['us', 'eu'], 'user_id' => [1, 2, 5, 6]];
        // ShardingManager resolves the expressions
        $expectedShards = [
            'shard1' => ['users_1', 'users_2'],
            'shard2' => ['users_1', 'users_2'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(2, $result);
    }

    // ==================== getAllShards() Scenarios ====================

    public function testGetAllShardsWithMultipleDatabases(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'users';
        $expectedShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
            'shard3' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($expectedShards);

        $result = $this->sharding->getAllShards($entityClass);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(3, $result);
    }

    public function testGetAllShardsWithMultipleTables(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users_0,users_1,users_2,users_3';
        $expectedShards = [
            'main' => ['users_0', 'users_1', 'users_2', 'users_3'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($expectedShards);

        $result = $this->sharding->getAllShards($entityClass);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(1, $result);
        $this->assertCount(4, $result['main']);
    }

    public function testGetAllShardsWithDynamicShardingReturnsAllPossibleShards(): void
    {
        $entityClass = TestEntity::class;
        // Dynamic expression must include : or , to be detected as sharded
        $connection = 'db1,db2,db3:region'; // Comma triggers sharding detection
        $table = 'users:shard_id'; // Colon triggers sharding detection
        // ShardingManager resolves all possible shards
        $expectedShards = [
            'db1' => ['users_0', 'users_1', 'users_2'],
            'db2' => ['users_0', 'users_1', 'users_2'],
            'db3' => ['users_0', 'users_1', 'users_2'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($expectedShards);

        $result = $this->sharding->getAllShards($entityClass);

        $this->assertSame($expectedShards, $result);
        $this->assertCount(3, $result);
    }

    // ==================== Sharding Detection Tests ====================

    public function testShardingDetectionWithColonInConnection(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'db:user_id%4';
        $table = 'users';
        $context = ['user_id' => 123];
        $expectedShards = ['db_3' => ['users']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->expects($this->once())
            ->method('multiple')
            ->with($connection, $table, $context)
            ->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testShardingDetectionWithCommaInTable(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users_0,users_1,users_2';
        $context = [];
        $expectedShards = ['main' => ['users_0', 'users_1', 'users_2']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->expects($this->once())
            ->method('multiple')
            ->with($connection, $table, $context)
            ->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testShardingDetectionWithBothColonAndComma(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2:user_id%2';
        $table = 'users_0,users_1';
        $context = ['user_id' => 5];
        $expectedShards = ['shard1' => ['users_1']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testNonShardedEntityBypassesShardingManager(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'users';
        $context = ['user_id' => 123];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);

        // ShardingManager should NOT be called for non-sharded entities
        $this->shardingManager->expects($this->never())->method('multiple');

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame([$connection => [$table]], $result);
    }

    // ==================== Empty Context Tests ====================

    public function testGetMultipleShardsWithEmptyContext(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2';
        $table = 'users';
        $context = [];
        $expectedShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testGetUniqueShardWithEmptyContextThrowsExceptionForMultipleShards(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2';
        $table = 'users';
        $context = [];
        $expectedShards = [
            'shard1' => ['users'],
            'shard2' => ['users'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $this->expectException(ShardingTooManyException::class);

        $this->sharding->getUniqueShard($entityClass, $context);
    }
}
