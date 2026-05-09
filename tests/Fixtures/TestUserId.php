<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestUserId extends Entity
{
    #[Id]
    public int $id;

    public ?string $name = null;
}
