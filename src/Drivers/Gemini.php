<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIContract;
use Spark\Facades\Http;
use function sprintf;

class Gemini implements AIContract
{
    /** @param string The API endpoint for the Gemini AI Content Generation API. */
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Constructor for the geminiApi class.
     *
     * Initializes the geminiApi instance with the specified API key and model.
     *
     * @param string $apiKey The API key used for authentication.
     * @param string $model The model version to use, defaults to 'gemini-2.5-flash-lite'
     */
    public function __construct(protected string $apiKey, protected string $model = 'gemini-2.5-flash-lite')
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
     * Asks a question to the Gemini AI API.
     *
     * This method sends a request to the Gemini AI API with the provided prompt and options.
     * The response is then parsed and returned in an associative array.
     *
     * If the response is successful, the returned array will contain the following keys:
     *   - `error`: A boolean indicating whether there was an error.
     *   - `content`: The generated content.
     *
     * If there is an error, the returned array will contain the following keys:
     *   - `error`: A boolean indicating whether there was an error.
     *   - `message`: The error message.
     *
     * @param string $prompt The prompt to generate content based on.
     * @param array $options Additional options to pass to the Gemini API.
     * @return string The generated content from the Gemini AI API.
     * @throws \Exception If there is an error in the API response.
     */
    public function ask(string $prompt, array $options = []): string
    {
        $http = Http::post(
            sprintf('%s%s:generateContent?key=%s', self::API_ENDPOINT, $this->model, $this->apiKey),
            [
                'contents' => ['parts' => ['text' => $prompt]],
                ...$options
            ]
        );

        if ($http->failed()) {
            throw new \Exception(
                sprintf('HTTP Error: %s, message: %s', $http->status(), $http->get('error.message', 'Unknown error occurred while communicating with Gemini API.'))
            );
        }

        $response = $http->json();
        if (isset($response['candidates'], $response['candidates'][0])) {
            $parts = $response['candidates'][0]['content']['parts'] ?? [];
            if (!empty($parts)) {
                return implode("\n", array_column($parts, 'text'));
            }
        }

        throw new \Exception($response['error']['message'] ?? '');
    }
}