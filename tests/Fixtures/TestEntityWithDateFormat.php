<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\DateFormat;
use Switon\Orm\Entity;

#[DateFormat('Y-m-d H:i:s')]
class TestEntityWithDateFormat extends Entity
{
    #[Id]
    public int $id;

    public string $name;
}
