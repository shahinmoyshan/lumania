<?php

namespace Lumina;

use Lumina\Contracts\AIContract;
use Lumina\Drivers\Vanilla;

class Lumina
{
    private DocumentLoader $documentLoader;
    private VectorStore $vectorStore;
    private AIContract $aiDriver;

    public function __construct(null|AIContract $driver = null, array $config = [])
    {
        $config = [
            'documents_path' => storage_dir('documents'),
            'vector_cache' => storage_dir('cache/vectors.json'),
            'chunk_size' => 500,
            'chunk_overlap' => 50,
            ...$config
        ];

        if ($driver !== null) {
            $this->aiDriver = $driver; // Use provided AI driver
        }

        $this->documentLoader = new DocumentLoader($config['documents_path'], $config['chunk_size'], $config['chunk_overlap']);
        $this->vectorStore = new VectorStore($config['vector_cache']);
    }

    /**
     * Initialize the system - load and index documents
     */
    public function initialize($forceReindex = false)
    {
        // Try to load from cache
        if (!$forceReindex && $this->vectorStore->loadCache()) {
            return;
        }

        $documents = $this->documentLoader->loadDocuments();
        $chunks = $this->documentLoader->chunkDocuments($documents);

        $this->vectorStore->addChunks($chunks);
    }

    /**
     * Load from string array instead of files
     */
    public function initializeFromStrings(array $strings)
    {
        $documents = $this->documentLoader->loadFromStrings($strings);
        $chunks = $this->documentLoader->chunkDocuments($documents);

        $this->vectorStore->addChunks($chunks);
    }

    /**
     * Ask a question
     */
    public function ask($question, $useCache = true)
    {
        $startTime = microtime(true);

        // If no AI driver is set, use Vanilla PHP
        if (!isset($this->aiDriver)) {
            $answer = $this->generateAnswerFromVanillaPhp($question);
            return [
                'answer' => $answer,
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        $results = $this->vectorStore->search($question, 3);
        $searchTime = (microtime(true) - $startTime) * 1000;

        if (empty($results)) {
            return [
                'answer' => "I couldn't find any relevant information to answer your question.",
                'response_time' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }

        // Build context from results
        $context = $this->buildContext($results);

        // Generate answer
        $genStart = microtime(true);
        $answer = $this->generateAnswerFromAI($question, $context);
        $genTime = (microtime(true) - $genStart) * 1000;

        // Prepare response
        $response = [
            'answer' => $answer,
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            'search_time' => round($searchTime, 2),
            'generation_time' => round($genTime, 2)
        ];

        return $response;
    }

    /**
     * Build context string from search results
     */
    private function buildContext($results)
    {
        $contextParts = [];

        foreach ($results as $index => $result) {
            $source = $result['chunk']['source'];
            $content = $result['chunk']['content'];
            $contextParts[] = "[Source: $source]\n$content\n";
        }

        return implode("\n---\n\n", $contextParts);
    }

    /**
     * Generate answer using AI model
     */
    private function generateAnswerFromAI($question, $context)
    {
        $prompt = "Context:\n$context\n\n" .
            "Question: $question\n\n" .
            "Answer based only on the context above:";

        try {
            return $this->aiDriver->ask($prompt);
        } catch (\Throwable $e) {
            return "Error generating answer: " . $e->getMessage();
        }
    }

    /**
     * Generate answer using Vanilla PHP driver
     */
    private function generateAnswerFromVanillaPhp($question)
    {
        try {
            $vanilla = new Vanilla();
            return $vanilla->ask($question, $this->vectorStore->getChunks());
        } catch (\Throwable $e) {
            return "Error generating answer: " . $e->getMessage();
        }
    }
}
