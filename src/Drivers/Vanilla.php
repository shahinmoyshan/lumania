<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIDriverContract;

class Vanilla implements AIDriverContract
{
    /**
     * @param string $model The model version to use, defaults to 'vanilla'
     */
    public function __construct(protected string $model = 'vanilla')
    {
    }

    /**
     * Sets the model to be used for generating content.
     *
     * @param string $model The model version to set.
     */
    public function setModel(string $model)
    {
        $this->model = $model;
    }

    /**
     * Generates a response based on the provided prompt and context.
     * 
     * @param string $prompt The input prompt for the AI model.
     * @param array $context The contextual information to guide the response.
     * @return string The generated response from the AI model.
     * @throws \Exception If the specified model file does not exist.
     */
    public function ask(string $prompt, array $context = []): string
    {
        $modelPath = dir_path(
            dirname(__DIR__) . '/resources/models/' . $this->model . '.php'
        );

        if (!is_file($modelPath)) {
            throw new \Exception("Model file not found: $modelPath");
        }

        $model = require $modelPath;
        $model->knowledgeBase($context);

        try {
            return $model->ask($prompt);
        } catch (\Throwable $e) {
            return "Internal Error: " . $e->getMessage();
        }
    }
}