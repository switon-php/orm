<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Query\QueryInterface;

/**
 * Contract for creating query instances from entity classes.
 *
 * Guidance: Return <code>QueryInterface</code>-compatible objects so repositories and entity managers can share the same query pipeline.
 *
 * @see \Switon\Orm\QueryBuilder
 * @see \Switon\Orm\EntityManagerInterface::query()
 * @see \Switon\Orm\AbstractRepository::select()
 *
 * @template T of Entity
 */
interface QueryBuilderInterface
{
    /**
     * Create a query instance for one entity class.
     *
     * @param class-string<T> $entityClass Entity class name
     * @param string|null $alias Optional table/collection alias for the query
     *
     * @return QueryInterface<T> Query instance ready for chaining
     *
     * @see \Switon\Orm\QueryBuilder::create() Default implementation
     * @see \Switon\Orm\EntityManagerInterface::query() Typical caller
     * @see \Switon\Orm\AbstractRepository::select() Typical caller
     */
    public function create(string $entityClass, ?string $alias = null): QueryInterface;
}
