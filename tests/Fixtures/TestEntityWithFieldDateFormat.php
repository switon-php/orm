<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\DateFormat;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithFieldDateFormat extends Entity
{
    #[Id]
    public int $id;

    #[DateFormat('Y-m-d H:i:s')]
    public \DateTimeImmutable $created_at;

    public \DateTimeImmutable $updated_at;
}
