<?php

declare(strict_types=1);

namespace Switon\Orm\Attribute;

use Attribute;

/**
 * Declares the repository class for an entity when naming convention is not enough.
 *
 * Guidance: Prefer the default entity-to-repository naming convention; use this only when the repository class cannot be inferred reliably.
 *
 * @see \Switon\Orm\EntityMetadataInterface::getRepository()
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Repository
{
    /**
     * Creates a new Repository attribute instance.
     *
     * @param string $name The fully qualified repository class name (e.g., <code>App\Repository\CustomUserRepository::class</code>).
     */
    public function __construct(public string $name)
    {
    }
}