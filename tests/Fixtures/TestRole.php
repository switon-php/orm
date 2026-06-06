<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestRole extends Entity
{
    #[Id]
    public int $role_id;

    public string $name;
}
