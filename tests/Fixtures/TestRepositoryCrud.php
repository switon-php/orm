<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\FilterPreprocessor;
use Switon\Orm\FilterPreprocessorInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;

class TestRepositoryCrud extends AbstractRepository
{
    protected string $entityClass = TestEntity::class;

    protected EntityManagerInterface $entityManager;
    protected QueryBuilderInterface $queryBuilder;
    protected EntityHydratorInterface $entityHydrator;

    public function __construct(
        EntityMetadataInterface      $entityMetadata,
        RelationManagerInterface     $relationManager,
        EntityManagerInterface       $entityManager,
        QueryBuilderInterface        $queryBuilder,
        EntityHydratorInterface      $entityHydrator,
        ?FilterPreprocessorInterface $filterPreprocessor = null,
        ?EventDispatcherInterface    $eventDispatcher = null,
    )
    {
        $this->entityMetadata = $entityMetadata;
        $this->relationManager = $relationManager;
        $this->entityManager = $entityManager;
        $this->queryBuilder = $queryBuilder;
        $this->entityHydrator = $entityHydrator;
        $this->filterPreprocessor = $filterPreprocessor ?? new FilterPreprocessor($entityMetadata);
        $this->eventDispatcher = $eventDispatcher ?? new PassthroughEventDispatcher();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }
}
