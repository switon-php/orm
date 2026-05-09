<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\Attribute\Autowired;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Sharding\ShardingManagerInterface;

#[AllowMockObjectsWithoutExpectations]
class ShardingTest extends TestCase
{
    #[Autowired] protected ShardingInterface $sharding;
    protected MockObject|EntityMetadataInterface $entityMetadata;
    protected MockObject|ShardingManagerInterface $shardingManager;

    protected function setUp(): void
    {
        parent::setUp();


        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->shardingManager = $this->createMock(ShardingManagerInterface::class);

        // Remove services if already resolved to avoid ServiceAlreadyResolvedException
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

    public function testGetMultipleShardsReturnsSingleShardForNonShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'test_entity';
        $context = [];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey($connection, $result);
        $this->assertSame([$table], $result[$connection]);
        $this->assertCount(1, $result);
    }

    public function testGetMultipleShardsDelegatesToShardingManagerForShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2';
        $table = 'test_entity';
        $context = [];
        $expectedShards = ['shard1' => ['test_entity'], 'shard2' => ['test_entity']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testGetAllShardsReturnsSingleShardForNonShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'test_entity';

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);

        $result = $this->sharding->getAllShards($entityClass);

        $this->assertIsArray($result);
        $this->assertArrayHasKey($connection, $result);
        $this->assertSame([$table], $result[$connection]);
        $this->assertCount(1, $result);
    }

    public function testGetAllShardsDelegatesToShardingManagerForShardedEntity(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'shard1,shard2,shard3';
        $table = 'test_entity';
        $expectedShards = [
            'shard1' => ['test_entity'],
            'shard2' => ['test_entity'],
            'shard3' => ['test_entity'],
        ];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('all')->with($connection, $table)->willReturn($expectedShards);

        $result = $this->sharding->getAllShards($entityClass);

        $this->assertSame($expectedShards, $result);
    }

    public function testGetAnyShardReturnsFirstShard(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'test_entity';

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);

        $result = $this->sharding->getAnyShard($entityClass);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($connection, $result[0]);
        $this->assertSame($table, $result[1]);
    }

    public function testGetMultipleShardsDetectsShardingInConnectionWithColon(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'db:user_id%4';
        $table = 'test_entity';
        $context = [];
        $expectedShards = ['db_1' => ['test_entity']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->expects($this->once())
            ->method('multiple')
            ->with($connection, $table, $context)
            ->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }

    public function testGetMultipleShardsDetectsShardingInTableWithComma(): void
    {
        $entityClass = TestEntity::class;
        $connection = 'main';
        $table = 'table1,table2';
        $context = [];
        $expectedShards = ['main' => ['table1', 'table2']];

        $this->entityMetadata->method('getConnection')->with($entityClass)->willReturn($connection);
        $this->entityMetadata->method('getTable')->with($entityClass)->willReturn($table);
        $this->shardingManager->method('multiple')->with($connection, $table, $context)->willReturn($expectedShards);

        $result = $this->sharding->getMultipleShards($entityClass, $context);

        $this->assertSame($expectedShards, $result);
    }
}

