<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use Switon\Core\MakerInterface;
use Switon\Db\Client;
use Switon\Db\ClientInterface;
use Switon\Db\TransactionManagerInterface;
use Switon\Di\NamedLookupInterface;
use Switon\Orm\EntityFillerInterface;
use Switon\Orm\EntityManager;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\Event\EntitiesCreated;
use Switon\Orm\Event\EntitiesCreating;
use Switon\Orm\Event\EntityCreated;
use Switon\Orm\Event\EntityCreating;
use Switon\Orm\Event\EntityDeleted;
use Switon\Orm\Event\EntityDeleting;
use Switon\Orm\Event\EntityUnchanged;
use Switon\Orm\Event\EntityUpdated;
use Switon\Orm\Event\EntityUpdating;
use Switon\Orm\Exception\PrimaryKeyImmutableException;
use Switon\Orm\Exception\PrimaryKeyMissingException;
use Switon\Orm\IdGeneratorInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\ShardingInterface;
use Switon\Orm\Tests\Fixtures\TestOrderWithMappedPrimaryKey;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;
use Switon\Sharding\ShardingManagerInterface;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;
use stdClass;

#[AllowMockObjectsWithoutExpectations]
class EntityManagerMappedPrimaryKeyTest extends TestCase
{
    protected EntityManager $entityManager;
    protected MockObject|EntityMetadataInterface $entityMetadata;
    protected MockObject|ShardingInterface $sharding;
    protected MockObject|EventDispatcherInterface $eventDispatcher;
    protected MockObject|ValidatorInterface $validator;
    protected MockObject|EntityFillerInterface $autoFiller;
    protected MockObject|RelationManagerInterface $relationManager;
    protected MockObject|NamedLookupInterface $namedLookup;
    protected MockObject|QueryBuilderInterface $queryBuilder;
    protected MockObject|IdGeneratorInterface $idGenerator;
    protected MockObject|TransactionManagerInterface $transactionManager;
    protected MockObject|ShardingManagerInterface $shardingManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->sharding = $this->createMock(ShardingInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->autoFiller = $this->createMock(EntityFillerInterface::class);
        $this->relationManager = $this->createMock(RelationManagerInterface::class);
        $this->namedLookup = $this->createMock(NamedLookupInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->idGenerator = $this->createMock(IdGeneratorInterface::class);
        $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
        $this->shardingManager = $this->createMock(ShardingManagerInterface::class);

        $this->entityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->entityMetadata->method('getFields')->willReturn(['id', 'order_no', 'status']);
        $this->entityMetadata->method('getColumnMap')->willReturn(['id' => 'order_id']);
        $this->entityMetadata->method('getConstraints')->willReturn([]);
        $this->entityMetadata->method('getFillable')->willReturn([]);

        $this->validator->method('beginValidate')
            ->willReturnCallback(fn (array|object $source): Validation => new Validation($this->validator, $source));

        $this->container->set(EntityMetadataInterface::class, $this->entityMetadata);
        $this->container->set(ShardingInterface::class, $this->sharding);
        $this->container->set(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->set(ValidatorInterface::class, $this->validator);
        $this->container->set(EntityFillerInterface::class, $this->autoFiller);
        $this->container->set(RelationManagerInterface::class, $this->relationManager);
        $this->container->set(NamedLookupInterface::class, $this->namedLookup);
        $this->container->set(QueryBuilderInterface::class, $this->queryBuilder);
        $this->container->set(IdGeneratorInterface::class, $this->idGenerator);
        $this->container->set(TransactionManagerInterface::class, $this->transactionManager);
        $this->container->set(ShardingManagerInterface::class, $this->shardingManager);
        $this->container->set(MakerInterface::class, $this->createMock(MakerInterface::class));

        $this->entityManager = $this->make(EntityManager::class);
    }

    public function testUpdateUsesMappedPrimaryKeyColumnInDatabaseConditions(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 101, 'order_no' => 'ORD-NEW', 'status' => 2]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 101, 'order_no' => 'ORD-OLD', 'status' => 1]);
        $dbClient = $this->createMock(Client::class);

        $this->autoFiller->expects($this->once())
            ->method('onUpdating')
            ->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);
        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityUpdating || $event instanceof EntityUpdated
            ));

        $dbClient->expects($this->once())
            ->method('update')
            ->with(
                'test_orders',
                ['order_no' => 'ORD-NEW', 'status' => 2],
                ['order_id' => 101]
            )
            ->willReturn(1);

        $this->assertSame($entity, $this->entityManager->update($entity, $original));
    }

    public function testDeleteUsesMappedPrimaryKeyColumnInDatabaseConditions(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 55, 'order_no' => 'ORD-055', 'status' => 1]);
        $dbClient = $this->createMock(Client::class);

        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);
        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityDeleting || $event instanceof EntityDeleted
            ));

        $dbClient->expects($this->once())
            ->method('delete')
            ->with('test_orders', ['order_id' => 55])
            ->willReturn(1);

        $this->assertSame($entity, $this->entityManager->delete($entity));
    }

    public function testUpdateThrowsWhenPrimaryKeyIsMissing(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-NO-ID', 'status' => 1]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 1, 'order_no' => 'ORD-001', 'status' => 1]);

        $this->expectException(PrimaryKeyMissingException::class);

        $this->entityManager->update($entity, $original);
    }

    public function testUpdateThrowsWhenPrimaryKeyIsChanged(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 2, 'order_no' => 'ORD-002', 'status' => 1]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 1, 'order_no' => 'ORD-001', 'status' => 1]);

        $this->expectException(PrimaryKeyImmutableException::class);

        $this->entityManager->update($entity, $original);
    }

    public function testDeleteThrowsWhenPrimaryKeyIsMissing(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-NO-ID', 'status' => 1]);

        $this->expectException(PrimaryKeyMissingException::class);

        $this->entityManager->delete($entity);
    }

    public function testUpdateValidatesUserChangedFieldsBeforeOnUpdatingAndDoesNotValidateFillerValues(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 7, 'order_no' => 'ORD-NEW', 'status' => 1]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 7, 'order_no' => 'ORD-OLD', 'status' => 1]);
        $dbClient = $this->createMock(Client::class);
        $constraint = $this->createMock(\Switon\Validating\ConstraintInterface::class);

        // Replace metadata so getConstraints() is not shadowed by setUp's empty stub (PHPUnit stub order).
        $this->entityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->entityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->entityMetadata->method('getFields')->willReturn(['id', 'order_no', 'status']);
        $this->entityMetadata->method('getColumnMap')->willReturn(['id' => 'order_id']);
        $this->entityMetadata->method('getConstraints')->willReturn(['order_no' => [$constraint]]);
        $this->entityMetadata->method('getFillable')->willReturn([]);
        $this->container->replace(EntityMetadataInterface::class, $this->entityMetadata);
        $this->entityManager = $this->make(EntityManager::class);
        $constraint->method('getMessage')->willReturn('');
        $constraint->expects($this->once())
            ->method('validate')
            ->with($this->callback(function (Validation $v) use ($entity): bool {
                $this->assertSame($entity, $v->source);
                $this->assertSame('order_no', $v->field);
                $this->assertSame('ORD-NEW', $v->value);

                return true;
            }))
            ->willReturn(true);

        $this->autoFiller->expects($this->once())
            ->method('onUpdating')
            ->with($entity)
            ->willReturnCallback(static function (TestOrderWithMappedPrimaryKey $value): void {
                $value->status = 200;
            });

        $this->validator->expects($this->once())->method('endValidate');

        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);
        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityUpdating || $event instanceof EntityUpdated
            ));

        $dbClient->expects($this->once())
            ->method('update')
            ->with(
                'test_orders',
                ['order_no' => 'ORD-NEW', 'status' => 200],
                ['order_id' => 7]
            )
            ->willReturn(1);

        $this->assertSame($entity, $this->entityManager->update($entity, $original));
    }

    public function testCreateManyLoadsDefaultValuesByMappedPrimaryKeyColumn(): void
    {
        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 101, 'order_no' => 'ORD-101']);
        $entityB = new TestOrderWithMappedPrimaryKey(['id' => 102, 'order_no' => 'ORD-102']);
        $entities = [$entityA, $entityB];

        $dbClient = new class () {
            /** @var array<int, array{table: string, records: array}> */
            public array $bulkInsertCalls = [];

            public function bulkInsert(string $table, array $records): int
            {
                $this->bulkInsertCalls[] = [
                    'table' => $table,
                    'records' => $records,
                ];

                return count($records);
            }
        };
        $query = $this->createMock(QueryInterface::class);

        $this->entityMetadata->method('getConnection')->willReturn('default');
        $this->entityMetadata->method('getTable')->willReturn('test_orders');

        $this->shardingManager->expects($this->once())
            ->method('unique')
            ->with('default', 'test_orders', $entities)
            ->willReturn(['default', 'test_orders']);
        $this->idGenerator->expects($this->once())
            ->method('fillIds')
            ->with($entities);
        $this->autoFiller->expects($this->exactly(2))
            ->method('onCreating');

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())
            ->method('setColumnMap')
            ->with(['id' => 'order_id'])
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('select')
            ->with(['id', 'status'])
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('where')
            ->with(['order_id' => [101, 102]])
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('execute')
            ->willReturn([
                ['id' => 101, 'status' => 1],
                ['id' => 102, 'status' => 2],
            ]);

        $this->eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntitiesCreating
                    || $event instanceof EntitiesCreated
                    || $event instanceof EntityCreating
                    || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->createMany($entities);

        $this->assertSame($entities, $result);
        $this->assertSame(
            [[
                'table' => 'test_orders',
                'records' => [
                    ['order_no' => 'ORD-101', 'order_id' => 101],
                    ['order_no' => 'ORD-102', 'order_id' => 102],
                ],
            ]],
            $dbClient->bulkInsertCalls
        );
        $this->assertSame(1, $entityA->status);
        $this->assertSame(2, $entityB->status);
    }

    public function testCreateLoadsDefaultValuesUsingMappedPrimaryKeyColumn(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-701']);
        $dbClient = $this->createMock(Client::class);
        $query = $this->createMock(QueryInterface::class);

        $this->idGenerator->expects($this->once())
            ->method('fillId')
            ->with($entity);
        $this->autoFiller->expects($this->once())
            ->method('onCreating')
            ->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('insert')
            ->with('test_orders', ['order_no' => 'ORD-701'], true)
            ->willReturn('701');

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())->method('setColumnMap')->with(['id' => 'order_id'])->willReturnSelf();
        $query->expects($this->once())->method('select')->with(['status'])->willReturnSelf();
        $query->expects($this->once())->method('where')->with(['order_id' => 701])->willReturnSelf();
        $query->expects($this->once())->method('execute')->willReturn([['status' => 3]]);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->create($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(701, $entity->id);
        $this->assertSame(3, $entity->status);
    }

    public function testCreateWithExistingPrimaryKeySkipsIdFillAndUsesPlainInsert(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 702, 'order_no' => 'ORD-702', 'status' => 1]);
        $dbClient = $this->createMock(Client::class);

        $this->idGenerator->expects($this->never())->method('fillId');
        $this->autoFiller->expects($this->once())->method('onCreating')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);
        $this->queryBuilder->expects($this->never())->method('create');

        $dbClient->expects($this->once())
            ->method('insert')
            ->with(
                'test_orders',
                $this->callback(static function (array $record): bool {
                    return $record['order_id'] === 702
                        && $record['order_no'] === 'ORD-702'
                        && $record['status'] === 1
                        && count($record) === 3;
                })
            )
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->create($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(702, $entity->id);
    }

    public function testCreateUsesTransactionClientWhenTransactionIsEnabled(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 703, 'order_no' => 'ORD-703', 'status' => 1]);
        $txClient = $this->createMock(Client::class);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');
        $this->idGenerator->expects($this->never())->method('fillId');

        $this->autoFiller->expects($this->once())->method('onCreating')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);
        $this->queryBuilder->expects($this->never())->method('create');

        $txClient->expects($this->once())
            ->method('insert')
            ->with(
                'test_orders',
                $this->callback(static fn (array $record): bool => ($record['order_id'] ?? null) === 703)
            )
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $this->assertSame($entity, $this->entityManager->create($entity));
    }

    public function testPutWithoutPrimaryKeyUsesInsertIdAndMappedFields(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-900', 'status' => 1]);
        $dbClient = $this->createMock(Client::class);

        $this->idGenerator->expects($this->once())->method('fillId')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('insert')
            ->with('test_orders', ['order_no' => 'ORD-900', 'status' => 1], true)
            ->willReturn('900');

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->put($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(900, $entity->id);
    }

    public function testPutWithExistingPrimaryKeySkipsIdFillAndUsesMappedPrimaryKeyField(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 901, 'order_no' => 'ORD-901', 'status' => 2]);
        $dbClient = $this->createMock(Client::class);

        $this->idGenerator->expects($this->never())->method('fillId');
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('insert')
            ->with(
                'test_orders',
                $this->callback(static fn (array $record): bool => ($record['order_id'] ?? null) === 901
                    && ($record['order_no'] ?? null) === 'ORD-901'
                    && ($record['status'] ?? null) === 2)
            )
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $this->assertSame($entity, $this->entityManager->put($entity));
    }

    public function testUpdateDispatchesEntityUnchangedWhenOnUpdatingRevertsAllChanges(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 42, 'order_no' => 'ORD-NEW', 'status' => 2]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 42, 'order_no' => 'ORD-OLD', 'status' => 1]);

        $this->autoFiller->expects($this->once())
            ->method('onUpdating')
            ->with($entity)
            ->willReturnCallback(static function (TestOrderWithMappedPrimaryKey $e) use ($original): void {
                $e->order_no = $original->order_no;
                $e->status = $original->status;
            });

        $this->sharding->expects($this->never())->method('getUniqueShard');
        $this->namedLookup->expects($this->never())->method('by');
        $dispatched = [];
        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched): object {
                $dispatched[] = $event;

                return $event;
            });

        $result = $this->entityManager->update($entity, $original);

        $this->assertInstanceOf(EntityUpdating::class, $dispatched[0]);
        $this->assertInstanceOf(EntityUnchanged::class, $dispatched[1]);
        $this->assertSame($entity, $result);
        $this->assertSame('ORD-OLD', $entity->order_no);
        $this->assertSame(1, $entity->status);
    }

    public function testUpdateWithoutChangesSkipsDatabaseAndFillsMissingFieldsFromOriginal(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 1001, 'order_no' => 'ORD-1001']);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 1001, 'order_no' => 'ORD-1001', 'status' => 5]);

        $this->autoFiller->expects($this->never())->method('onUpdating');
        $this->sharding->expects($this->never())->method('getUniqueShard');
        $this->namedLookup->expects($this->never())->method('by');
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityUnchanged
            ));

        $result = $this->entityManager->update($entity, $original);

        $this->assertSame($entity, $result);
        $this->assertSame(1001, $entity->id);
        $this->assertSame('ORD-1001', $entity->order_no);
        $this->assertSame(5, $entity->status);
    }

    public function testCreateManyWithCompleteFieldsSkipsDefaultValueReloadQuery(): void
    {
        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 1101, 'order_no' => 'ORD-1101', 'status' => 1]);
        $entityB = new TestOrderWithMappedPrimaryKey(['id' => 1102, 'order_no' => 'ORD-1102', 'status' => 2]);
        $entities = [$entityA, $entityB];

        $dbClient = new class () extends Client {
            /** @var array<int, array{table: string, records: array}> */
            public array $bulkInsertCalls = [];

            public function __construct()
            {
            }

            public function bulkInsert(string $table, array $records): int
            {
                $this->bulkInsertCalls[] = [
                    'table' => $table,
                    'records' => $records,
                ];

                return count($records);
            }
        };

        $this->entityMetadata->method('getConnection')->willReturn('default');
        $this->entityMetadata->method('getTable')->willReturn('test_orders');

        $this->shardingManager->expects($this->once())
            ->method('unique')
            ->with('default', 'test_orders', $entities)
            ->willReturn(['default', 'test_orders']);
        $this->idGenerator->expects($this->once())
            ->method('fillIds')
            ->with($entities);
        $this->autoFiller->expects($this->exactly(2))
            ->method('onCreating');

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);
        $this->queryBuilder->expects($this->never())->method('create');

        $this->eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntitiesCreating
                    || $event instanceof EntitiesCreated
                    || $event instanceof EntityCreating
                    || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->createMany($entities);

        $this->assertSame($entities, $result);
        $this->assertEquals(
            [[
                'table' => 'test_orders',
                'records' => [
                    ['order_id' => 1101, 'order_no' => 'ORD-1101', 'status' => 1],
                    ['order_id' => 1102, 'order_no' => 'ORD-1102', 'status' => 2],
                ],
            ]],
            $dbClient->bulkInsertCalls
        );
    }

    public function testCreateManyUsesTransactionClientWhenTransactionEnabled(): void
    {
        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 1201, 'order_no' => 'ORD-1201', 'status' => 1]);
        $entityB = new TestOrderWithMappedPrimaryKey(['id' => 1202, 'order_no' => 'ORD-1202', 'status' => 2]);
        $entities = [$entityA, $entityB];

        $txClient = new class () extends Client {
            /** @var array<int, array{table: string, records: array}> */
            public array $bulkInsertCalls = [];

            public function __construct()
            {
            }

            public function bulkInsert(string $table, array $records): int
            {
                $this->bulkInsertCalls[] = [
                    'table' => $table,
                    'records' => $records,
                ];

                return count($records);
            }
        };

        $this->entityMetadata->method('getConnection')->willReturn('default');
        $this->entityMetadata->method('getTable')->willReturn('test_orders');

        $this->shardingManager->expects($this->once())
            ->method('unique')
            ->with('default', 'test_orders', $entities)
            ->willReturn(['default', 'test_orders']);
        $this->idGenerator->expects($this->once())
            ->method('fillIds')
            ->with($entities);
        $this->autoFiller->expects($this->exactly(2))
            ->method('onCreating');

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');
        $this->queryBuilder->expects($this->never())->method('create');

        $this->eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntitiesCreating
                    || $event instanceof EntitiesCreated
                    || $event instanceof EntityCreating
                    || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->createMany($entities);

        $this->assertSame($entities, $result);
        $this->assertEquals(
            [[
                'table' => 'test_orders',
                'records' => [
                    ['order_id' => 1201, 'order_no' => 'ORD-1201', 'status' => 1],
                    ['order_id' => 1202, 'order_no' => 'ORD-1202', 'status' => 2],
                ],
            ]],
            $txClient->bulkInsertCalls
        );
    }

    public function testUpdateUsesTransactionClientWhenTransactionEnabled(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 1301, 'order_no' => 'ORD-1301', 'status' => 2]);
        $original = new TestOrderWithMappedPrimaryKey(['id' => 1301, 'order_no' => 'ORD-1301', 'status' => 1]);
        $txClient = $this->createMock(Client::class);

        $this->autoFiller->expects($this->once())
            ->method('onUpdating')
            ->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $txClient->expects($this->once())
            ->method('update')
            ->with('test_orders', ['status' => 2], ['order_id' => 1301])
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityUpdating || $event instanceof EntityUpdated
            ));

        $this->assertSame($entity, $this->entityManager->update($entity, $original));
    }

    public function testDeleteUsesTransactionClientWhenTransactionEnabled(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 1302, 'order_no' => 'ORD-1302', 'status' => 1]);
        $txClient = $this->createMock(Client::class);

        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $txClient->expects($this->once())
            ->method('delete')
            ->with('test_orders', ['order_id' => 1302])
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityDeleting || $event instanceof EntityDeleted
            ));

        $this->assertSame($entity, $this->entityManager->delete($entity));
    }

    public function testCreateUsesTransactionClientAndReloadsDefaultsWhenMissingFields(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-1401']);
        $txClient = $this->createMock(Client::class);
        $query = $this->createMock(QueryInterface::class);

        $this->idGenerator->expects($this->once())->method('fillId')->with($entity);
        $this->autoFiller->expects($this->once())->method('onCreating')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $txClient->expects($this->once())
            ->method('insert')
            ->with('test_orders', ['order_no' => 'ORD-1401'], true)
            ->willReturn('1401');

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())->method('setColumnMap')->with(['id' => 'order_id'])->willReturnSelf();
        $query->expects($this->once())->method('select')->with(['status'])->willReturnSelf();
        $query->expects($this->once())->method('where')->with(['order_id' => 1401])->willReturnSelf();
        $query->expects($this->once())->method('execute')->willReturn([['status' => 9]]);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->create($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(1401, $entity->id);
        $this->assertSame(9, $entity->status);
    }

    public function testCreateManyUsesTransactionClientAndReloadsDefaultsForMissingFields(): void
    {
        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 1501, 'order_no' => 'ORD-1501']);
        $entityB = new TestOrderWithMappedPrimaryKey(['id' => 1502, 'order_no' => 'ORD-1502']);
        $entities = [$entityA, $entityB];

        $txClient = new class () extends Client {
            /** @var array<int, array{table: string, records: array}> */
            public array $bulkInsertCalls = [];

            public function __construct()
            {
            }

            public function bulkInsert(string $table, array $records): int
            {
                $this->bulkInsertCalls[] = [
                    'table' => $table,
                    'records' => $records,
                ];

                return count($records);
            }
        };
        $query = $this->createMock(QueryInterface::class);

        $this->entityMetadata->method('getConnection')->willReturn('default');
        $this->entityMetadata->method('getTable')->willReturn('test_orders');

        $this->shardingManager->expects($this->once())
            ->method('unique')
            ->with('default', 'test_orders', $entities)
            ->willReturn(['default', 'test_orders']);
        $this->idGenerator->expects($this->once())
            ->method('fillIds')
            ->with($entities);
        $this->autoFiller->expects($this->exactly(2))
            ->method('onCreating');

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())->method('setColumnMap')->with(['id' => 'order_id'])->willReturnSelf();
        $query->expects($this->once())->method('select')->with(['id', 'status'])->willReturnSelf();
        $query->expects($this->once())->method('where')->with(['order_id' => [1501, 1502]])->willReturnSelf();
        $query->expects($this->once())
            ->method('execute')
            ->willReturn([
                ['id' => 1501, 'status' => 11],
                ['id' => 1502, 'status' => 12],
            ]);

        $this->eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntitiesCreating
                    || $event instanceof EntitiesCreated
                    || $event instanceof EntityCreating
                    || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->createMany($entities);

        $this->assertSame($entities, $result);
        $this->assertEquals(
            [[
                'table' => 'test_orders',
                'records' => [
                    ['order_id' => 1501, 'order_no' => 'ORD-1501'],
                    ['order_id' => 1502, 'order_no' => 'ORD-1502'],
                ],
            ]],
            $txClient->bulkInsertCalls
        );
        $this->assertSame(11, $entityA->status);
        $this->assertSame(12, $entityB->status);
    }

    public function testPutUsesTransactionClientWhenPrimaryKeyExists(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['id' => 1601, 'order_no' => 'ORD-1601', 'status' => 1]);
        $txClient = $this->createMock(Client::class);

        $this->idGenerator->expects($this->never())->method('fillId');
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $txClient->expects($this->once())
            ->method('insert')
            ->with(
                'test_orders',
                $this->callback(static fn (array $record): bool => ($record['order_id'] ?? null) === 1601
                    && ($record['order_no'] ?? null) === 'ORD-1601'
                    && ($record['status'] ?? null) === 1)
            )
            ->willReturn(1);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->put($entity);

        $this->assertSame($entity, $result);
    }

    public function testPutUsesTransactionClientAndInsertIdWhenPrimaryKeyMissing(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-1602', 'status' => 2]);
        $txClient = $this->createMock(Client::class);

        $this->idGenerator->expects($this->once())->method('fillId')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->transactionManager->expects($this->once())
            ->method('useTransaction')
            ->with('default')
            ->willReturn(true);
        $this->transactionManager->expects($this->once())
            ->method('ensureConnection')
            ->with('default');
        $this->transactionManager->expects($this->once())
            ->method('getCurrentClient')
            ->with('default')
            ->willReturn($txClient);

        $this->namedLookup->expects($this->never())->method('by');

        $txClient->expects($this->once())
            ->method('insert')
            ->with('test_orders', ['order_no' => 'ORD-1602', 'status' => 2], true)
            ->willReturn('1602');

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->put($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(1602, $entity->id);
    }

    public function testCreateManyWithEmptyInputReturnsEmptyArrayWithoutSideEffects(): void
    {
        $this->shardingManager->expects($this->never())->method('unique');
        $this->idGenerator->expects($this->never())->method('fillIds');
        $this->autoFiller->expects($this->never())->method('onCreating');
        $this->namedLookup->expects($this->never())->method('by');
        $this->transactionManager->expects($this->never())->method('useTransaction');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->queryBuilder->expects($this->never())->method('create');

        $this->assertSame([], $this->entityManager->createMany([]));
    }

    public function testCreateManyThrowsWhenMixedEntityTypesProvided(): void
    {
        $this->expectException(\Switon\Orm\Exception\CreateManyEntityTypeMismatchException::class);
        $this->expectExceptionMessage(
            'createMany() expects TestOrderWithMappedPrimaryKey[]; item 1 is TestEntity.'
        );

        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 1, 'order_no' => 'ORD-1', 'status' => 1]);
        $entityB = new \Switon\Orm\Tests\Fixtures\TestEntity(['id' => 2, 'name' => 'x', 'status' => 1]);

        $this->entityManager->createMany([$entityA, $entityB]);
    }

    public function testCreateManyThrowsWhenNonEntityProvided(): void
    {
        $this->expectException(\Switon\Orm\Exception\CreateManyInvalidEntityException::class);
        $this->expectExceptionMessage('createMany() expects Entity[]; item 0 is string.');

        $this->entityManager->createMany(['oops']);
    }

    public function testDescribeEntityTypeReflectsScalarDebugType(): void
    {
        $m = new ReflectionMethod(EntityManager::class, 'describeEntityType');
        $m->setAccessible(true);

        $this->assertSame('int', $m->invoke($this->entityManager, 7));
        $this->assertSame('string', $m->invoke($this->entityManager, 'x'));
    }

    public function testDescribeEntityTypeUsesShortClassNameForObjects(): void
    {
        $m = new ReflectionMethod(EntityManager::class, 'describeEntityType');
        $m->setAccessible(true);

        $this->assertSame('stdClass', $m->invoke($this->entityManager, new stdClass()));
    }

    public function testGetChangedFieldsDetectsPresenceAndValueDifferences(): void
    {
        $m = new ReflectionMethod(EntityManager::class, 'getChangedFields');
        $m->setAccessible(true);

        $this->assertSame([], $m->invoke($this->entityManager, ['a'], ['a' => 1], ['a' => 1]));
        $this->assertSame(['a'], $m->invoke($this->entityManager, ['a'], ['a' => 1], ['a' => 2]));
        $this->assertSame(['a', 'b'], $m->invoke($this->entityManager, ['a', 'b'], ['a' => 1], ['b' => 2]));
    }

    public function testCreateDoesNotSetDefaultFieldWhenReloadQueryReturnsEmpty(): void
    {
        $entity = new TestOrderWithMappedPrimaryKey(['order_no' => 'ORD-1701']);
        $dbClient = $this->createMock(Client::class);
        $query = $this->createMock(QueryInterface::class);

        $this->idGenerator->expects($this->once())->method('fillId')->with($entity);
        $this->autoFiller->expects($this->once())->method('onCreating')->with($entity);
        $this->sharding->expects($this->once())
            ->method('getUniqueShard')
            ->with(TestOrderWithMappedPrimaryKey::class, $entity)
            ->willReturn(['default', 'test_orders']);

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $dbClient->expects($this->once())
            ->method('insert')
            ->with('test_orders', ['order_no' => 'ORD-1701'], true)
            ->willReturn('1701');

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())->method('setColumnMap')->with(['id' => 'order_id'])->willReturnSelf();
        $query->expects($this->once())->method('select')->with(['status'])->willReturnSelf();
        $query->expects($this->once())->method('where')->with(['order_id' => 1701])->willReturnSelf();
        $query->expects($this->once())->method('execute')->willReturn([]);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntityCreating || $event instanceof EntityCreated
            ));

        $result = $this->entityManager->create($entity);

        $this->assertSame($entity, $result);
        $this->assertSame(1701, $entity->id);
        $this->assertFalse(isset($entity->status));
    }

    public function testCreateManyTriggersWarningWhenDefaultReloadMissesEntityId(): void
    {
        $entityA = new TestOrderWithMappedPrimaryKey(['id' => 1801, 'order_no' => 'ORD-1801']);
        $entityB = new TestOrderWithMappedPrimaryKey(['id' => 1802, 'order_no' => 'ORD-1802']);
        $entities = [$entityA, $entityB];

        $dbClient = new class () {
            /** @var array<int, array{table: string, records: array}> */
            public array $bulkInsertCalls = [];

            public function bulkInsert(string $table, array $records): int
            {
                $this->bulkInsertCalls[] = [
                    'table' => $table,
                    'records' => $records,
                ];

                return count($records);
            }
        };
        $query = $this->createMock(QueryInterface::class);

        $this->entityMetadata->method('getConnection')->willReturn('default');
        $this->entityMetadata->method('getTable')->willReturn('test_orders');

        $this->shardingManager->expects($this->once())
            ->method('unique')
            ->with('default', 'test_orders', $entities)
            ->willReturn(['default', 'test_orders']);
        $this->idGenerator->expects($this->once())
            ->method('fillIds')
            ->with($entities);
        $this->autoFiller->expects($this->exactly(2))->method('onCreating');

        $this->namedLookup->expects($this->once())
            ->method('by')
            ->with(ClientInterface::class, 'default')
            ->willReturn($dbClient);

        $this->queryBuilder->expects($this->once())
            ->method('create')
            ->with(TestOrderWithMappedPrimaryKey::class)
            ->willReturn($query);

        $query->expects($this->once())->method('setTable')->willReturnSelf();
        $query->expects($this->once())->method('setColumnMap')->with(['id' => 'order_id'])->willReturnSelf();
        $query->expects($this->once())->method('select')->with(['id', 'status'])->willReturnSelf();
        $query->expects($this->once())->method('where')->with(['order_id' => [1801, 1802]])->willReturnSelf();
        $query->expects($this->once())
            ->method('execute')
            ->willReturn([
                ['id' => 1801, 'status' => 21],
            ]);

        $this->eventDispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event): bool => $event instanceof EntitiesCreating
                    || $event instanceof EntitiesCreated
                    || $event instanceof EntityCreating
                    || $event instanceof EntityCreated
            ));

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;
            return true;
        });
        try {
            $result = $this->entityManager->createMany($entities);
        } finally {
            restore_error_handler();
        }

        $this->assertSame($entities, $result);
        $this->assertSame(21, $entityA->status);
        $this->assertFalse(isset($entityB->status));
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Undefined array key', $warnings[0]);
    }
}
