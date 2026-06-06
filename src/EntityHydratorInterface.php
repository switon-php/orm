<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Contract for hydrating entities from arrays and dehydrating them back to write payloads.
 *
 * Road-signs:
 * - hydrate new entity
 * - hydrateInto existing entity
 * - dehydrate for persistence
 * - metadata drives field casting
 *
 * Guidance: Keep conversion rules here instead of spreading them across entities and repositories.
 *
 * @template T of Entity
 *
 * @see \Switon\Orm\EntityHydrator
 * @see \Switon\Orm\HydratableInterface
 * @see \Switon\Orm\EntityMetadataInterface
 */
interface EntityHydratorInterface
{
    /**
     * Hydrates a new entity instance from array data.
     *
     * When <code>$fields</code> is provided, only those fields are assigned.
     * The default implementation preserves null values and applies metadata-driven
     * casts for scalars, enums, date/time values, and JSON fields.
     *
     * @param class-string<T> $entityClass Entity class name
     * @param array<string, mixed> $data Source data
     * @param array<int, string>|null $fields Optional field allow-list
     *
     * @return T Hydrated entity instance
     */
    public function hydrate(string $entityClass, array $data, ?array $fields = null): Entity;

    /**
     * Hydrates an existing entity instance from array data.
     *
     * Existing property values are replaced only for the requested fields.
     * Use this when reloading default values or merging trusted data into an
     * already created entity.
     *
     * @param T $entity Target entity instance
     * @param array<string, mixed> $data Source data
     * @param array<int, string>|null $fields Optional field allow-list
     *
     * @return T Hydrated entity instance
     */
    public function hydrateInto(Entity $entity, array $data, ?array $fields = null): Entity;

    /**
     * Converts an entity into a persistence payload.
     *
     * The returned array contains only initialized persistent fields. Values are
     * converted to database-friendly scalars where needed.
     *
     * @param Entity $entity Entity instance to convert
     * @param array<int, string>|null $fields Optional field allow-list
     *
     * @return array<string, mixed> Persistence payload
     */
    public function dehydrate(Entity $entity, ?array $fields = null): array;
}
