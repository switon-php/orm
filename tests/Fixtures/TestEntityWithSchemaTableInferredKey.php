<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Fixtures;

use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;

#[Table('schema.test_roles')]
class TestEntityWithSchemaTableInferredKey extends Entity
{
    // Should infer 'role_id' from table name 'schema.test_roles' -> base 'test_roles' -> last segment 'roles'
    // Tries 'rol_id' (from Naming::singular) first, then 'role_id' (removing 'es'), finds 'role_id'
    public int $role_id;

    public string $name;
}
