<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Contract for generating and filling entity identifiers.
 *
 * Road-signs:
 * - generate single id
 * - generate batch ids
 * - fill one entity
 * - fill many entities with shard awareness
 *
 * Guidance: For batch fills, keep entities compatible with one entity type and one resolved shard.
 *
 * @see \Switon\Orm\IdGenerator
 * @see \Switon\Orm\Attribute\Id
 * @see \Switon\Orm\ShardingInterface
 */
interface IdGeneratorInterface
{
    /**
     * Generate a single ID for an entity.
     *
     * For entities with custom ID generators (UUID, Snowflake, etc.), generates and returns the ID.
     * For entities using database auto-increment, returns null (ID will be set by database on insert).
     *
     * @param Entity $entity Entity instance
     * @return null|int|string Generated ID or null for auto-increment
     */
    public function generateId(Entity $entity): null|int|string;

    /**
     * Generate multiple IDs for an entity class
     *
     * @param string $entityClass Entity class name
     * @param int $count Number of IDs to generate
     * @param array|object|null $context Context for sharding (optional)
     * @return array Generated IDs array
     */
    public function generateIds(string $entityClass, int $count, $context = null): array;

    /**
     * Fill ID for a single entity.
     *
     * For entities with custom ID generators, immediately generates and sets the ID.
     * For entities using database auto-increment, does nothing (ID set by database on insert).
     *
     * Note: Does not guarantee ID will be filled - depends on entity's ID strategy.
     *
     * @param Entity $entity Entity to fill ID for
     */
    public function fillId(Entity $entity): void;

    /**
     * Fill IDs for multiple entities at once.
     *
     * Guarantees all entities will have IDs filled. More efficient than calling fillId() multiple times.
     * Can handle database auto-increment by pre-allocating ID ranges.
     * Suitable for bulk operations like data import where IDs are required upfront.
     *
     * **Sharding Limitation (Important):**
     * When using database auto-increment pre-allocation, all entities must resolve to the same shard
     * (single database + single table). Otherwise sharding resolution may produce multiple shards and
     * cause an exception.
     *
     * @param array<Entity> $entities Array of entities to fill IDs for
     */
    public function fillIds(array $entities): void;
}
