<?php
namespace Lumina;

class VectorStore
{
    private $chunks = [];
    private $chunkMap = []; // Map chunk IDs to array indices
    private $index = [];
    private $idf = [];
    private $cacheFile;
    private $tokenCache = []; // Cache for tokenization results

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
        $this->buildChunkMap();
        $this->buildIndex();

        if ($this->cacheFile) {
            $this->saveCache();
        }
    }

    /**
     * Build mapping from chunk ID to array index
     */
    private function buildChunkMap()
    {
        $this->chunkMap = [];
        foreach ($this->chunks as $index => $chunk) {
            $chunkId = $chunk['id'] ?? $index;
            $this->chunkMap[$chunkId] = $index;
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
            $this->buildChunkMap();
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
     * Build TF-IDF index with normalized TF and smoothing
     */
    private function buildIndex()
    {
        $documentFrequency = [];
        $totalDocs = count($this->chunks);

        if ($totalDocs == 0) {
            return;
        }

        // Calculate document frequency for all terms AND n-grams
        foreach ($this->chunks as $index => $chunk) {
            $terms = $this->tokenize($chunk['content']);
            $uniqueTerms = array_unique($terms);

            // Count single terms
            foreach ($uniqueTerms as $term) {
                if (!isset($documentFrequency[$term])) {
                    $documentFrequency[$term] = 0;
                }
                $documentFrequency[$term]++;
            }

            // Count bigrams and trigrams for IDF calculation
            $bigrams = array_unique($this->extractNgrams($terms, 2));
            $trigrams = array_unique($this->extractNgrams($terms, 3));

            foreach ($bigrams as $bigram) {
                if (!isset($documentFrequency[$bigram])) {
                    $documentFrequency[$bigram] = 0;
                }
                $documentFrequency[$bigram]++;
            }

            foreach ($trigrams as $trigram) {
                if (!isset($documentFrequency[$trigram])) {
                    $documentFrequency[$trigram] = 0;
                }
                $documentFrequency[$trigram]++;
            }
        }

        // Calculate IDF with smoothing (add 1 to prevent division by zero)
        foreach ($documentFrequency as $term => $df) {
            // IDF = log((total_docs + 1) / (df + 1)) - smoothing prevents log(0)
            $this->idf[$term] = log(($totalDocs + 1) / ($df + 1)) + 1;
        }

        // Build normalized TF-IDF vectors for each chunk
        foreach ($this->chunks as $index => $chunk) {
            $terms = $this->tokenize($chunk['content']);
            $termFreq = array_count_values($terms);
            $totalTerms = count($terms);

            if ($totalTerms == 0) {
                $this->index[$index] = [];
                continue;
            }

            $vector = [];

            // Calculate normalized TF-IDF
            foreach ($termFreq as $term => $tf) {
                // Normalized TF: log(1 + tf) / log(1 + total_terms)
                // This prevents bias towards longer documents
                $normalizedTf = log(1 + $tf) / log(1 + $totalTerms);
                $idf = $this->idf[$term] ?? 0;
                $vector[$term] = $normalizedTf * $idf;
            }

            // Add n-grams for better semantic matching
            $bigrams = $this->extractNgrams($terms, 2);
            $trigrams = $this->extractNgrams($terms, 3);

            foreach ($bigrams as $bigram) {
                if (!isset($vector[$bigram])) {
                    $vector[$bigram] = 0;
                }
                $vector[$bigram] += 0.3 * ($this->idf[$bigram] ?? 0); // Lower weight for n-grams
            }

            foreach ($trigrams as $trigram) {
                if (!isset($vector[$trigram])) {
                    $vector[$trigram] = 0;
                }
                $vector[$trigram] += 0.2 * ($this->idf[$trigram] ?? 0);
            }

            // Normalize vector for cosine similarity
            $this->index[$index] = $this->normalizeVector($vector);
        }
    }

    /**
     * Extract n-grams from token array
     */
    private function extractNgrams(array $tokens, $n)
    {
        $ngrams = [];
        $count = count($tokens);

        for ($i = 0; $i <= $count - $n; $i++) {
            $ngram = implode('_', array_slice($tokens, $i, $n));
            $ngrams[] = $ngram;
        }

        return $ngrams;
    }

    /**
     * Normalize vector to unit length
     */
    private function normalizeVector(array $vector)
    {
        $magnitude = 0;
        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }

        if ($magnitude == 0) {
            return $vector;
        }

        $magnitude = sqrt($magnitude);
        $normalized = [];
        foreach ($vector as $term => $value) {
            $normalized[$term] = $value / $magnitude;
        }

        return $normalized;
    }

    /**
     * Search for relevant chunks with optimizations
     */
    public function search($query, $topK = 3)
    {
        if (empty(trim($query))) {
            return [];
        }

        $queryTerms = $this->tokenize($query);

        if (empty($queryTerms)) {
            return [];
        }

        $queryVector = [];
        $termFreq = array_count_values($queryTerms);
        $totalTerms = count($queryTerms);

        // Build normalized query vector
        foreach ($termFreq as $term => $tf) {
            $normalizedTf = log(1 + $tf) / log(1 + $totalTerms);
            $idf = $this->idf[$term] ?? 0;
            $queryVector[$term] = $normalizedTf * $idf;
        }

        // Add n-grams to query
        $bigrams = $this->extractNgrams($queryTerms, 2);
        $trigrams = $this->extractNgrams($queryTerms, 3);

        foreach ($bigrams as $bigram) {
            if (!isset($queryVector[$bigram])) {
                $queryVector[$bigram] = 0;
            }
            $queryVector[$bigram] += 0.3 * ($this->idf[$bigram] ?? 0);
        }

        foreach ($trigrams as $trigram) {
            if (!isset($queryVector[$trigram])) {
                $queryVector[$trigram] = 0;
            }
            $queryVector[$trigram] += 0.2 * ($this->idf[$trigram] ?? 0);
        }

        // Normalize query vector
        $queryVector = $this->normalizeVector($queryVector);

        // Calculate cosine similarity with all chunks
        $scores = [];
        foreach ($this->index as $chunkIndex => $docVector) {
            $similarity = $this->cosineSimilarity($queryVector, $docVector);

            // Early termination: skip zero similarity chunks
            if ($similarity > 0) {
                $scores[$chunkIndex] = $similarity;
            }
        }

        // Sort by score (descending)
        arsort($scores);

        // Get top K results
        $results = [];
        $count = 0;
        foreach ($scores as $chunkIndex => $score) {
            if ($count >= $topK) {
                break;
            }

            if (isset($this->chunks[$chunkIndex])) {
                $results[] = [
                    'chunk' => $this->chunks[$chunkIndex],
                    'score' => $score
                ];
                $count++;
            }
        }

        return $results;
    }

    /**
     * Enhanced tokenization with special case handling
     */
    private function tokenize($text)
    {
        // Check cache first
        $cacheKey = md5($text);
        if (isset($this->tokenCache[$cacheKey])) {
            return $this->tokenCache[$cacheKey];
        }

        $text = strtolower(trim($text));

        if (empty($text)) {
            return [];
        }

        // Extract searchable parts from emails, URLs, phone numbers
        // Instead of preserving as one token, extract meaningful parts
        $extractedTokens = [];

        // Extract email parts: contact@techcorp.com -> contact, techcorp, com
        $text = preg_replace_callback(
            '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+)\.([a-zA-Z]{2,})/',
            function ($matches) use (&$extractedTokens) {
                // Add individual parts as tokens
                $extractedTokens[] = strtolower($matches[1]); // username part
                $domainParts = explode('.', $matches[2]);
                foreach ($domainParts as $part) {
                    if (strlen($part) > 1) {
                        $extractedTokens[] = strtolower($part);
                    }
                }
                return ' ' . implode(' ', $extractedTokens) . ' ';
            },
            $text
        );

        // Extract URL parts: https://github.com/techcorp -> github, techcorp
        $text = preg_replace_callback(
            '/https?:\/\/([^\s\/]+)(\/[^\s]*)?/',
            function ($matches) use (&$extractedTokens) {
                $domain = $matches[1];
                $path = $matches[2] ?? '';
                $parts = preg_split('/[.\/\-_]+/', $domain . $path, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parts as $part) {
                    if (strlen($part) > 1 && !in_array($part, ['www', 'http', 'https', 'com', 'org', 'net'])) {
                        $extractedTokens[] = strtolower($part);
                    }
                }
                return ' ' . implode(' ', $parts) . ' ';
            },
            $text
        );

        // Normalize phone numbers - just remove them, not useful for search
        $text = preg_replace('/\+?\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}/', ' ', $text);

        // Handle contractions (but not possessives - 's is often possessive, not "is")
        $contractions = [
            "n't" => ' not',
            "'re" => ' are',
            "'ve" => ' have',
            "'ll" => ' will',
            "'d" => ' would',
            "'m" => ' am',
            // Note: 's removed - too ambiguous (possessive vs contraction)
        ];
        foreach ($contractions as $contraction => $expansion) {
            $text = str_replace($contraction, $expansion, $text);
        }
        // Remove remaining apostrophes (possessives)
        $text = str_replace("'s", '', $text);
        $text = str_replace("'", '', $text);

        // Remove punctuation but preserve alphanumeric, spaces, and underscores
        $text = preg_replace('/[^a-z0-9\s_]/', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words and filter
        $stopWords = require __DIR__ . '/resources/inc/stopwords.php';

        // Important short terms to always keep (tech terms, abbreviations)
        $keepShortTerms = ['ai', 'ml', 'ui', 'ux', 'go', 'js', 'db', 'os', 'it', 'qa', 'hr', 'pr', 'pm', 'vp', 'ceo', 'cto', 'cfo', 'api', 'aws', 'gcp', 'ios', 'sql', 'css', 'php', 'vue', 'mvp'];

        $words = array_filter($words, function ($word) use ($stopWords, $keepShortTerms) {
            $word = trim($word);
            $len = strlen($word);

            // Always keep important short terms
            if (in_array($word, $keepShortTerms)) {
                return true;
            }

            // Keep years (1900-2099) - important temporal context
            if (preg_match('/^(19|20)\d{2}$/', $word)) {
                return true;
            }

            // Keep words: 2+ chars (not just 1), not in stopwords
            // Filter pure numbers except years (handled above)
            return $len >= 2 &&
                !in_array($word, $stopWords) &&
                !preg_match('/^\d+$/', $word);
        });

        $result = array_values($words);

        // Cache result
        $this->tokenCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity($vec1, $vec2)
    {
        if (empty($vec1) || empty($vec2)) {
            return 0;
        }

        $dotProduct = 0;
        $allTerms = array_unique(array_merge(array_keys($vec1), array_keys($vec2)));

        foreach ($allTerms as $term) {
            $v1 = $vec1[$term] ?? 0;
            $v2 = $vec2[$term] ?? 0;
            $dotProduct += $v1 * $v2;
        }

        // Since vectors are normalized, magnitude is 1, so cosine similarity = dot product
        return $dotProduct;
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
