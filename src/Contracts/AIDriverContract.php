<?php

namespace Lumina\Contracts;

interface AIDriverContract
{
    public function ask(string $prompt, array $options = []): string;
}