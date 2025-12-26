<?php

namespace Lumina\Contracts;

interface AIContract
{
    public function ask(string $prompt, array $options = []): string;
}