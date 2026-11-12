<?php
declare(strict_types=1);

namespace ReleaseHub\Core;

use ReflectionClass;

/** Container tự nối (autowiring) rất nhỏ theo kiểu khai báo trong constructor. */
final class Container
{
    /** @var array<string,object> */
    private static array $instances = [];

    public static function get(string $class): object
    {
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        $args = [];
        if ($ctor) {
            foreach ($ctor->getParameters() as $p) {
                $type = $p->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $name = $type->getName();
                    $args[] = $name === \PDO::class ? Database::connection() : self::get($name);
                } elseif ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                }
            }
        }
        return self::$instances[$class] = $ref->newInstanceArgs($args);
    }

    public static function reset(): void
    {
        self::$instances = [];
    }
}
