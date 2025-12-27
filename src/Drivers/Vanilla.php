<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIContract;

class Vanilla implements AIContract
{
    private array $chunks;
    private array $stopWords;
    private array $questionWords;

    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;

        // Common stop words to filter out
        $this->stopWords = [
            'the',
            'is',
            'at',
            'which',
            'on',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'with',
            'to',
            'for',
            'of',
            'as',
            'by',
            'that',
            'this',
            'it',
            'from',
            'are',
            'was',
            'were',
            'be',
            'been',
            'have',
            'has',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'must',
            'can',
            'about',
            'into',
            'through',
            'during',
            'before',
            'after',
            'above',
            'below',
            'up',
            'down',
            'out',
            'off',
            'over',
            'under',
            'again',
            'further',
            'then',
            'once'
        ];

        // Question word patterns
        $this->questionWords = [
            'what',
            'where',
            'when',
            'who',
            'why',
            'how',
            'which',
            'does',
            'do',
            'is',
            'are',
            'can',
            'tell',
            'describe',
            'explain',
            'list',
            'show',
            'find',
            'get'
        ];
    }

    /**
     * Generate AI-like response to question
     */
    public function ask(string $question, array $context = []): string
    {
        // Merge provided context with chunks
        $allChunks = $this->flattenChunks($context);

        if (empty($allChunks)) {
            return $this->generateNoInfoResponse($question);
        }

        // Detect question type for appropriate response style
        $questionType = $this->detectQuestionType($question);

        // Score and rank all chunks by relevance
        $rankedChunks = $this->rankChunks($question, $allChunks);

        if (empty($rankedChunks) || $rankedChunks[0]['score'] < 0.1) {
            return $this->generateNoInfoResponse($question);
        }

        // Extract and synthesize answer from top chunks
        $topChunks = array_slice($rankedChunks, 0, 3);

        // Generate natural response based on question type
        return $this->generateNaturalResponse($question, $questionType, $topChunks);
    }

    /**
     * Flatten nested chunk arrays
     */
    private function flattenChunks(array $context): array
    {
        $flattened = [];

        // Handle the chunks from constructor
        if (!empty($this->chunks)) {
            // Chunks might be nested in array(1) { [0] => array(...) }
            $chunks = is_array($this->chunks[0] ?? null) && isset($this->chunks[0][0])
                ? $this->chunks[0]
                : $this->chunks;

            foreach ($chunks as $chunk) {
                if (isset($chunk['content'])) {
                    $flattened[] = $chunk;
                }
            }
        }

        // Merge with provided context
        foreach ($context as $item) {
            if (is_array($item)) {
                if (isset($item['content'])) {
                    $flattened[] = $item;
                } else {
                    // Recursively flatten
                    foreach ($item as $subItem) {
                        if (isset($subItem['content'])) {
                            $flattened[] = $subItem;
                        }
                    }
                }
            }
        }

        return $flattened;
    }

    /**
     * Detect question type to tailor response style
     */
    private function detectQuestionType(string $question): string
    {
        $question = strtolower($question);

        if (preg_match('/^(what|which)\s+(is|are|was|were)/', $question)) {
            return 'definition';
        }
        if (preg_match('/^where/', $question)) {
            return 'location';
        }
        if (preg_match('/^when/', $question)) {
            return 'time';
        }
        if (preg_match('/^who/', $question)) {
            return 'person';
        }
        if (preg_match('/^how\s+(much|many)/', $question)) {
            return 'quantity';
        }
        if (preg_match('/^(how|can\s+you|tell\s+me|explain)/', $question)) {
            return 'explanation';
        }
        if (preg_match('/^(list|show|give|provide)/', $question)) {
            return 'list';
        }
        if (preg_match('/(price|cost|pricing|fee|charge)/', $question)) {
            return 'pricing';
        }
        if (preg_match('/(contact|email|phone|reach|address)/', $question)) {
            return 'contact';
        }

        return 'general';
    }

    /**
     * Rank chunks by relevance to question
     */
    private function rankChunks(string $question, array $chunks): array
    {
        $ranked = [];

        foreach ($chunks as $chunk) {
            $score = $this->calculateRelevanceScore($question, $chunk['content']);

            if ($score > 0) {
                $ranked[] = [
                    'chunk' => $chunk,
                    'score' => $score,
                    'sentences' => $this->extractSentences($chunk['content'])
                ];
            }
        }

        // Sort by score descending
        usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }

    /**
     * Calculate semantic relevance score using multiple factors
     */
    private function calculateRelevanceScore(string $question, string $content): float
    {
        $questionTokens = $this->tokenize($question);
        $contentTokens = $this->tokenize($content);

        if (empty($questionTokens) || empty($contentTokens)) {
            return 0.0;
        }

        // Factor 1: Keyword overlap (TF-IDF-like)
        $keywordScore = $this->calculateKeywordOverlap($questionTokens, $contentTokens);

        // Factor 2: Bigram matching (phrase matching)
        $bigramScore = $this->calculateBigramMatch($question, $content);

        // Factor 3: Semantic similarity (word proximity)
        $semanticScore = $this->calculateSemanticSimilarity($questionTokens, $contentTokens);

        // Factor 4: Question focus word boost
        $focusScore = $this->calculateFocusWordBoost($question, $content);

        // Weighted combination
        return (
            $keywordScore * 0.35 +
            $bigramScore * 0.25 +
            $semanticScore * 0.25 +
            $focusScore * 0.15
        );
    }

    /**
     * Calculate keyword overlap score
     */
    private function calculateKeywordOverlap(array $questionTokens, array $contentTokens): float
    {
        $intersection = array_intersect($questionTokens, $contentTokens);

        if (empty($questionTokens)) {
            return 0.0;
        }

        $recall = count($intersection) / count($questionTokens);
        $precision = count($intersection) / max(count($contentTokens), 1);

        // F1 score
        if ($recall + $precision == 0) {
            return 0.0;
        }

        return 2 * ($precision * $recall) / ($precision + $recall);
    }

    /**
     * Calculate bigram (2-word phrase) matches
     */
    private function calculateBigramMatch(string $question, string $content): float
    {
        $questionBigrams = $this->extractBigrams($question);
        $contentBigrams = $this->extractBigrams($content);

        if (empty($questionBigrams)) {
            return 0.0;
        }

        $matches = count(array_intersect($questionBigrams, $contentBigrams));
        return $matches / count($questionBigrams);
    }

    /**
     * Calculate semantic similarity using word embeddings simulation
     */
    private function calculateSemanticSimilarity(array $questionTokens, array $contentTokens): float
    {
        // Simulate semantic similarity by checking for synonyms and related terms
        $semanticMatches = 0;
        $synonymMap = $this->getSynonymMap();

        foreach ($questionTokens as $qToken) {
            foreach ($contentTokens as $cToken) {
                if ($qToken === $cToken) {
                    $semanticMatches += 1.0;
                } elseif (isset($synonymMap[$qToken]) && in_array($cToken, $synonymMap[$qToken])) {
                    $semanticMatches += 0.7;
                } elseif ($this->areSimilarWords($qToken, $cToken)) {
                    $semanticMatches += 0.5;
                }
            }
        }

        return $semanticMatches / max(count($questionTokens) * count($contentTokens), 1);
    }

    /**
     * Boost score for focus words (key entities in question)
     */
    private function calculateFocusWordBoost(string $question, string $content): float
    {
        // Extract likely focus words (capitalized, numbers, special terms)
        preg_match_all('/\b([A-Z][a-z]+|\d+|[a-z]{8,})\b/', $question, $focusWords);

        if (empty($focusWords[0])) {
            return 0.0;
        }

        $boostScore = 0.0;
        foreach ($focusWords[0] as $focus) {
            if (stripos($content, $focus) !== false) {
                $boostScore += 1.0;
            }
        }

        return min($boostScore / count($focusWords[0]), 1.0);
    }

    /**
     * Extract bigrams from text
     */
    private function extractBigrams(string $text): array
    {
        $tokens = $this->tokenize($text, false);
        $bigrams = [];

        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $bigrams[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }

        return $bigrams;
    }

    /**
     * Check if two words are similar (Levenshtein distance)
     */
    private function areSimilarWords(string $word1, string $word2): bool
    {
        if (strlen($word1) < 4 || strlen($word2) < 4) {
            return false;
        }

        $distance = levenshtein($word1, $word2);
        $maxLen = max(strlen($word1), strlen($word2));

        return ($distance / $maxLen) < 0.3; // 30% difference threshold
    }

    /**
     * Simple synonym map for common words
     */
    private function getSynonymMap(): array
    {
        return [
            'service' => ['services', 'offering', 'product', 'solution'],
            'price' => ['pricing', 'cost', 'fee', 'charge', 'rate'],
            'contact' => ['reach', 'email', 'phone', 'call'],
            'company' => ['organization', 'business', 'firm', 'corporation'],
            'offer' => ['provide', 'give', 'deliver', 'supply'],
            'location' => ['office', 'address', 'place', 'headquarter'],
            'team' => ['staff', 'employee', 'member', 'people'],
            'project' => ['work', 'case', 'assignment', 'job'],
        ];
    }

    /**
     * Generate natural AI-like response
     */
    private function generateNaturalResponse(string $question, string $type, array $topChunks): string
    {
        // Extract most relevant information
        $info = $this->extractRelevantInfo($question, $topChunks);

        if (empty($info['sentences'])) {
            return $this->generateNoInfoResponse($question);
        }

        // Generate opening based on question type and confidence
        $opening = $this->generateOpening($type, $info['confidence']);

        // Synthesize main answer
        $mainAnswer = $this->synthesizeAnswer($info['sentences'], $type);

        // Add context if available
        $context = $this->addContext($info['sentences'], $type);

        // Combine into natural response
        $response = $opening . ' ' . $mainAnswer;

        if (!empty($context)) {
            $response .= ' ' . $context;
        }

        return trim($response);
    }

    /**
     * Extract most relevant information from chunks
     */
    private function extractRelevantInfo(string $question, array $chunks): array
    {
        $relevantSentences = [];
        $sources = [];
        $totalScore = 0;

        foreach ($chunks as $chunkData) {
            $sentences = $chunkData['sentences'];
            $chunkScore = $chunkData['score'];

            foreach ($sentences as $sentence) {
                $sentenceScore = $this->scoreSentence($sentence, $question);

                if ($sentenceScore > 0) {
                    $relevantSentences[] = [
                        'text' => $sentence,
                        'score' => $sentenceScore * $chunkScore,
                        'source' => $chunkData['chunk']['source'] ?? 'unknown'
                    ];
                    $totalScore += $sentenceScore;
                }
            }

            $sources[] = $chunkData['chunk']['source'] ?? 'unknown';
        }

        // Sort sentences by score
        usort($relevantSentences, fn($a, $b) => $b['score'] <=> $a['score']);

        // Calculate confidence based on top sentence scores
        $confidence = empty($relevantSentences) ? 0 : min($relevantSentences[0]['score'] * 100, 95);

        return [
            'sentences' => $relevantSentences,
            'sources' => array_unique($sources),
            'confidence' => $confidence
        ];
    }

    /**
     * Score individual sentence relevance
     */
    private function scoreSentence(string $sentence, string $question): float
    {
        $sentence = trim($sentence);

        if (empty($sentence) || strlen($sentence) < 10) {
            return 0.0;
        }

        $questionTokens = $this->tokenize($question);
        $sentenceTokens = $this->tokenize($sentence);

        $matches = count(array_intersect($questionTokens, $sentenceTokens));
        $coverage = $matches / max(count($questionTokens), 1);

        // Boost sentences with numbers, entities, specific info
        $boost = 1.0;
        if (preg_match('/\d+/', $sentence)) {
            $boost += 0.2;
        }
        if (preg_match('/[A-Z][a-z]+\s+[A-Z][a-z]+/', $sentence)) {
            $boost += 0.15;
        }
        if (preg_match('/@|\.com|http/', $sentence)) {
            $boost += 0.25;
        }

        return $coverage * $boost;
    }

    /**
     * Generate contextual opening
     */
    private function generateOpening(string $type, float $confidence): string
    {
        $openings = [
            'definition' => [
                'high' => ['', 'Let me explain:', 'Here\'s what I found:'],
                'medium' => ['Based on the available information,', 'From what I can see,'],
                'low' => ['I found some information that might help:', 'Here\'s what the documents indicate:']
            ],
            'location' => [
                'high' => ['', 'The location is', 'You can find us at'],
                'medium' => ['Based on our records,', 'According to the documents,'],
                'low' => ['I found location information:', 'The documents mention:']
            ],
            'pricing' => [
                'high' => ['', 'Here\'s the pricing information:', 'The pricing is as follows:'],
                'medium' => ['Based on our pricing structure,', 'According to our packages,'],
                'low' => ['I found some pricing details:', 'The documents indicate:']
            ],
            'contact' => [
                'high' => ['You can reach us through the following:', 'Here are the contact details:', ''],
                'medium' => ['Based on our contact information,', 'According to the records,'],
                'low' => ['I found contact information:', 'The documents show:']
            ],
            'list' => [
                'high' => ['Here\'s what we offer:', 'Here are the items:', ''],
                'medium' => ['Based on the documents,', 'From what I can see,'],
                'low' => ['I found a list of:', 'The information includes:']
            ],
            'general' => [
                'high' => ['', 'Here\'s what I found:', 'Based on our information,'],
                'medium' => ['According to the documents,', 'From the available information,'],
                'low' => ['I found some relevant information:', 'The documents suggest:']
            ]
        ];

        $confidenceLevel = $confidence > 70 ? 'high' : ($confidence > 40 ? 'medium' : 'low');
        $options = $openings[$type] ?? $openings['general'];

        return $options[$confidenceLevel][array_rand($options[$confidenceLevel])];
    }

    /**
     * Synthesize coherent answer from sentences
     */
    private function synthesizeAnswer(array $sentences, string $type): string
    {
        if (empty($sentences)) {
            return "I don't have specific information about that.";
        }

        // For lists and multi-part answers
        if ($type === 'list' || count($sentences) > 3) {
            return $this->generateListAnswer($sentences);
        }

        // For specific questions (contact, pricing, etc.)
        if (in_array($type, ['contact', 'pricing', 'location', 'quantity'])) {
            return $this->generateSpecificAnswer($sentences, $type);
        }

        // For general/explanation questions
        return $this->generateParagraphAnswer($sentences);
    }

    /**
     * Generate list-style answer
     */
    private function generateListAnswer(array $sentences): string
    {
        $items = [];
        $seen = [];

        foreach (array_slice($sentences, 0, 5) as $sentenceData) {
            $text = trim($sentenceData['text']);

            // Skip duplicates
            if (in_array($text, $seen)) {
                continue;
            }
            $seen[] = $text;

            // Extract list items or use full sentence
            if (preg_match('/^[\d\-•]\s*(.+)/', $text, $matches)) {
                $items[] = trim($matches[1]);
            } elseif (strlen($text) > 20 && strlen($text) < 200) {
                $items[] = $text;
            }
        }

        if (count($items) <= 1) {
            return $items[0] ?? $sentences[0]['text'];
        }

        // Format as natural list
        $answer = implode(', ', array_slice($items, 0, -1));
        $answer .= ', and ' . end($items);

        return $answer . '.';
    }

    /**
     * Generate specific answer (contact, pricing, etc.)
     */
    private function generateSpecificAnswer(array $sentences, string $type): string
    {
        $extracted = [];

        foreach (array_slice($sentences, 0, 3) as $sentenceData) {
            $text = $sentenceData['text'];

            // Extract specific patterns based on type
            if ($type === 'contact') {
                if (preg_match('/(email|phone|address|contact)[:=]?\s*([^\n,]+)/i', $text, $match)) {
                    $extracted[] = trim($match[2]);
                }
            } elseif ($type === 'pricing') {
                if (preg_match('/\$[\d,]+ ?-? ?[\d,]*|\$[\d,]+/i', $text, $match)) {
                    $extracted[] = trim($match[0]);
                }
            }
        }

        // If extraction worked, format nicely
        if (!empty($extracted)) {
            return implode(', ', array_unique($extracted));
        }

        // Otherwise return best sentence
        return $this->cleanSentence($sentences[0]['text']);
    }

    /**
     * Generate paragraph-style answer
     */
    private function generateParagraphAnswer(array $sentences): string
    {
        $topSentences = array_slice($sentences, 0, 2);
        $texts = array_map(fn($s) => $this->cleanSentence($s['text']), $topSentences);

        // Combine sentences naturally
        if (count($texts) === 1) {
            return $texts[0];
        }

        // Connect sentences if they're related
        $combined = $texts[0];
        if (!empty($texts[1]) && strlen($texts[1]) > 20) {
            $combined .= ' Additionally, ' . lcfirst($texts[1]);
        }

        return $combined;
    }

    /**
     * Add contextual information
     */
    private function addContext(array $sentences, string $type): string
    {
        // Only add context for general questions
        if (!in_array($type, ['general', 'explanation'])) {
            return '';
        }

        // Look for additional relevant details
        $contextSentences = array_slice($sentences, 2, 2);

        if (empty($contextSentences)) {
            return '';
        }

        foreach ($contextSentences as $sentenceData) {
            $text = $this->cleanSentence($sentenceData['text']);
            if (strlen($text) > 30 && strlen($text) < 150) {
                return $text;
            }
        }

        return '';
    }

    /**
     * Clean and normalize sentence
     */
    private function cleanSentence(string $sentence): string
    {
        $sentence = trim($sentence);
        $sentence = preg_replace('/\s+/', ' ', $sentence);
        $sentence = preg_replace('/^[\d\-•]\s*/', '', $sentence);

        // Ensure proper ending
        if (!preg_match('/[.!?]$/', $sentence)) {
            $sentence .= '.';
        }

        return $sentence;
    }

    /**
     * Extract sentences from text
     */
    private function extractSentences(string $text): array
    {
        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_filter(array_map('trim', $sentences), function ($s) {
            return strlen($s) > 10; // Filter out very short sentences
        });
    }

    /**
     * Tokenize text into meaningful words
     */
    private function tokenize(string $text, bool $removeStopWords = true): array
    {
        // Convert to lowercase and extract words
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words if requested
        if ($removeStopWords) {
            $words = array_filter($words, function ($word) {
                return strlen($word) > 2 && !in_array($word, $this->stopWords);
            });
        }

        return array_values($words);
    }

    /**
     * Generate response when no information is found
     */
    private function generateNoInfoResponse(string $question): string
    {
        $responses = [
            "I don't have specific information about that in the available documents.",
            "I couldn't find that information in the company documents.",
            "That information isn't available in the documents I have access to.",
            "I don't have enough information to answer that question accurately.",
        ];

        return $responses[array_rand($responses)];
    }
}