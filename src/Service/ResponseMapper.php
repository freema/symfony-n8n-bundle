<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class ResponseMapper
{
    private readonly PropertyAccessor $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Map response data to a specific class
     *
     * @template T of object
     * @param array $data The response data
     * @param class-string<T> $className The target class name
     * @return T The mapped object
     */
    public function mapToClass(array $data, string $className): object
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        $reflection = new \ReflectionClass($className);
        
        // Try constructor with parameters first
        $constructor = $reflection->getConstructor();
        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            return $this->createWithConstructor($reflection, $constructor, $data);
        }

        // Fallback to property setting
        return $this->createWithProperties($reflection, $data);
    }

    private function createWithConstructor(\ReflectionClass $reflection, \ReflectionMethod $constructor, array $data): object
    {
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $paramName = $param->getName();
            $value = $data[$paramName] ?? null;
            
            if ($value === null && !$param->isOptional()) {
                throw new \InvalidArgumentException("Required parameter '{$paramName}' not found in response data");
            }
            
            $args[] = $value;
        }
        
        return $reflection->newInstanceArgs($args);
    }

    private function createWithProperties(\ReflectionClass $reflection, array $data): object
    {
        $object = $reflection->newInstance();
        
        foreach ($data as $key => $value) {
            if ($this->propertyAccessor->isWritable($object, $key)) {
                $this->propertyAccessor->setValue($object, $key, $value);
            }
        }
        
        return $object;
    }
}