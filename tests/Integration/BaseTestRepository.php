<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Db\ClientInterface;
use Switon\Di\ContainerInterface;
use Switon\Orm\AbstractRepository;
use Switon\Orm\EntityHydratorInterface;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\EntityMetadataInterface;
use Switon\Orm\FilterPreprocessorInterface;
use Switon\Orm\QueryBuilderInterface;
use Switon\Orm\RelationManagerInterface;

/**
 * Base repository class for integration tests.
 *
 * Provides concrete implementations of abstract methods required by AbstractRepository.
 * All integration test repositories should extend this class.
 */
abstract class BaseTestRepository extends AbstractRepository
{
    protected EntityManagerInterface $entityManager;
    protected QueryBuilderInterface $queryBuilder;
    protected array $withRelations = [];

    public function __construct(
        ClientInterface        $db,
        EntityManagerInterface $entityManager,
        ContainerInterface     $container
    )
    {
        // Get dependencies from container
        $this->entityMetadata = $container->get(EntityMetadataInterface::class);
        $this->relationManager = $container->get(RelationManagerInterface::class);
        $this->filterPreprocessor = $container->get(FilterPreprocessorInterface::class);
        $this->entityHydrator = $container->get(EntityHydratorInterface::class);
        $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        $this->entityManager = $entityManager;
        $this->queryBuilder = $container->get(QueryBuilderInterface::class);

        // Set entity class before calling parent constructor
        $this->entityClass = $this->getEntityClass();

        parent::__construct();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }

    /**
     * Specify relations to eager load (for testing convenience).
     *
     * @param string $relation Relation name
     * @return static
     */
    public function with(string $relation): static
    {
        $this->withRelations[$relation] = [];
        return $this;
    }

    /**
     * Override find to support with() method.
     */
    public function find(int|string $id, array $fields = []): ?\Switon\Orm\Entity
    {
        // Merge with relations into fields
        $fields = array_merge($fields, $this->withRelations);
        $this->withRelations = []; // Reset

        return parent::find($id, $fields);
    }

    /**
     * Override all to support with() method.
     */
    public function all(array $filters = [], array $fields = [], array $orders = []): array
    {
        // Merge with relations into fields
        $fields = array_merge($fields, $this->withRelations);
        $this->withRelations = []; // Reset

        return parent::all($filters, $fields, $orders);
    }
}
