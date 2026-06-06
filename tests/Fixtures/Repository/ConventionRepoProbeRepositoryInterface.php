<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Repository;

use Switon\Orm\RepositoryInterface;

/**
 * Naming-convention probe: EntityMetadata prefers this token over the concrete repository class when present.
 */
interface ConventionRepoProbeRepositoryInterface extends RepositoryInterface
{
}
