<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Orm\EntityHydrator;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\FilterPreprocessor;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\Fixtures\TestRepository;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

/**
 * Test repository subclass that exposes protected methods for testing.
 *
 * This approach is preferred over Reflection as it:
 * - Makes intent clear (we're testing internal behavior)
 * - Is more maintainable (compiler catches signature changes)
 * - Follows open/closed principle
 */
class TestableRepository extends TestRepository
{
    /**
     * Delegate to {@see FilterPreprocessor} date conversion (same as repository preprocessing).
     */
    public function testConvertDateRange(string $field, mixed $min, mixed $max): array
    {
        $fp = new FilterPreprocessor($this->entityMetadata);
        $m = (new \ReflectionClass(FilterPreprocessor::class))->getMethod('convertDateRange');

        return $m->invoke($fp, TestEntity::class, $field, $min, $max);
    }

    /**
     * Expose protected where method for testing.
     */
    public function testWhere(array $filters): void
    {
        $this->where($filters);
    }
}

#[AllowMockObjectsWithoutExpectations]
class AbstractRepositoryDateAndFillTest extends TestCase
{
    protected TestableRepository $repository;
    protected MockObject|EntityMetadataInterface $mockEntityMetadata;
    protected MockObject|RelationManagerInterface $mockRelationManager;
    protected MockObject|EntityManagerInterface $mockEntityManager;
    protected MockObject|QueryBuilderInterface $mockQueryBuilder;
    protected MockObject|QueryInterface $mockQuery;
    protected EntityHydratorInterface $entityHydrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMetadata = $this->createMock(EntityMetadataInterface::class);
        $this->mockRelationManager = $this->createMock(RelationManagerInterface::class);
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->mockQuery = $this->createMock(QueryInterface::class);

        $this->mockEntityMetadata->method('getConnection')->willReturn('default');
        $this->mockEntityMetadata->method('getTable')->willReturn('test_entities');
        $this->mockEntityMetadata->method('getColumnMap')->willReturn([]);
        $this->mockEntityMetadata->method('getPrimaryKey')->willReturn('id');
        $this->mockEntityMetadata->method('getFieldType')
            ->willReturnCallback(static function (string $entityClass, string $field): string {
                return match ($field) {
                    'id' => 'int',
                    'name' => 'string',
                    'status' => 'int',
                    'price' => 'float',
                    'active', 'disabled', 'enabled', 'locked', 'active1', 'active2', 'active3', 'active4', 'active5', 'active6' => 'bool',
                    default => '',
                };
            });

        $this->container->set(EntityMetadataInterface::class, $this->mockEntityMetadata);
        $this->entityHydrator = $this->make(EntityHydrator::class);

        $this->mockQueryBuilder->method('create')->willReturn($this->mockQuery);

        $this->mockQuery->method('setTable')->willReturnSelf();
        $this->mockQuery->method('setColumnMap')->willReturnSelf();
        $this->mockQuery->method('select')->willReturnSelf();
        $this->mockQuery->method('where')->willReturnSelf();

        $this->repository = new TestableRepository(
            $this->mockEntityMetadata,
            $this->mockRelationManager,
            $this->mockEntityManager,
            $this->mockQueryBuilder,
            $this->entityHydrator
        );
    }

    public function testConvertDateRangeWithStringDates(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');

        // Act - use testable repository's exposed method
        [$min, $max] = $this->repository->testConvertDateRange('created_at', '2024-01-01', '2024-12-31');

        // Assert - 'U' format keeps timestamps as integers (no redundant date('U', $ts) conversion)
        $this->assertIsInt($min);
        $this->assertIsInt($max);
        $this->assertSame(strtotime('2024-01-01 00:00:00'), $min);
        $this->assertSame(strtotime('2024-12-31 23:59:59'), $max);
    }

    public function testConvertDateRangeWithDateFormat(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('Y-m-d H:i:s');

        // Act
        [$min, $max] = $this->repository->testConvertDateRange('created_at', '2024-01-01', '2024-12-31');

        // Assert - should convert to formatted strings
        $this->assertIsString($min);
        $this->assertIsString($max);
        $this->assertSame('2024-01-01 00:00:00', $min);
        $this->assertSame('2024-12-31 23:59:59', $max);
    }

    public function testConvertDateRangeWithTimestamps(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');
        $minTimestamp = strtotime('2024-01-01');
        $maxTimestamp = strtotime('2024-12-31');

        // Act
        [$min, $max] = $this->repository->testConvertDateRange('created_at', $minTimestamp, $maxTimestamp);

        // Assert - 'U' format keeps timestamps as integers
        $this->assertSame($minTimestamp, $min);
        $this->assertSame($maxTimestamp, $max);
    }

    public function testConvertDateRangeWithNumericStrings(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');

        // Act
        [$min, $max] = $this->repository->testConvertDateRange('created_at', '1704067200', '1735689599');

        // Assert - numeric strings are converted to int; 'U' format keeps them as integers
        $this->assertIsInt($min);
        $this->assertIsInt($max);
        $this->assertSame(1704067200, $min);
        $this->assertSame(1735689599, $max);
    }

    public function testWhereConvertsDateRangeOperator(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');
        $filters = [
            'created_at@=' => ['2024-01-01', '2024-12-31'],
        ];

        // Expect - should convert @= to ~= and convert dates
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return isset($filters['created_at~='])
                    && is_array($filters['created_at~='])
                    && count($filters['created_at~=']) === 2
                    && is_int($filters['created_at~='][0])
                    && is_int($filters['created_at~='][1]);
            }))
            ->willReturnSelf();

        // Act - use testable repository's exposed method
        $this->repository->testWhere($filters);
    }

    public function testWhereConvertsDateRangeMinOnly(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');
        $filters = [
            'created_at@=' => ['2024-01-01', null],
        ];

        // Expect - should convert @= to >= with only min value
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return isset($filters['created_at>='])
                    && is_int($filters['created_at>='])
                    && !isset($filters['created_at~='])
                    && !isset($filters['created_at@=']);
            }))
            ->willReturnSelf();

        // Act
        $this->repository->testWhere($filters);
    }

    public function testWhereConvertsDateRangeMaxOnly(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');
        $filters = [
            'created_at@=' => [null, '2024-12-31'],
        ];

        // Expect - should convert @= to <= with only max value
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return isset($filters['created_at<='])
                    && is_int($filters['created_at<='])
                    && !isset($filters['created_at~='])
                    && !isset($filters['created_at@=']);
            }))
            ->willReturnSelf();

        // Act
        $this->repository->testWhere($filters);
    }

    public function testWhereIgnoresDateRangeWithBothNull(): void
    {
        // Arrange
        $this->mockEntityMetadata->method('getDateFormat')->willReturn('U');
        $this->mockEntityMetadata->method('getFields')->willReturn(['id', 'name', 'status']);
        $filters = [
            'created_at@=' => [null, null],
        ];

        // Expect - should pass no date filter to where()
        $this->mockQuery->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($filters) {
                return !isset($filters['created_at>='])
                    && !isset($filters['created_at<='])
                    && !isset($filters['created_at~='])
                    && !isset($filters['created_at@=']);
            }))
            ->willReturnSelf();

        // Act
        $this->repository->testWhere($filters);
    }

    public function testFillIgnoresNullValues(): void
    {
        $data = [
            'name' => 'Test Name',
            'status' => null, // Should be ignored
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string', 'status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertSame('Test Name', $result->name);
        $this->assertFalse(isset($result->status)); // Should not be set
    }

    public function testFillConvertsIntType(): void
    {
        $data = [
            'status' => '123',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertSame(123, $result->status);
        $this->assertIsInt($result->status);
    }

    public function testFillConvertsFloatType(): void
    {
        $data = [
            'price' => '99.99',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['price' => 'float']);

        $result = $this->repository->fill($data);

        $this->assertSame(99.99, $result->price);
        $this->assertIsFloat($result->price);
    }

    public function testFillConvertsStringType(): void
    {
        $data = [
            'name' => 123,
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string']);

        $result = $this->repository->fill($data);

        $this->assertSame('123', $result->name);
        $this->assertIsString($result->name);
    }

    public function testFillConvertsBoolTypeFalseValues(): void
    {
        $data = [
            'active1' => '',
            'active2' => 0,
            'active3' => '0',
            'active4' => 'false',
            'active5' => 'off',
            'active6' => 'no',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn([
                'active1' => 'bool',
                'active2' => 'bool',
                'active3' => 'bool',
                'active4' => 'bool',
                'active5' => 'bool',
                'active6' => 'bool',
            ]);

        $result = $this->repository->fill($data);

        $this->assertFalse($result->active1);
        $this->assertFalse($result->active2);
        $this->assertFalse($result->active3);
        $this->assertFalse($result->active4);
        $this->assertFalse($result->active5);
        $this->assertFalse($result->active6);
    }

    public function testFillConvertsBoolTypeTrueValues(): void
    {
        $data = [
            'active1' => 1,
            'active2' => '1',
            'active3' => 'true',
            'active4' => 'on',
            'active5' => 'yes',
            'active6' => 'anything',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn([
                'active1' => 'bool',
                'active2' => 'bool',
                'active3' => 'bool',
                'active4' => 'bool',
                'active5' => 'bool',
                'active6' => 'bool',
            ]);

        $result = $this->repository->fill($data);

        $this->assertTrue($result->active1);
        $this->assertTrue($result->active2);
        $this->assertTrue($result->active3);
        $this->assertTrue($result->active4);
        $this->assertTrue($result->active5);
        $this->assertTrue($result->active6);
    }

    public function testFillOnlyFillsFillableFields(): void
    {
        $data = [
            'name' => 'Test Name',
            'status' => 1,
            'nonFillable' => 'Should be ignored',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string', 'status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertSame('Test Name', $result->name);
        $this->assertSame(1, $result->status);
        $this->assertFalse(isset($result->nonFillable));
    }

    public function testFillWithEmptyData(): void
    {
        $data = [];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string', 'status' => 'int']);

        $result = $this->repository->fill($data);

        $this->assertInstanceOf(TestEntity::class, $result);
        $this->assertFalse(isset($result->name));
        $this->assertFalse(isset($result->status));
    }

    public function testCreateRemovesPrimaryKeyFromArrayData(): void
    {
        $data = [
            'id' => 999, // Should be removed for security
            'name' => 'New Entity',
        ];

        $this->mockEntityMetadata->method('getFillable')
            ->willReturn(['name' => 'string']);

        $createdEntity = new TestEntity(['id' => 1, 'name' => 'New Entity']);

        $this->mockEntityManager->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($entity) {
                // Verify that primary key was removed
                return $entity instanceof TestEntity
                    && !isset($entity->id)
                    && $entity->name === 'New Entity';
            }))
            ->willReturn($createdEntity);

        $result = $this->repository->create($data);

        $this->assertSame(1, $result->id); // Should have auto-generated ID
    }
}
