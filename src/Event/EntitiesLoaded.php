<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Orm\Entity;

/**
 * Dispatched after multiple entities are hydrated from query rows.
 *
 * Log category: <code>switon.orm.entity.loaded.bulk</code>
 *
 * @see \Switon\Orm\AbstractRepository::hydrateEntities() Typical emitter
 * @see \Switon\Orm\Event\EntityLoaded
 */
#[EventLevel(Severity::DEBUG)]
class EntitiesLoaded implements JsonSerializable
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
