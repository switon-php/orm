<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestMorphChild extends Entity
{
    #[Id]
    public int $id;

    public string $commentable_table;

    public int $commentable_id;

    public string $title;
}
