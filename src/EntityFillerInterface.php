<?php

declare(strict_types=1);

namespace Switon\Orm;

/**
 * Contract for automatically filling entity fields during writes.
 *
 * Guidance:
 * - use this for deterministic automatic fields such as timestamps, actor data, or version counters
 * - keep write-time filling logic here instead of spreading it through repositories or entities
 *
 * Road-signs:
 * - onCreating fills create-time fields
 * - onUpdating fills update-time fields
 * - entity manager invokes it directly
 *
 * @see \Switon\Orm\EntityFiller
 * @see \Switon\Orm\EntityManagerInterface
 * @see \Switon\Orm\AbstractEntityManager
 * @see \Switon\Orm\Entity
 */
interface EntityFillerInterface
{
    /**
     * Fills entity fields when creating an entity.
     *
     * Actively called by EntityManager before persisting a new entity. Typically fills creation
     * timestamps, creator information, and other audit fields that should be set only once.
     *
     * **Common Fields to Fill:**
     * - <code>created_at</code>: Creation timestamp (int or string based on property type)
     * - <code>created_by</code>: ID (int) or name (string) of user creating the entity, based on property type
     * - <code>creator_ip</code>: IP address of creator
     * - <code>version</code>: Initial version number (e.g., 1)
     *
     * @param Entity $entity Entity being created
     */
    public function onCreating(Entity $entity): void;

    /**
     * Fills entity fields when updating an entity.
     *
     * Actively called by EntityManager before persisting entity updates. Typically fills update
     * timestamps, updater information, and other audit fields that should be refreshed on each update.
     *
     * **Common Fields to Fill:**
     * - <code>updated_at</code>: Last update timestamp (int or string based on property type)
     * - <code>updated_by</code>: ID (int) or name (string) of user updating the entity, based on property type
     * - <code>updater_ip</code>: IP address of updater
     * - <code>version</code>: Increment version number for optimistic locking
     *
     * @param Entity $entity Entity being updated
     */
    public function onUpdating(Entity $entity): void;
}
