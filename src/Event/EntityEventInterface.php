<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use Switon\Orm\Entity;

/**
 * Contract for entity lifecycle events.
 *
 * Guidance: Use these events for create/update/delete lifecycle hooks when listeners need current and original entity state.
 *
 * @see \Switon\Orm\Event\AbstractEntityEvent
 * @see \Switon\Orm\Entity::onEvent()
 * @see \Switon\Orm\AbstractEntityManager::dispatchEvent()
 */
interface EntityEventInterface
{
    /**
     * Get the entity being processed.
     */
    public function getEntity(): Entity;

    /**
     * Get original entity state before modification.
     *
     * Returns null for create events.
     */
    public function getOriginal(): ?Entity;

    /**
     * Check whether any specified field changed.
     *
     * Returns false when original data is unavailable.
     *
     * @param array<string> $fields Field names to compare
     */
    public function hasChanged(array $fields): bool;
}
