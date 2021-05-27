<?php

namespace Rest\DI;

use Closure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Rest\Contracts\DIContainer;
use Rest\Contracts\Singleton;
use Rest\Exceptions\DICannotConstructException;

class Container implements DIContainer
{
    protected static Container $instance;

    protected array $resolved = [];

    protected array $bind = [];

    protected array $singleton = [];

    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    /**
     * @param  mixed  $name
     * @param  string|null  $parentName
     * @return mixed
     * @throws DICannotConstructException
     * @throws ReflectionException
     */
    public function resolve(mixed $name, ?string $parentName = null): mixed
    {
        if (is_string($name)) {
            if (is_string($parentName)) {
                $key = "$name-$parentName";
                if (isset($this->resolved[$key])) {
                    return $this->resolved[$key];
                }

                if (isset($this->bind[$key])) {
                    return $this->saveSingletonAndReturn($key, $this->resolve($this->bind[$key], $parentName));
                }
            }

            if (isset($this->resolved[$name])) {
                return $this->resolved[$name];
            }

            if (isset($this->bind[$name])) {
                return $this->saveSingletonAndReturn($name, $this->resolve($this->bind[$name], $parentName));
            }
        }

        return $this->saveSingletonAndReturn($name, $this->constructInstance($name));
    }

    protected function saveSingletonAndReturn(mixed $name, mixed $object): mixed
    {
        $isSingleton = is_string($name) && (
                isset($this->singleton[$name])
                || (!isset($this->bind[$name]) && $object instanceof Singleton)
            );
        if ($isSingleton && !isset($this->resolved[$name])) {
            $this->resolved[$name] = $object;
        }
        return $object;
    }

    /**
     * @param  mixed  $name
     * @return mixed
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    protected function constructInstance(mixed $name): mixed
    {
        if ($name instanceof Closure) {
            return $name($this);
        }

        if (!is_string($name)) {
            return $name;
        }

        if (interface_exists($name)) {
            throw new DICannotConstructException(
                sprintf('Cannot construct interface %s without bind. Please bind this interface in Container', $name)
            );
        }

        if (!class_exists($name)) {
            throw new LogicException(sprintf('Class %s do not exists ', $name));
        }

        $reflection  = new ReflectionClass($name);
        $constructor = $reflection->getConstructor();

        if (is_null($constructor) || $constructor->getNumberOfParameters() == 0) {
            return new $name();
        }

        $resolvedParams = $this->buildParams($name, $constructor->getParameters());

        return new $name(...$resolvedParams);
    }

    /**
     * @param  string  $name
     * @param  ReflectionParameter[]  $params
     * @return array
     * @throws \ReflectionException
     * @throws \Rest\Exceptions\DICannotConstructException
     */
    protected function buildParams(string $name, array $params): array
    {
        $resolvedParams = [];
        foreach ($params as $param) {
            $type = $param->getType();

            if (is_null($type) || $type instanceof \ReflectionUnionType || ($type instanceof \ReflectionNamedType && $type->isBuiltin())) {
                if ($param->isDefaultValueAvailable()) {
                    $resolvedParams[] = $param->getDefaultValue();
                    continue;
                }
                throw new DICannotConstructException(
                    "Cannot construct $name because \${$param->getName()} is not initializable"
                );
            }
            /** @var \ReflectionNamedType $type */

            try {
                $resolvedParams[] = $this->resolve($type->getName(), $name);
            } catch (DICannotConstructException $exception) {
                if ($param->isDefaultValueAvailable()) {
                    $resolvedParams[] = $param->getDefaultValue();
                    continue;
                }
                throw $exception;
            }
        }
        return $resolvedParams;
    }

    public function bind(string $name, $value, ?string $parentName = null): static
    {
        if (is_string($parentName)) {
            $name = "$name-$parentName";
        }
        $this->bind[$name] = $value;
        return $this;
    }

    public function singleton(string $name, $value, ?string $parentName = null): static
    {
        if (is_string($parentName)) {
            $name = "$name-$parentName";
        }

        $this->singleton[$name] = true;

        $this->bind[$name] = $value;

        return $this;
    }
}
