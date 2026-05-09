<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestItemWithMappedPrimaryKey extends Entity
{
    #[Id]
    #[Column('item_id')]
    public int $id;

    public string $name;
}
