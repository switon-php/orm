<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Core\Attribute\Autowired;

/**
 * Default SQL-backed repository base.
 *
 * @template T of Entity
 *
 * @extends AbstractRepository<T>
 *
 * Road-signs:
 * - entity class is inferred by naming convention
 * - query methods live in AbstractRepository
 * - entityManager handles writes
 * - queryBuilder creates queries
 *
 * Guidance: Extend this class for normal SQL repositories and keep subclasses focused on domain queries.
 *
 * @see \Switon\Orm\AbstractRepository
 * @see \Switon\Orm\RepositoryInterface
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\QueryBuilderInterface
 */
class Repository extends AbstractRepository
{
    /** @var EntityManagerInterface<T> */
    #[Autowired] protected EntityManagerInterface $entityManager;

    /** @var QueryBuilderInterface<T> */
    #[Autowired] protected QueryBuilderInterface $queryBuilder;

    /**
     * Returns the entity manager used for repository write operations.
     *
     * @return EntityManagerInterface<T> Entity manager instance
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Returns the query builder used to create repository queries.
     *
     * @return QueryBuilderInterface<T> Query builder instance
     */
    protected function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }
}
