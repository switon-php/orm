<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Core\Attribute\Autowired;
use Switon\Core\MakerInterface;
use Switon\Query\QueryInterface;

/**
 * Default query builder that creates entity-aware query instances.
 *
 * Guidance: Keep <code>queryClass</code> compatible with <code>QueryInterface</code> before overriding the default binding.
 *
 * @see \Switon\Orm\QueryBuilderInterface
 * @see \Switon\Orm\EntityManagerInterface::query()
 * @see \Switon\Orm\AbstractRepository::select()
 */
class QueryBuilder implements QueryBuilderInterface
{
    #[Autowired] protected string $queryClass = 'Switon\\Query\\Query';
    #[Autowired] protected MakerInterface $maker;

    /** {@inheritDoc} */
    public function create(string $entityClass, ?string $alias = null): QueryInterface
    {
        /** @var QueryInterface $query */
        $query = $this->maker->make($this->queryClass);
        return $query->from($entityClass, $alias);
    }
}
