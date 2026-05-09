<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\CurrentIdentity;
use Switon\Orm\Attribute\CurrentTime;
use Switon\Orm\Attribute\FixedValue;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithPropertyWriteFillerFields extends Entity
{
    #[Id]
    public int $id;

    #[CurrentIdentity]
    public int $owner_id;

    #[CurrentIdentity]
    public string $owner_name;

    #[FixedValue(0)]
    public int $read_at;

    #[CurrentIdentity]
    public int $editor_id;

    #[CurrentTime('status', 1, 0)]
    public int $published_at;

    public int $status;
}
