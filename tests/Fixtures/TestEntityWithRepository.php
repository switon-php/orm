<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithRepository extends Entity
{
    #[Id]
    public int $id;

    public string $name;
}
