<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Entity;

class TestEntityWithObjects extends Entity
{
    #[Id]
    public int $id;

    public ?string $name = null;

    public ?TestStatus $status = null;

    public ?TestColor $color = null;

    public ?TestJsonSerializableValue $metadata = null;

    public ?TestStringableValue $label = null;

    /** @var object|null Unknown object type for skip test */
    public ?object $unknown = null;
}
