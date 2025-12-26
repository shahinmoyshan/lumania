<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIContract;
use Spark\Facades\Http;
use function sprintf;

class Ollama implements AIContract
{
    private string $API_ENDPOINT = 'http://localhost:11434';

    public function __construct(
        protected string $model = 'gemma:2b',
        protected float $temperature = 0.7
    ) {
    }

    public function setModel(string $model)
    {
        $this->model = $model;
    }

    public function ask(string $prompt, array $options = []): string
    {
        $http = Http::post(
            sprintf('%s/api/generate', $this->API_ENDPOINT),
            [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => $this->temperature,
                    'num_predict' => $options['max_tokens'] ?? 256,
                    'top_k' => 40,
                    'top_p' => 0.9
                ]
            ]
        );

        if ($http->failed()) {
            throw new \Exception('Failed to connect to Ollama API: ' . $http->body());
        }

        return $http->get('response');
    }
}