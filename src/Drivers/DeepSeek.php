<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIDriverContract;
use Spark\Facades\Http;
use function sprintf;

class DeepSeek implements AIDriverContract
{
    /** @param string The API endpoint for the DeepSeek AI. */
    private const API_ENDPOINT = 'https://api.deepseek.com/chat/completions';

    /**
     * Constructor for the deepSeekApi class.
     *
     * Initializes the deepSeekApi instance with the specified API key and model.
     *
     * @param string $apiKey The API key used for authentication.
     * @param string $model The model version to use, defaults to 'deepseek-chat'
     */
    public function __construct(protected string $apiKey, protected string $model = 'deepseek-chat')
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
     * Asks a question to the DeepSeek API.
     *
     * This method sends a request to the DeepSeek AI API with the provided prompt and options.
     * The response is then parsed and returned in an associative array.
     *
     * @param string $prompt The prompt to generate content based on.
     * @param array $options Additional options to pass to the DeepSeek API.
     * @return string The generated content from the DeepSeek AI API.
     * @throws \Exception If there is an error in the API response.
     */
    public function ask(string $prompt, array $options = []): string
    {
        $http = Http::withToken($this->apiKey)
            ->post(
                self::API_ENDPOINT,
                [
                    'model' => $this->model,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    ...$options
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
                $http->get('error.message', 'Unknown error occurred while communicating with DeepSeek API.')
            )
        );
    }
}