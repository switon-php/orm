<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Db\ClientInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Id\IdGeneratorInterface as IdPackageInterface;
use Switon\Orm\Attribute\Id;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Exception\InvalidIdStrategyException;
use Switon\Orm\IdGenerator;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestOrderWithMappedPrimaryKey;
use Switon\Orm\Tests\TestCase;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class IdGeneratorTest extends TestCase
{
    protected IdGenerator $idGenerator;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|ShardingInterface $mockSharding;
    protected MockObject|NamedLookupInterface $mockNamedLookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockSharding = $this->createMock(ShardingInterface::class);
        $this->mockNamedLookup = $this->createMock(NamedLookupInterface::class);

        // Replace container services before make()
        $this->container->set(EntityMetadataInterface::class, $this->mockEntityMetadata);
        $this->container->set(ShardingInterface::class, $this->mockSharding);
        $this->container->set(NamedLookupInterface::class, $this->mockNamedLookup);

        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->mockEntityMetadata->method('getColumnMap')
            ->willReturnCallback(static fn (string $entityClass): array => $entityClass === TestOrderWithMappedPrimaryKey::class
                ? ['id' => 'order_id']
                : []);

        // Create IdGenerator with autowired dependencies
        $this->idGenerator = $this->make(IdGenerator::class);
    }

    public function testGenerateIdReturnsNullForAutoIncrement(): void
    {
        $entity = new TestEntity(['name' => 'Test']);

        // Mock that entity uses auto-increment (no Id attribute with strategy)
        $result = $this->idGenerator->generateId($entity);

        $this->assertNull($result);
    }

    public function testFillIdDoesNotSetIdForAutoIncrement(): void
    {
        $entity = new TestEntity(['name' => 'Test']);

        $this->idGenerator->fillId($entity);

        // ID should not be set for auto-increment
        $this->assertFalse(isset($entity->id));
    }

    public function testFillIdsWithEmptyArray(): void
    {
        $result = $this->idGenerator->fillIds([]);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testFillIdsSkipsEntitiesWithExistingIds(): void
    {
        $entity1 = new TestEntity(['id' => 1, 'name' => 'Test 1']);
        $entity2 = new TestEntity(['id' => 2, 'name' => 'Test 2']);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');
        $this->mockNamedLookup->expects($this->never())->method('by');

        $this->idGenerator->fillIds([$entity1, $entity2]);

        // IDs should remain unchanged
        $this->assertSame(1, $entity1->id);
        $this->assertSame(2, $entity2->id);
    }

    public function testGenerateIdThrowsExceptionForUnknownStrategy(): void
    {
        $this->expectException(InvalidIdStrategyException::class);
        $this->expectExceptionMessage('Unknown ID generation strategy: bad-strategy');

        $this->idGenerator->generateId(new InvalidStrategyEntity());
    }

    public function testGenerateIdUsesConfiguredStrategyAndCachesGeneratorInstance(): void
    {
        $idPackageGenerator = $this->createMock(IdPackageInterface::class);
        $idPackageGenerator->expects($this->exactly(2))
            ->method('next')
            ->willReturnOnConsecutiveCalls(101, 102);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($idPackageGenerator);

        $entity = new SnowflakeEntity();

        $this->assertSame(101, $this->idGenerator->generateId($entity));
        $this->assertSame(102, $this->idGenerator->generateId($entity));
    }

    public function testGenerateIdsForAutoIncrementUsesShardAndInclusiveRange(): void
    {
        $context = ['tenant' => 'acme'];
        $dbClient = $this->createMock(ClientInterface::class);

        $this->mockSharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestEntity::class, $context)
            ->willReturn(['default', 'test_entities']);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('allocateIds')
            ->with('test_entities', 'id', 3)
            ->willReturn([10, 12]);

        $this->assertSame([10, 11, 12], $this->idGenerator->generateIds(TestEntity::class, 3, $context));
    }

    public function testFillIdsAssignsOnlyMissingIdsAndKeepsExistingValues(): void
    {
        $entityA = new TestEntity(['name' => 'A']);
        $entityB = new TestEntity(['id' => 88, 'name' => 'B']);
        $entityC = new TestEntity(['name' => 'C']);
        $dbClient = $this->createMock(ClientInterface::class);

        $this->mockSharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestEntity::class, $entityA)
            ->willReturn(['default', 'test_entities']);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('allocateIds')
            ->with('test_entities', 'id', 2)
            ->willReturn([20, 21]);

        $this->idGenerator->fillIds([$entityA, $entityB, $entityC]);

        $this->assertSame(20, $entityA->id);
        $this->assertSame(88, $entityB->id);
        $this->assertSame(21, $entityC->id);
    }

    public function testGenerateIdsUsesMappedPrimaryKeyColumnWhenAllocatingFromDatabase(): void
    {
        $context = ['tenant' => 'mapped'];
        $dbClient = $this->createMock(ClientInterface::class);

        $this->mockSharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $context)
            ->willReturn(['default', 'test_orders']);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('allocateIds')
            ->with('test_orders', 'order_id', 2)
            ->willReturn([50, 51]);

        $this->assertSame(
            [50, 51],
            $this->idGenerator->generateIds(TestOrderWithMappedPrimaryKey::class, 2, $context)
        );
    }

    public function testGenerateIdsWithStrategyUsesGeneratorAndSkipsDatabaseAllocation(): void
    {
        $idPackageGenerator = $this->createMock(IdPackageInterface::class);
        $idPackageGenerator->expects($this->exactly(3))
            ->method('next')
            ->willReturnOnConsecutiveCalls(701, 702, 703);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($idPackageGenerator);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $this->assertSame([701, 702, 703], $this->idGenerator->generateIds(SnowflakeEntity::class, 3));
    }

    public function testGenerateIdCachesGeneratorsPerEntityClass(): void
    {
        $snowflakeGenerator = $this->createMock(IdPackageInterface::class);
        $snowflakeGenerator->expects($this->exactly(2))
            ->method('next')
            ->willReturnOnConsecutiveCalls(901, 902);

        $uuidGenerator = $this->createMock(IdPackageInterface::class);
        $uuidGenerator->expects($this->exactly(2))
            ->method('next')
            ->willReturnOnConsecutiveCalls('uuid-1', 'uuid-2');

        $aliases = [];
        $this->mockNamedLookup->expects($this->exactly(2))
            ->method('by')
            ->willReturnCallback(static function (string $type, string $alias) use (&$aliases, $snowflakeGenerator, $uuidGenerator): IdPackageInterface {
                $aliases[] = $alias;

                return match ($alias) {
                    'Switon\\Id\\IdGeneratorInterface#snowflake' => $snowflakeGenerator,
                    'Switon\\Id\\IdGeneratorInterface#uuid4' => $uuidGenerator,
                    default => throw new RuntimeException('unexpected generator alias: ' . $alias),
                };
            });

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $this->assertSame(901, $this->idGenerator->generateId(new SnowflakeEntity()));
        $this->assertSame(902, $this->idGenerator->generateId(new SnowflakeEntity()));
        $this->assertSame('uuid-1', $this->idGenerator->generateId(new UuidStrategyEntity()));
        $this->assertSame('uuid-2', $this->idGenerator->generateId(new UuidStrategyEntity()));

        $this->assertSame(
            [
                'Switon\\Id\\IdGeneratorInterface#snowflake',
                'Switon\\Id\\IdGeneratorInterface#uuid4',
            ],
            $aliases
        );
    }

    public function testFillIdsWithStrategyAssignsOnlyMissingIdsAndSkipsDatabaseAllocation(): void
    {
        $idPackageGenerator = $this->createMock(IdPackageInterface::class);
        $idPackageGenerator->expects($this->exactly(2))
            ->method('next')
            ->willReturnOnConsecutiveCalls(801, 802);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($idPackageGenerator);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $entityA = new SnowflakeEntity();
        $entityB = new SnowflakeEntity();
        $entityB->id = 99;
        $entityC = new SnowflakeEntity();

        $this->idGenerator->fillIds([$entityA, $entityB, $entityC]);

        $this->assertSame(801, $entityA->id);
        $this->assertSame(99, $entityB->id);
        $this->assertSame(802, $entityC->id);
    }

    public function testGenerateIdsWithStrategyReturnsEmptyArrayWhenCountZero(): void
    {
        $idPackageGenerator = $this->createMock(IdPackageInterface::class);
        $idPackageGenerator->expects($this->never())->method('next');

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($idPackageGenerator);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $this->assertSame([], $this->idGenerator->generateIds(SnowflakeEntity::class, 0));
    }

    public function testFillIdWithStrategyAssignsGeneratedId(): void
    {
        $idPackageGenerator = $this->createMock(IdPackageInterface::class);
        $idPackageGenerator->expects($this->once())
            ->method('next')
            ->willReturn(1001);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($idPackageGenerator);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $entity = new SnowflakeEntity();
        $this->idGenerator->fillId($entity);

        $this->assertSame(1001, $entity->id);
    }

    public function testFillIdsUsesFirstEntityNeedingIdAsShardingContext(): void
    {
        $entityWithId = new TestEntity(['id' => 7, 'name' => 'ready']);
        $entityMissingId = new TestEntity(['name' => 'missing']);
        $dbClient = $this->createMock(ClientInterface::class);

        $this->mockSharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestEntity::class, $entityMissingId)
            ->willReturn(['default', 'test_entities']);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('allocateIds')
            ->with('test_entities', 'id', 1)
            ->willReturn([42, 42]);

        $this->idGenerator->fillIds([$entityWithId, $entityMissingId]);

        $this->assertSame(7, $entityWithId->id);
        $this->assertSame(42, $entityMissingId->id);
    }

    public function testGenerateIdsThrowsForUnknownStrategyBeforeDatabaseAllocation(): void
    {
        $this->mockSharding->expects($this->never())->method('getUniqueShard');
        $this->mockNamedLookup->expects($this->never())->method('by');

        $this->expectException(InvalidIdStrategyException::class);
        $this->expectExceptionMessage('Unknown ID generation strategy: bad-strategy');

        $this->idGenerator->generateIds(InvalidStrategyEntity::class, 2);
    }

    public function testFillIdsUsesEntityClassOfFirstItemForBatchGeneration(): void
    {
        $snowflakeGenerator = $this->createMock(IdPackageInterface::class);
        $snowflakeGenerator->expects($this->exactly(2))
            ->method('next')
            ->willReturnOnConsecutiveCalls(3001, 3002);

        $this->mockNamedLookup->expects($this->once())
            ->method('by')
            ->with(IdPackageInterface::class, 'Switon\\Id\\IdGeneratorInterface#snowflake')
            ->willReturn($snowflakeGenerator);

        $this->mockSharding->expects($this->never())->method('getUniqueShard');

        $entityWithId = new SnowflakeEntity();
        $entityWithId->id = 1234;
        $entityA = new SnowflakeEntity();
        $entityB = new SnowflakeEntity();

        $this->idGenerator->fillIds([$entityWithId, $entityA, $entityB]);

        $this->assertSame(1234, $entityWithId->id);
        $this->assertSame(3001, $entityA->id);
        $this->assertSame(3002, $entityB->id);
    }

}

class InvalidStrategyEntity extends \Switon\Orm\Entity
{
    #[Id(strategy: 'bad-strategy')]
    public int $id;
}

class SnowflakeEntity extends \Switon\Orm\Entity
{
    #[Id(strategy: 'snowflake')]
    public int $id;
}

class UuidStrategyEntity extends \Switon\Orm\Entity
{
    #[Id(strategy: 'uuid')]
    public string $id;
}
