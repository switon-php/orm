<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\MakerInterface;
use Switon\Orm\QueryBuilder;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class QueryBuilderAdvancedTest extends TestCase
{
    protected QueryBuilder $queryBuilder;
    protected MockObject|MakerInterface $mockObjectMaker;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockQuery = $this->createMock(QueryInterface::class);
        $this->mockQuery->method('from')->willReturnSelf();

        $this->mockObjectMaker = $this->createMock(MakerInterface::class);
        $this->mockObjectMaker->method('make')->willReturn($this->mockQuery);

        $this->container->set(MakerInterface::class, $this->mockObjectMaker);
        $this->queryBuilder = $this->container->make(QueryBuilder::class);
    }

    public function testCreateReturnsQueryInstance(): void
    {
        $query = $this->queryBuilder->create(TestEntity::class);

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateWithDifferentEntityClasses(): void
    {
        $query1 = $this->queryBuilder->create(TestEntity::class);
        $query2 = $this->queryBuilder->create('AnotherEntity');

        $this->assertInstanceOf(QueryInterface::class, $query1);
        $this->assertInstanceOf(QueryInterface::class, $query2);
    }

    public function testCreateMultipleTimesReturnsNewInstances(): void
    {
        // Each call to create() should call objectMaker->make() which returns a new mock instance
        // For this test, configure mock to return different instances
        $mockQuery1 = $this->createMock(QueryInterface::class);
        $mockQuery1->method('from')->willReturnSelf();
        $mockQuery2 = $this->createMock(QueryInterface::class);
        $mockQuery2->method('from')->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')
            ->willReturnOnConsecutiveCalls($mockQuery1, $mockQuery2);

        $this->container->replace(MakerInterface::class, $mockObjectMaker);
        $queryBuilder = $this->container->make(QueryBuilder::class);

        $query1 = $queryBuilder->create(TestEntity::class);
        $query2 = $queryBuilder->create(TestEntity::class);

        $this->assertNotSame($query1, $query2);
    }

    public function testCreateWithEmptyEntityClass(): void
    {
        $query = $this->queryBuilder->create('');

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateWithNamespacedEntityClass(): void
    {
        $query = $this->queryBuilder->create('App\\Entity\\User');

        $this->assertInstanceOf(QueryInterface::class, $query);
    }
}
