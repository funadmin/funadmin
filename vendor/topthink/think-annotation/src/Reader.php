<?php

namespace think\annotation;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

class Reader
{

    /**
     * @template T of object
     * @param ReflectionClass|ReflectionMethod $ref
     * @param class-string<T> $name
     * @return array<T>
     */
    public function getAnnotations($ref, $name)
    {
        return array_map(function (ReflectionAttribute $attribute) {
            return $attribute->newInstance();
        }, $ref->getAttributes($name, ReflectionAttribute::IS_INSTANCEOF));
    }

    /**
     * @template T of object
     * @param ReflectionClass|ReflectionMethod $ref
     * @param class-string<T> $name
     * @return T|null
     */
    public function getAnnotation($ref, $name)
    {
        $attributes = $this->getAnnotations($ref, $name);

        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return null;
    }

}
