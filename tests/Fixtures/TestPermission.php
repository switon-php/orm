<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestPermission extends Entity
{
    #[Id]
    public int $permission_id;

    public string $name;
}
