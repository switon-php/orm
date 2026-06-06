<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\Repository;

class RepositoryTestStub extends Repository
{
    public function exposeEntityManager(): EntityManagerInterface
    {
        return $this->getEntityManager();
    }

    public function exposeQueryBuilder(): QueryBuilderInterface
    {
        return $this->getQueryBuilder();
    }

    public function setDependencies(EntityManagerInterface $entityManager, QueryBuilderInterface $queryBuilder): void
    {
        $this->entityManager = $entityManager;
        $this->queryBuilder = $queryBuilder;
    }
}

#[AllowMockObjectsWithoutExpectations]
class RepositoryTest extends TestCase
{
    public function testGetEntityManagerReturnsAutowiredInstance(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);

        $repository = (new ReflectionClass(RepositoryTestStub::class))->newInstanceWithoutConstructor();

        $repository->setDependencies($entityManager, $queryBuilder);

        $this->assertSame($entityManager, $repository->exposeEntityManager());
    }

    public function testGetQueryBuilderReturnsAutowiredInstance(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilderInterface::class);

        $repository = (new ReflectionClass(RepositoryTestStub::class))->newInstanceWithoutConstructor();

        $repository->setDependencies($entityManager, $queryBuilder);

        $this->assertSame($queryBuilder, $repository->exposeQueryBuilder());
    }
}
