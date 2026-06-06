<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

/**
 * No #[Id] and no <code>id</code> property: primary key resolution falls through to table-name inference.
 * Inferred <code>role_id</code> from <code>test_roles</code> exists but is readonly, so inference must reject it.
 */
#[Table('test_roles')]
class TestEntityReadonlyInferredPk extends Entity
{
    public readonly int $role_id;

    public string $name;
}
