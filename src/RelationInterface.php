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
     * @param class-string<Entity>|'' $related Related entity class, or empty when the handler already knows it
     */
    public function bind(string $self, string $related): void;

    /**
     * Returns whether the related entity class is known after {@see bind()}.
     *
     * Handlers that resolve related class later (e.g. many-to-many via junction) return true.
     */
    public function isRelatedEntityKnown(): bool;

    /**
     * Eager-load related data for multiple parent entities.
     *
     * @param array<Entity> $r Parent entities to enrich
     * @param QueryInterface<mixed> $relatedQuery Pre-configured query for loading related entities
     * @param string $name Relation property name on parent entities
     *
     * @return array<Entity> Parent entities with relation data attached
     */
    public function earlyLoad(array $r, QueryInterface $relatedQuery, string $name): array;

    /**
     * Create a lazy-loading query for one parent entity.
     *
     * @param Entity $entity Parent entity to load relationships for
     *
     * @return QueryInterface<mixed> Query for related entities
     */
    public function lazyLoad(Entity $entity): QueryInterface;

    /**
     * Returns the parent entity class bound to this relation.
     *
     * @return class-string<Entity>
     */
    public function getSelfEntityClass(): string;

    /**
     * Returns the related entity class bound to this relation.
     *
     * @return class-string<Entity>
     */
    public function getRelatedEntityClass(): string;

    /**
     * Returns the base query for the related entity class.
     *
     * @return QueryInterface<mixed> Query for loading related entities
     */
    public function getRelatedQuery(): QueryInterface;
}
