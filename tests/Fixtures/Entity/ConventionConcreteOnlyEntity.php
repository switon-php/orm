<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Entity;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

#[Table('convention_concrete_only')]
class ConventionConcreteOnlyEntity extends Entity
{
    #[Id]
    public int $id;

    public ?string $name = null;
}
