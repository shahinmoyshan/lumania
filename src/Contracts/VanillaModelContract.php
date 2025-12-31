<?php

namespace Lumina\Contracts;

use Spark\Contracts\Support\Arrayable;
use Spark\Support\Collection;

abstract class VanillaModelContract implements Arrayable
{
    public Collection $data;

    public function setup(array $data = [])
    {
        $this->data = new Collection($data);
    }

    abstract public function knowledgeBase(array $chunks): void;

    abstract public function ask(string $question): string;

    public function __get($name)
    {
        return $this->data->get($name);
    }

    public function __set($name, $value)
    {
        $this->data->put($name, $value);
    }

    public function __isset($name)
    {
        return $this->data->has($name);
    }

    public function __unset($name)
    {
        $this->data->forget($name);
    }

    public function data(): Collection
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return $this->data->toArray();
    }
}