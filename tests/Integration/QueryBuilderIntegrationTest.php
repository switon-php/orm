<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\MakerInterface;
use Switon\Orm\QueryBuilder;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Tests\Fixtures\TestEntity;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

use function interface_exists;

#[AllowMockObjectsWithoutExpectations]
class QueryBuilderIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(QueryInterface::class, true)) {
            $this->markTestSkipped('Query package dependency not available');
        }
    }

    /**
     * Create a QueryBuilder instance with the given mock dependencies.
     */
    protected function createQueryBuilder(
        MockObject|MakerInterface $mockObjectMaker,
        MockObject|QueryInterface $mockQuery
    ): QueryBuilderInterface {
        $this->container->set(MakerInterface::class, $mockObjectMaker);
        return $this->container->make(QueryBuilder::class);
    }

    public function testQueryBuilderCanBeInstantiated(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->method('from')->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);

        $this->assertInstanceOf(QueryBuilderInterface::class, $queryBuilder);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    public function testCreateReturnsQueryInterface(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->method('from')->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);
        $query = $queryBuilder->create(TestEntity::class);

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateWithAliasReturnsQueryInterface(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->method('from')->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);
        $query = $queryBuilder->create(TestEntity::class, 't');

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateUsesContainerToMakeQuery(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->method('from')->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->expects($this->once())
            ->method('make')
            ->with('Switon\\Query\\Query')
            ->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);
        $queryBuilder->create(TestEntity::class);
    }

    public function testCreateCallsQueryFromWithCorrectParameters(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $mockQuery->expects($this->once())
            ->method('from')
            ->with(TestEntity::class, null)
            ->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);
        $queryBuilder->create(TestEntity::class);
    }

    public function testCreateWithAliasCallsQueryFromWithAlias(): void
    {
        $mockQuery = $this->createMock(QueryInterface::class);
        $alias = 't';
        $mockQuery->expects($this->once())
            ->method('from')
            ->with(TestEntity::class, $alias)
            ->willReturnSelf();

        $mockObjectMaker = $this->createMock(MakerInterface::class);
        $mockObjectMaker->method('make')->willReturn($mockQuery);

        $queryBuilder = $this->createQueryBuilder($mockObjectMaker, $mockQuery);
        $queryBuilder->create(TestEntity::class, $alias);
    }
}
