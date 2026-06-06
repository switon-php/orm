<?php

declare(strict_types=1);

namespace Switon\Orm\PhpStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Switon\Orm\EntityMetadataInterface;

final class EntityMetadataCreateQueryMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    use EntityClassTypeResolver;

    public function getClass(): string
    {
        return EntityMetadataInterface::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'createQuery';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        return $this->resolveQueryTypeFromEntityClassArgument($methodCall, $scope);
    }
}
