<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\App;
use Switon\Core\ContainerInterface;
use Switon\Orm\QueryBuilder;
use Switon\Orm\Tests\TestCase;
use Switon\Query\QueryInterface;

#[AllowMockObjectsWithoutExpectations]
class QueryBuilderTest extends TestCase
{
    protected QueryBuilder $queryBuilder;
    protected MockObject|ContainerInterface $mockContainer;
    protected MockObject|QueryInterface $mockQuery;

    protected function setUp(): void
    {
        parent::setUp();


        $this->mockQuery = $this->createMock(QueryInterface::class);
        $this->mockQuery->method('from')->willReturnSelf();

        $this->mockContainer = $this->createMock(ContainerInterface::class);
        $this->mockContainer->method('make')->willReturn($this->mockQuery);

        $this->container->set(ContainerInterface::class, $this->mockContainer);
        $this->queryBuilder = $this->container->make(QueryBuilder::class);
    }

    public function testCreateReturnsQueryInterface(): void
    {
        $entityClass = 'Tests\Fixtures\TestEntity';

        $query = $this->queryBuilder->create($entityClass);

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateAcceptsEntityClassName(): void
    {
        $entityClass = 'Tests\Fixtures\TestUser';

        $query = $this->queryBuilder->create($entityClass);

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateAcceptsOptionalAlias(): void
    {
        $entityClass = 'Tests\Fixtures\TestEntity';
        $alias = 'e';

        $query = $this->queryBuilder->create($entityClass, $alias);

        $this->assertInstanceOf(QueryInterface::class, $query);
    }

    public function testCreateAcceptsNullAlias(): void
    {
        $entityClass = 'Tests\Fixtures\TestEntity';

        $query1 = $this->queryBuilder->create($entityClass);
        $query2 = $this->queryBuilder->create($entityClass, null);

        $this->assertInstanceOf(QueryInterface::class, $query1);
        $this->assertInstanceOf(QueryInterface::class, $query2);
    }

    public function testCreateCreatesDifferentInstancesForDifferentEntities(): void
    {
        $entityClass1 = 'Tests\Fixtures\TestEntity';
        $entityClass2 = 'Tests\Fixtures\TestUser';

        $query1 = $this->createMock(QueryInterface::class);
        $query2 = $this->createMock(QueryInterface::class);
        $query1->method('from')->willReturnSelf();
        $query2->method('from')->willReturnSelf();

        // Use real container with factory to return different queries
        $callCount = [0]; // Use array to allow reference
        $factory = new class($query1, $query2, $callCount) {
            public function __construct(
                private       $query1,
                private       $query2,
                private array $callCount
            )
            {
            }

            public function __invoke(array $parameters = []): QueryInterface
            {
                $this->callCount[0]++;
                return $this->callCount[0] === 1 ? $this->query1 : $this->query2;
            }
        };

        // Set global container for make() function using testing container
        $testContainer = new \Switon\Testing\Container\Container();
        $testContainer->set(ContainerInterface::class, $testContainer);
        $testContainer->set(\Psr\Container\ContainerInterface::class, $testContainer);
        $testContainer->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->createStub(\Psr\EventDispatcher\EventDispatcherInterface::class));
        $testContainer->set('Switon\\Query\\Query', $factory);
        App::setContainer($testContainer);

        $queryBuilder = $testContainer->make(QueryBuilder::class);

        $result1 = $queryBuilder->create($entityClass1);
        $result2 = $queryBuilder->create($entityClass2);

        $this->assertInstanceOf(QueryInterface::class, $result1);
        $this->assertInstanceOf(QueryInterface::class, $result2);
        $this->assertNotSame($result1, $result2);

        // Restore container
        App::setContainer($this->container);
    }
}

