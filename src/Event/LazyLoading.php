<?php

declare(strict_types=1);

namespace Switon\Orm\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Orm\Entity;

/**
 * Dispatched before loading one relation query via RelationManager::lazyLoad().
 *
 * Log category: <code>switon.orm.lazy.loading</code>
 *
 * @see \Switon\Orm\RelationManager::lazyLoad()
 * @see \Switon\Orm\Event\LazyLoaded
 */
#[EventLevel(Severity::DEBUG)]
class LazyLoading implements JsonSerializable
{
    public function __construct(
        public Entity $entity,
        public string $relationName,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'entity' => $this->entity::class,
            'relation' => $this->relationName,
            'mode' => 'lazy',
        ];
    }
}
