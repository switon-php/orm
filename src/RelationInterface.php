<?php

declare(strict_types=1);

namespace Switon\Orm;

use Switon\Query\QueryInterface;

/**
 * Contract for relation algorithms between entity classes.
 *
 * Road-signs:
 * - bind entity classes
 * - earlyLoad attaches batches
 * - lazyLoad builds one relation query
 * - getRelatedQuery returns the base builder
 *
 * Guidance: Keep eager and lazy semantics aligned and bind the relation before use.
 *
 * @see \Switon\Orm\Relation\AbstractRelation
 * @see \Switon\Orm\RelationManagerInterface
 */
interface RelationInterface
{
    /**
     * Bind this relation to parent and related entity classes.
     *
     * @param class-string<Entity> $self Parent entity class
     * @param class-string<Entity> $related Related entity class
     */
    public function bind(string $self, string $related): void;

    /**
     * Eager-load related data for multiple parent entities.
     *
     * @param array<Entity> $r Parent entities to enrich
     * @param QueryInterface $relatedQuery Pre-configured query for loading related entities
     * @param string $name Relation property name on parent entities
     *
     * @return array<Entity> Parent entities with relation data attached
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array;

    /**
     * Create a lazy-loading query for one parent entity.
     *
     * @param Entity $entity Parent entity to load relationships for
     * @return QueryInterface Query for related entities
     */
    public function lazyLoad(Entity $entity): QueryInterface;

    /**
     * Get parent entity class name.
     *
     * @return class-string<Entity>
     */
    public function getSelfEntityClass(): string;

    /**
     * Get related entity class name.
     *
     * @return class-string<Entity>
     */
    public function getRelatedEntityClass(): string;

    /**
     * Get base query for related entity class.
     *
     * @return QueryInterface Query for loading related entities
     */
    public function getRelatedQuery(): QueryInterface;
}
