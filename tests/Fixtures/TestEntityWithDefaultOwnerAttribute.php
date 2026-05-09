<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Owner;
use Switon\Orm\Entity;

#[Owner]
class TestEntityWithDefaultOwnerAttribute extends Entity
{
    #[Id]
    public int $id;

    public string $created_by;

    public string $name;
}
