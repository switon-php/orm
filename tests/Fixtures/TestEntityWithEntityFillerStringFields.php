<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithEntityFillerStringFields extends Entity
{
    #[Id]
    public int $id;

    public string $name;

    // EntityFiller fields
    public string $created_at;
    public string $created_by;
    public string $updated_at;
    public string $updated_by;
}
