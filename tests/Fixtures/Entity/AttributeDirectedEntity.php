<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures\Entity;

use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Repository;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\Tests\Fixtures\Repository\AttributeDirectedCustomRepository;

#[Repository(AttributeDirectedCustomRepository::class)]
#[Table('attribute_directed_entities')]
class AttributeDirectedEntity extends Entity
{
    #[Id]
    public int $id;

    public ?string $name = null;
}
