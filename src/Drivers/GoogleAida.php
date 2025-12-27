<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIContract;
use Spark\Facades\Http;

class GoogleAida implements AIContract
{
    private const API_ENDPOINT = 'https://aida.googleapis.com/v1/aida:doConversation';

    public function __construct(protected string $token)
    {
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    public function ask(string $prompt, array $options = []): string
    {
        $http = Http::withToken($this->token)
            ->post(
                self::API_ENDPOINT,
                [
                    'client' => 'CHROME_DEVTOOLS',
                    'current_message' => [
                        'parts' => [['text' => 'Tell me about this <h1>Hello World</h1> HTML heading tags']],
                        'role' => 1,
                    ],
                    'functionality_type' => 5,
                    'client_feature' => 2
                ]
            );

        dd($http);
    }
}