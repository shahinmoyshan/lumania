<?php
namespace Lumina;

use Exception;

class DocumentLoader
{
    private $documentsPath;
    private $chunkSize;
    private $chunkOverlap;

    public function __construct($documentsPath, $chunkSize = 500, $chunkOverlap = 50)
    {
        $this->documentsPath = rtrim($documentsPath, '/');
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
    }

    /**
     * Load all documents from folder
     */
    public function loadDocuments()
    {
        $documents = [];

        if (!is_dir($this->documentsPath)) {
            throw new Exception("Documents path does not exist: {$this->documentsPath}");
        }

        $files = glob($this->documentsPath . '/*.txt');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            $documents[] = [
                'filename' => $filename,
                'content' => $content,
                'size' => strlen($content)
            ];
        }

        return $documents;
    }

    /**
     * Load from string array
     */
    public function loadFromStrings(array $strings)
    {
        $documents = [];

        foreach ($strings as $source => $content) {
            $documents[] = [
                'filename' => $source,
                'content' => $content,
                'size' => strlen($content)
            ];
        }

        return $documents;
    }

    /**
     * Split documents into chunks for better retrieval
     */
    public function chunkDocuments(array $documents)
    {
        $chunks = [];

        foreach ($documents as $doc) {
            $content = $doc['content'];
            $sentences = $this->splitIntoSentences($content);

            $currentChunk = '';
            $chunkIndex = 0;

            foreach ($sentences as $sentence) {
                $testChunk = $currentChunk . ' ' . $sentence;

                if (strlen($testChunk) > $this->chunkSize && !empty($currentChunk)) {
                    // Save current chunk
                    $chunks[] = [
                        'id' => md5($doc['filename'] . $chunkIndex),
                        'source' => $doc['filename'],
                        'content' => trim($currentChunk),
                        'chunk_index' => $chunkIndex
                    ];

                    // Start new chunk with overlap
                    $words = explode(' ', $currentChunk);
                    $overlapWords = array_slice($words, -($this->chunkOverlap / 10));
                    $currentChunk = implode(' ', $overlapWords) . ' ' . $sentence;
                    $chunkIndex++;
                } else {
                    $currentChunk = $testChunk;
                }
            }

            // Add remaining chunk
            if (!empty(trim($currentChunk))) {
                $chunks[] = [
                    'id' => md5($doc['filename'] . $chunkIndex),
                    'source' => $doc['filename'],
                    'content' => trim($currentChunk),
                    'chunk_index' => $chunkIndex
                ];
            }
        }

        return $chunks;
    }

    /**
     * Split text into sentences
     */
    private function splitIntoSentences($text)
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $sentences;
    }
}
