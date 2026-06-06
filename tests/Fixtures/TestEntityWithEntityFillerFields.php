<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithEntityFillerFields extends Entity
{
    #[Id]
    public int $id;

    public string $name;

    // EntityFiller fields
    public int $created_at;
    public string $created_by;
    public int $updated_at;
    public string $updated_by;
}
