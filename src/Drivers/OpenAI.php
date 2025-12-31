<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIDriverContract;
use Spark\Facades\Http;
use function sprintf;

class OpenAI implements AIDriverContract
{
    /** @var string The API endpoint for the OpenAI Chat Completions API */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor for the OpenAI driver.
     *
     * @param string $apiKey The OpenAI API key.
     * @param string $model The model to use (default: gpt-4o-mini)
     */
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini'
    ) {
    }

    /**
     * Sets the model to be used for generating content.
     *
     * @param string $model
     */
    public function setModel(string $model)
    {
        $this->model = $model;
    }

    /**
     * Asks a question to the OpenAI API.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public function ask(string $prompt, array $options = []): string
    {
        $http = Http::withToken($this->apiKey)
            ->post(
                self::API_ENDPOINT,
                [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    ...$options,
                ]
            );

        if ($http->ok()) {
            $response = $http->json();
            if (isset($response['choices'], $response['choices'][0]['message']['content'])) {
                return trim($response['choices'][0]['message']['content']);
            }
        }

        throw new \Exception(
            sprintf(
                'HTTP Error: %s, message: %s',
                $http->status(),
                $http->get('error.message', 'Unknown error occurred while communicating with OpenAI API.')
            )
        );
    }
}