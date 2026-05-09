<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Owner;
use Switon\Orm\Entity;

#[Owner('admin_id')]
class TestEntityWithExplicitOwnerField extends Entity
{
    #[Id]
    public int $id;

    public int $admin_id;

    public string $name;
}
