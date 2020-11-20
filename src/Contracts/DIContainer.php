<?php


namespace Rest\Contracts;


interface DIContainer
{
    public function resolve(string $name, ?string $parentName = null);

    public function bind(string $name, $value, ?string $parentName = null);

    public function singleton(string $name, $value, ?string $parentName = null);
}
