<?php
namespace Lumina;

class VectorStore
{
    private $chunks = [];
    private $index = [];
    private $idf = [];
    private $cacheFile;

    public function __construct($cacheFile = null)
    {
        $this->cacheFile = $cacheFile;
    }

    /**
     * Add chunks to vector store
     */
    public function addChunks(array $chunks)
    {
        $this->chunks = $chunks;
        $this->buildIndex();

        if ($this->cacheFile) {
            $this->saveCache();
        }
    }

    /**
     * Load from cache
     */
    public function loadCache()
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            $this->chunks = $data['chunks'];
            $this->index = $data['index'];
            $this->idf = $data['idf'];
            return true;
        }
        return false;
    }

    /**
     * Save to cache
     */
    private function saveCache()
    {
        $data = [
            'chunks' => $this->chunks,
            'index' => $this->index,
            'idf' => $this->idf,
            'created_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->cacheFile, json_encode($data));
    }

    /**
     * Build TF-IDF index
     */
    private function buildIndex()
    {
        $documentFrequency = [];
        $totalDocs = count($this->chunks);

        // Calculate document frequency
        foreach ($this->chunks as $chunkId => $chunk) {
            $terms = $this->tokenize($chunk['content']);
            $uniqueTerms = array_unique($terms);

            foreach ($uniqueTerms as $term) {
                if (!isset($documentFrequency[$term])) {
                    $documentFrequency[$term] = 0;
                }
                $documentFrequency[$term]++;
            }
        }

        // Calculate IDF
        foreach ($documentFrequency as $term => $df) {
            $this->idf[$term] = log($totalDocs / $df);
        }

        // Build TF-IDF vectors for each chunk
        foreach ($this->chunks as $chunkId => $chunk) {
            $terms = $this->tokenize($chunk['content']);
            $termFreq = array_count_values($terms);
            $vector = [];

            foreach ($termFreq as $term => $tf) {
                $idf = $this->idf[$term] ?? 0;
                $vector[$term] = $tf * $idf;
            }

            $this->index[$chunkId] = $vector;
        }
    }

    /**
     * Search for relevant chunks
     */
    public function search($query, $topK = 3)
    {
        $queryTerms = $this->tokenize($query);
        $queryVector = [];

        // Build query vector
        $termFreq = array_count_values($queryTerms);
        foreach ($termFreq as $term => $tf) {
            $idf = $this->idf[$term] ?? 0;
            $queryVector[$term] = $tf * $idf;
        }

        // Calculate cosine similarity with all chunks
        $scores = [];
        foreach ($this->index as $chunkId => $docVector) {
            $similarity = $this->cosineSimilarity($queryVector, $docVector);
            $scores[$chunkId] = $similarity;
        }

        // Sort by score
        arsort($scores);

        // Get top K results
        $results = [];
        $count = 0;
        foreach ($scores as $chunkId => $score) {
            if ($count >= $topK || $score <= 0)
                break;

            $results[] = [
                'chunk' => $this->chunks[$chunkId],
                'score' => $score
            ];
            $count++;
        }

        return $results;
    }

    /**
     * Tokenize text
     */
    private function tokenize($text)
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but', 'in', 'with', 'to', 'for', 'of', 'as', 'by', 'that', 'this', 'it', 'from', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had'];
        $words = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });

        return array_values($words);
    }

    /**
     * Calculate cosine similarity
     */
    private function cosineSimilarity($vec1, $vec2)
    {
        $dotProduct = 0;
        $mag1 = 0;
        $mag2 = 0;

        $allTerms = array_unique(array_merge(array_keys($vec1), array_keys($vec2)));

        foreach ($allTerms as $term) {
            $v1 = $vec1[$term] ?? 0;
            $v2 = $vec2[$term] ?? 0;

            $dotProduct += $v1 * $v2;
            $mag1 += $v1 * $v1;
            $mag2 += $v2 * $v2;
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 == 0 || $mag2 == 0)
            return 0;

        return $dotProduct / ($mag1 * $mag2);
    }

    /**
     * Get total chunks
     */
    public function getTotalChunks()
    {
        return count($this->chunks);
    }

    /**
     * Get all chunks
     */
    public function getChunks()
    {
        return $this->chunks;
    }
}
