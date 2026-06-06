<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Orm\Entity;

/**
 * Dispatched before a bulk entity create operation.
 *
 * Log category: <code>switon.orm.entities.creating</code>
 *
 * @see \Switon\Orm\EntityManager::createMany() Typical emitter
 * @see \Switon\Orm\Event\EntitiesCreated
 * @see \Switon\Orm\Event\EntityCreating Per-entity event
 */
#[EventLevel(Severity::DEBUG)]
class EntitiesCreating implements JsonSerializable
{
    /**
     * @param class-string<Entity> $entityClass
     * @param array<int, Entity> $entities
     */
    public function __construct(
        public string $entityClass,
        public array  $entities,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entityClass,
            'count' => count($this->entities),
        ];
    }
}
