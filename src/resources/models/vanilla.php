<?php

use Lumina\Contracts\VanillaModelContract;

return new class extends VanillaModelContract {
    /** @var array $context The knowledge context used by the model */
    private array $context;

    // Scoring weights
    private const KEYWORD_WEIGHT = 0.38;
    private const BIGRAM_WEIGHT = 0.18;
    private const TRIGRAM_WEIGHT = 0.14;
    private const SEMANTIC_WEIGHT = 0.18;
    private const ENTITY_WEIGHT = 0.08;
    private const POSITION_WEIGHT = 0.04;

    // Response configuration
    private const MIN_CONFIDENCE_THRESHOLD = 0.10;
    private const HIGH_CONFIDENCE = 75;
    private const MEDIUM_CONFIDENCE = 45;
    private const MAX_SENTENCE_LENGTH = 250;
    private const MIN_SENTENCE_LENGTH = 15;

    public function __construct()
    {
        // initialize this model
        parent::setup([
            'stopWords' => include __DIR__ . '/../inc/stopwords.php',
            'questionWords' => [
                'what',
                'where',
                'when',
                'who',
                'why',
                'how',
                'which',
                'whose',
                'does',
                'do',
                'is',
                'are',
                'can',
                'could',
                'would',
                'should',
                'tell',
                'describe',
                'explain',
                'list',
                'show',
                'find',
                'get',
                'give',
                'provide',
                'define',
                'compare',
                'analyze',
                'identify'
            ],
            'entityPatterns' => [
                'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
                'phone' => '/\b(?:\+?1[-.\s]?)?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})\b/',
                'url' => '/\b(?:https?:\/\/)?(?:www\.)?[a-zA-Z0-9-]+\.[a-zA-Z]{2,}(?:\/[^\s]*)?\b/',
                'price' => '/\$\s*\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d{1,3}(?:,\d{3})*(?:\.\d{2})?\s*(?:dollars?|USD)/i',
                'date' => '/\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b|\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}\b/i',
                'percentage' => '/\b\d+(?:\.\d+)?%/',
                'number' => '/\b\d+(?:,\d{3})*(?:\.\d+)?\b/',
                'organization' => '/\b(?:[A-Z][a-z]+\s+){0,2}(?:Inc|LLC|Corp|Ltd|Company|Corporation|Group)\b/',
                'proper_noun' => '/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/',
            ],
            'responseTemplates' => [
                'definition' => [
                    'high' => [
                        '%s is %s',
                        '%s refers to %s',
                        'In essence, %s represents %s',
                        '%s can be defined as %s',
                    ],
                    'medium' => [
                        'Based on the available information, %s appears to be %s',
                        'From what I understand, %s is %s',
                        'The documentation suggests that %s is %s',
                    ],
                    'low' => [
                        'While I have limited information, it seems %s relates to %s',
                        'From the fragments available, %s might be %s',
                    ]
                ],
                'explanation' => [
                    'high' => [
                        'Here\'s how this works:',
                        'Let me break this down for you:',
                        'To explain this clearly:',
                    ],
                    'medium' => [
                        'From what I can gather:',
                        'Based on the documentation:',
                        'Here\'s what I understand:',
                    ],
                    'low' => [
                        'I found some relevant information:',
                        'While my understanding is partial:',
                    ]
                ],
            ],
            'transitionPhrases' => [
                'addition' => [
                    'Additionally,',
                    'Furthermore,',
                    'Moreover,',
                    'In addition,',
                    'What\'s more,',
                    'Beyond that,',
                    'On top of that,',
                    'Also worth noting,'
                ],
                'contrast' => [
                    'However,',
                    'On the other hand,',
                    'Conversely,',
                    'That said,',
                    'Nevertheless,',
                    'In contrast,',
                    'Alternatively,'
                ],
                'emphasis' => [
                    'Importantly,',
                    'Notably,',
                    'It\'s worth emphasizing that',
                    'Particularly significant is',
                    'A key point is'
                ],
                'example' => [
                    'For instance,',
                    'For example,',
                    'To illustrate,',
                    'Specifically,',
                    'In particular,',
                    'Such as',
                    'Like'
                ],
                'conclusion' => [
                    'In summary,',
                    'To sum up,',
                    'Overall,',
                    'In conclusion,',
                    'Ultimately,',
                    'In essence,'
                ],
            ],
        ]);
    }

    public function knowledgeBase(array $context): void
    {
        $this->context = $context;
    }

    /**
     * Generate an intelligent AI response to a user question
     * 
     * @param string $question The user's question
     * @return string AI-generated response
     */
    public function ask(string $question): string
    {
        // Normalize and preprocess question
        $normalizedQuestion = $this->normalizeQuestion($question);

        // Merge and flatten all available chunks
        $allChunks = $this->flattenChunks($this->context);

        if (empty($allChunks)) {
            return $this->generateNoInfoResponse($question);
        }

        // Extract question intent and classify type
        $questionAnalysis = $this->analyzeQuestion($normalizedQuestion);

        // Extract key entities from question
        $entities = $this->extractEntities($normalizedQuestion);

        // Rank all chunks by multi-factor relevance
        $rankedChunks = $this->rankChunksAdvanced(
            $normalizedQuestion,
            $allChunks,
            $entities,
            $questionAnalysis
        );

        // Check if we have sufficient relevant information (use more lenient threshold)
        // Allow chunks with lower scores if we have ranked results
        if (empty($rankedChunks)) {
            return $this->generateNoInfoResponse($question);
        }

        // Only reject if top score is extremely low (more lenient than before)
        if ($rankedChunks[0]['score'] < 0.05) {
            return $this->generateNoInfoResponse($question);
        }

        // Extract and synthesize answer from top-ranked chunks
        $topChunks = $this->selectOptimalChunks($rankedChunks, $questionAnalysis);

        // Generate contextually-aware, natural response
        return $this->generateIntelligentResponse(
            $question,
            $normalizedQuestion,
            $questionAnalysis,
            $topChunks,
            $entities
        );
    }

    // ==========================================
    // QUESTION ANALYSIS METHODS
    // ==========================================

    /**
     * Normalize question text for better processing
     */
    private function normalizeQuestion(string $question): string
    {
        // Trim whitespace
        $question = trim($question);

        // Normalize whitespace
        $question = preg_replace('/\s+/', ' ', $question);

        // Remove extra punctuation
        $question = preg_replace('/[?!.]+$/', '', $question);

        // Expand common contractions
        $contractions = [
            "what's" => "what is",
            "where's" => "where is",
            "who's" => "who is",
            "how's" => "how is",
            "can't" => "cannot",
            "won't" => "will not",
            "don't" => "do not",
            "doesn't" => "does not",
            "isn't" => "is not",
            "aren't" => "are not",
        ];

        $question = str_ireplace(array_keys($contractions), array_values($contractions), $question);

        return $question;
    }

    /**
     * Comprehensive question analysis
     */
    private function analyzeQuestion(string $question): array
    {
        $questionLower = strtolower($question);

        return [
            'type' => $this->detectQuestionType($questionLower),
            'intent' => $this->detectIntent($questionLower),
            'complexity' => $this->assessComplexity($question),
            'sentiment' => $this->detectSentiment($questionLower),
            'focus_words' => $this->extractFocusWords($question),
            'is_comparative' => $this->isComparativeQuestion($questionLower),
            'requires_list' => $this->requiresListResponse($questionLower),
            'temporal' => $this->hasTemporalElement($questionLower),
        ];
    }

    /**
     * Detect detailed question type
     */
    private function detectQuestionType(string $question): string
    {
        // Check list patterns FIRST (before definition) to catch "what are X" questions
        $listPatterns = [
            '/^(?:list|show|name|give|provide|tell me)\s+(?:all|the|some)/i',
            '/^what\s+are\s+(?:the|your|our|their|these|those)?\s*[a-z]+(?:ies|es|s)\b/i', // "what are the services", "what are services"
            '/^what\s+(?:do|does)\s+(?:you|we|they|it)\s+(?:offer|provide|have|include)/i',
            '/^(?:name|list|show)\s+(?:all|the|some|your|our)\s+[a-z]+(?:ies|es|s)?/i',
        ];

        foreach ($listPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return 'list';
            }
        }

        $patterns = [
            'comparison' => '/(?:difference|compare|versus|vs|better|worse|rather than)/i',
            'location' => '/^where\s+(?:is|are|can|do)|\b(?:headquarters?|headquartered|located|location|address|office)\b/i',
            'time' => '/^when\s+(?:is|are|did|does|will|should)/i',
            'person' => '/^who\s+(?:is|are|was|were|does)/i',
            'quantity' => '/^how\s+(?:many|much|often|long|far)/i',
            'method' => '/^how\s+(?:do|does|can|to|should)/i',
            'reason' => '/^why\s+(?:is|are|do|does|did|should)/i',
            'choice' => '/^(?:which|what)\s+(?:one|type|kind|option)/i',
            'pricing' => '/\b(?:price|cost|pricing|fee|charge|rate|expensive|cheap)\b/i',
            'contact' => '/\b(?:contact|email|phone|reach|address|call|message)\b/i',
            'confirmation' => '/^(?:is|are|do|does|can|could|would|should|will)\s+/i',
            'definition' => '/^(?:what|which)\s+(?:is|are|was|were|does|do)\s+(?:a|an|the)?\s*\w+/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $question)) {
                return $type;
            }
        }

        return 'general';
    }

    /**
     * Detect user intent
     */
    private function detectIntent(string $question): string
    {
        $intentKeywords = [
            'informational' => ['what', 'who', 'where', 'when', 'define', 'explain'],
            'instructional' => ['how', 'guide', 'tutorial', 'steps', 'process'],
            'navigational' => ['find', 'locate', 'search', 'where can i'],
            'transactional' => ['buy', 'purchase', 'order', 'subscribe', 'sign up'],
            'investigational' => ['why', 'reason', 'cause', 'because'],
            'comparative' => ['compare', 'difference', 'versus', 'better', 'or'],
        ];

        foreach ($intentKeywords as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($question, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * Assess question complexity
     */
    private function assessComplexity(string $question): string
    {
        $score = 0;

        // Length factor
        $wordCount = str_word_count($question);
        if ($wordCount > 15)
            $score += 2;
        elseif ($wordCount > 10)
            $score += 1;

        // Multiple clauses
        if (substr_count($question, ',') > 1)
            $score += 1;
        if (preg_match('/\b(and|or|but)\b/i', $question))
            $score += 1;

        // Technical terms or specific entities
        if (preg_match('/[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+/', $question))
            $score += 1;

        // Multiple question words
        $questionWordCount = 0;
        foreach ($this->questionWords as $qw) {
            if (stripos($question, $qw) !== false)
                $questionWordCount++;
        }
        if ($questionWordCount > 2)
            $score += 2;

        if ($score >= 4)
            return 'complex';
        if ($score >= 2)
            return 'moderate';
        return 'simple';
    }

    /**
     * Detect sentiment in question
     */
    private function detectSentiment(string $question): string
    {
        $positiveWords = ['good', 'great', 'excellent', 'best', 'better', 'like', 'love'];
        $negativeWords = ['bad', 'poor', 'worst', 'worse', 'hate', 'dislike', 'problem', 'issue'];

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            if (stripos($question, $word) !== false)
                $positiveCount++;
        }

        foreach ($negativeWords as $word) {
            if (stripos($question, $word) !== false)
                $negativeCount++;
        }

        if ($positiveCount > $negativeCount)
            return 'positive';
        if ($negativeCount > $positiveCount)
            return 'negative';
        return 'neutral';
    }

    /**
     * Extract focus words (key entities/terms)
     */
    private function extractFocusWords(string $question): array
    {
        $focusWords = [];

        // Proper nouns (capitalized words)
        if (preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', $question, $matches)) {
            $focusWords = array_merge($focusWords, $matches[0]);
        }

        // Numbers
        if (preg_match_all('/\b\d+(?:,\d{3})*(?:\.\d+)?\b/', $question, $matches)) {
            $focusWords = array_merge($focusWords, $matches[0]);
        }

        // Technical terms (long words)
        if (preg_match_all('/\b[a-z]{8,}\b/i', $question, $matches)) {
            $focusWords = array_merge($focusWords, $matches[0]);
        }

        return array_unique($focusWords);
    }

    /**
     * Check if question is comparative
     */
    private function isComparativeQuestion(string $question): bool
    {
        $comparativePatterns = [
            '/\b(?:versus|vs\.?|compared to|compare|difference between)\b/i',
            '/\b(?:better|worse|more|less|rather than|instead of)\b/i',
            '/\bor\b.*\b(?:which|what)\b/i',
        ];

        foreach ($comparativePatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if question requires list response
     */
    private function requiresListResponse(string $question): bool
    {
        // Check for explicit list keywords
        if (preg_match('/\b(?:list|all|every|each|multiple|various|several|types?|kinds?)\b/i', $question) === 1) {
            return true;
        }

        // Check for "what are X" patterns (asking for a list of things)
        if (preg_match('/^what\s+are\s+(?:the|your|our|their|these|those)?\s*[a-z]+(?:ies|es|s)?\??$/i', $question)) {
            return true;
        }

        // Check for "what do/does X offer/provide/have/include"
        if (preg_match('/^what\s+(?:do|does)\s+(?:you|we|they|it)\s+(?:offer|provide|have|include)/i', $question)) {
            return true;
        }

        return false;
    }

    /**
     * Check for temporal elements
     */
    private function hasTemporalElement(string $question): bool
    {
        return preg_match('/\b(?:when|time|date|year|month|day|recently|current|now|today|latest)\b/i', $question) === 1;
    }


    // ==========================================
    // ENTITY EXTRACTION
    // ==========================================

    /**
     * Extract named entities from text
     */
    private function extractEntities(string $text): array
    {
        $entities = [
            'emails' => [],
            'phones' => [],
            'urls' => [],
            'prices' => [],
            'dates' => [],
            'percentages' => [],
            'numbers' => [],
            'organizations' => [],
            'proper_nouns' => [],
        ];

        foreach ($this->entityPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $entities[$type . 's'] = array_unique($matches[0]);
            }
        }

        return array_filter($entities);
    }


    // ==========================================
    // CHUNK PROCESSING
    // ==========================================

    /**
     * Flatten nested chunk arrays recursively
     */
    private function flattenChunks(array $context): array
    {
        $flattened = [];

        // Process constructor chunks first (always prioritize these)
        foreach ($context as $chunk) {
            if (is_array($chunk) && isset($chunk['content']) && !empty($chunk['content'])) {
                $flattened[] = $this->normalizeChunk($chunk);
            } elseif (is_array($chunk)) {
                // Handle nested arrays
                foreach ($chunk as $subChunk) {
                    if (is_array($subChunk) && isset($subChunk['content']) && !empty($subChunk['content'])) {
                        $flattened[] = $this->normalizeChunk($subChunk);
                    }
                }
            }
        }

        return $flattened;
    }

    /**
     * Normalize chunk structure
     */
    private function normalizeChunk(array $chunk): array
    {
        return [
            'content' => $chunk['content'] ?? '',
            'source' => $chunk['source'] ?? 'unknown',
            'metadata' => $chunk['metadata'] ?? [],
            'timestamp' => $chunk['timestamp'] ?? null,
        ];
    }


    // ==========================================
    // ADVANCED RANKING SYSTEM
    // ==========================================

    /**
     * Advanced multi-factor chunk ranking
     */
    private function rankChunksAdvanced(
        string $question,
        array $chunks,
        array $entities,
        array $analysis
    ): array {
        $ranked = [];
        $questionTokens = $this->tokenize($question);
        $questionBigrams = $this->extractNgrams($question, 2);
        $questionTrigrams = $this->extractNgrams($question, 3);

        foreach ($chunks as $index => $chunk) {
            $content = $chunk['content'];
            $contentTokens = $this->tokenize($content);

            // Calculate multiple scoring factors
            $scores = [
                'keyword' => $this->calculateTFIDFScore($questionTokens, $contentTokens, $chunks),
                'bigram' => $this->calculateNgramMatch($questionBigrams, $this->extractNgrams($content, 2)),
                'trigram' => $this->calculateNgramMatch($questionTrigrams, $this->extractNgrams($content, 3)),
                'semantic' => $this->calculateAdvancedSemanticSimilarity($questionTokens, $contentTokens),
                'entity' => $this->calculateEntityMatchScore($entities, $content),
                'position' => $this->calculatePositionScore($index, count($chunks)),
            ];

            // Context-aware score adjustment
            $contextBoost = $this->calculateContextBoost($content, $analysis);

            // Weighted final score with small base to ensure non-zero scores for relevant content
            $weightedScore = (
                $scores['keyword'] * self::KEYWORD_WEIGHT +
                $scores['bigram'] * self::BIGRAM_WEIGHT +
                $scores['trigram'] * self::TRIGRAM_WEIGHT +
                $scores['semantic'] * self::SEMANTIC_WEIGHT +
                $scores['entity'] * self::ENTITY_WEIGHT +
                $scores['position'] * self::POSITION_WEIGHT
            );

            // Apply context boost - if boost is high, give a minimum score
            $finalScore = $weightedScore * $contextBoost;

            // Ensure chunks with high context boost get a minimum score
            if ($contextBoost > 1.2 && $finalScore < 0.05) {
                $finalScore = 0.05 * $contextBoost;
            }

            // Add all chunks with positive scores to ranked list
            if ($finalScore > 0) {
                $extractedSentences = $this->extractSentences($content);

                $ranked[] = [
                    'chunk' => $chunk,
                    'score' => $finalScore,
                    'scores_breakdown' => $scores,
                    'sentences' => !empty($extractedSentences) ? $extractedSentences : [$content], // Fallback to full content
                    'entities' => $this->extractEntities($content),
                ];
            }
        }

        usort($ranked, fn($a, $b) => $b['score'] <=> $a['score']);

        return $ranked;
    }

    /**
     * Calculate TF-IDF inspired score with plural/singular matching
     */
    private function calculateTFIDFScore(array $queryTokens, array $docTokens, array $allChunks): float
    {
        if (empty($queryTokens) || empty($docTokens)) {
            return 0.0;
        }

        $score = 0.0;
        $docTokenFreq = array_count_values($docTokens);
        $totalDocs = count($allChunks);
        $exactMatchBonus = 0.0;

        foreach ($queryTokens as $token) {
            $tokenVariations = $this->getWordVariations($token);
            $matched = false;
            $tokenScore = 0.0;

            // Check for exact matches first (highest priority)
            if (isset($docTokenFreq[$token])) {
                $tf = $docTokenFreq[$token] / count($docTokens);

                // Inverse document frequency
                $docsWithTerm = 0;
                foreach ($allChunks as $chunk) {
                    if (stripos($chunk['content'], $token) !== false) {
                        $docsWithTerm++;
                    }
                }

                $idf = $docsWithTerm > 0 ? log($totalDocs / max($docsWithTerm, 1)) : 0;
                $tokenScore = $tf * (1 + $idf);
                $exactMatchBonus += 0.2; // Boost for exact matches
                $matched = true;
            } else {
                // Check for variations (plural/singular)
                foreach ($tokenVariations as $variation) {
                    if ($variation !== $token && isset($docTokenFreq[$variation])) {
                        $tf = $docTokenFreq[$variation] / count($docTokens);

                        // Inverse document frequency for variation
                        $docsWithTerm = 0;
                        foreach ($allChunks as $chunk) {
                            if (stripos($chunk['content'], $variation) !== false) {
                                $docsWithTerm++;
                            }
                        }

                        $idf = $docsWithTerm > 0 ? log($totalDocs / max($docsWithTerm, 1)) : 0;
                        $tokenScore = $tf * (1 + $idf) * 0.8; // Slight penalty for variation match
                        $matched = true;
                        break; // Use first variation match
                    }
                }
            }

            // Also check for substring matches (e.g., "service" in "services")
            if (!$matched) {
                foreach ($docTokenFreq as $docToken => $freq) {
                    foreach ($tokenVariations as $variation) {
                        if (stripos($docToken, $variation) !== false || stripos($variation, $docToken) !== false) {
                            $tf = $freq / count($docTokens);
                            $tokenScore = $tf * 0.5; // Lower score for substring match
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if ($matched) {
                $score += $tokenScore;
            }
        }

        // Add exact match bonus
        $finalScore = min(($score / max(count($queryTokens), 1)) + $exactMatchBonus, 1.0);

        return $finalScore;
    }

    /**
     * Calculate n-gram matches
     */
    private function calculateNgramMatch(array $queryNgrams, array $contentNgrams): float
    {
        if (empty($queryNgrams)) {
            return 0.0;
        }

        $matches = count(array_intersect($queryNgrams, $contentNgrams));
        return $matches / count($queryNgrams);
    }

    /**
     * Extract n-grams from text
     */
    private function extractNgrams(string $text, int $n): array
    {
        $tokens = $this->tokenize($text, false);
        $ngrams = [];

        for ($i = 0; $i <= count($tokens) - $n; $i++) {
            $ngram = array_slice($tokens, $i, $n);
            $ngrams[] = implode(' ', $ngram);
        }

        return $ngrams;
    }

    /**
     * Advanced semantic similarity using cosine similarity approach
     */
    private function calculateAdvancedSemanticSimilarity(array $tokens1, array $tokens2): float
    {
        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $vector1 = $this->createWordVector($tokens1);
        $vector2 = $this->createWordVector($tokens2);

        return $this->cosineSimilarity($vector1, $vector2);
    }

    /**
     * Create word frequency vector
     */
    private function createWordVector(array $tokens): array
    {
        $vector = [];
        $counts = array_count_values($tokens);

        foreach ($counts as $word => $count) {
            // Use log normalization
            $vector[$word] = 1 + log($count);
        }

        return $vector;
    }

    /**
     * Calculate cosine similarity between vectors
     */
    private function cosineSimilarity(array $vector1, array $vector2): float
    {
        $allKeys = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        foreach ($allKeys as $key) {
            $val1 = $vector1[$key] ?? 0;
            $val2 = $vector2[$key] ?? 0;

            $dotProduct += $val1 * $val2;
            $magnitude1 += $val1 * $val1;
            $magnitude2 += $val2 * $val2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Calculate entity match score
     */
    private function calculateEntityMatchScore(array $queryEntities, string $content): float
    {
        if (empty($queryEntities)) {
            return 0.0;
        }

        $matches = 0;
        $total = 0;

        foreach ($queryEntities as $entityType => $entities) {
            foreach ($entities as $entity) {
                $total++;
                if (stripos($content, $entity) !== false) {
                    $matches++;
                }
            }
        }

        return $total > 0 ? $matches / $total : 0.0;
    }

    /**
     * Calculate position-based score (earlier chunks slightly favored)
     */
    private function calculatePositionScore(int $index, int $total): float
    {
        if ($total <= 1) {
            return 1.0;
        }

        return 1.0 - (($index / $total) * 0.3);
    }

    /**
     * Calculate context boost based on question analysis
     */
    private function calculateContextBoost(string $content, array $analysis): float
    {
        $boost = 1.0;

        // Boost for specific content types matching question type
        $type = $analysis['type'];

        if ($type === 'pricing' && preg_match('/\$|\bprice\b|\bcost\b/i', $content)) {
            $boost *= 1.3;
        }

        if ($type === 'contact' && preg_match('/@|phone|email|contact/i', $content)) {
            $boost *= 1.3;
        }

        if ($type === 'location' && preg_match('/\baddress\b|\blocation\b|\boffice\b|\bheadquarters?\b|\bheadquartered\b|\blocated\b/i', $content)) {
            $boost *= 1.4;
        }

        // Boost for content with address-like patterns (numbers followed by words, postal codes)
        if ($type === 'location' && preg_match('/\d+\s+[A-Z][a-z]+|\b[A-Z]{2}\s*\d{5}\b/i', $content)) {
            $boost *= 1.2;
        }

        // Enhanced boost for list-type content when list is required
        if ($analysis['requires_list'] || $type === 'list') {
            // Check for numbered lists (1., 2., etc.)
            if (preg_match('/^\s*\d+\.|\n\s*\d+\./m', $content)) {
                $boost *= 1.5; // Strong boost for numbered lists
            }
            // Check for bullet points or list markers
            elseif (preg_match('/^[\d\-•]|\n[\d\-•]/m', $content)) {
                $boost *= 1.3;
            }
            // Check for content that describes offerings
            if (preg_match('/\b(?:services?|offering|offer|provide|include)\b/i', $content)) {
                $boost *= 1.2;
            }
        }

        // Boost for temporal content when temporal element detected
        if ($analysis['temporal'] && preg_match('/\b\d{4}\b|\bdate\b|\byear\b/i', $content)) {
            $boost *= 1.15;
        }

        return $boost;
    }

    /**
     * Select optimal chunks based on diversity and relevance
     */
    private function selectOptimalChunks(array $rankedChunks, array $analysis): array
    {
        $complexity = $analysis['complexity'];

        // Determine number of chunks to use
        $chunkCount = match ($complexity) {
            'complex' => 5,
            'moderate' => 3,
            'simple' => 2,
            default => 3,
        };

        // Select top chunks while maintaining diversity
        $selected = [];
        $usedSources = [];

        foreach ($rankedChunks as $chunkData) {
            if (count($selected) >= $chunkCount) {
                break;
            }

            $source = $chunkData['chunk']['source'];

            // Prefer diverse sources, but allow duplicates for high-scoring chunks
            if (!isset($usedSources[$source]) || $chunkData['score'] > 0.7) {
                $selected[] = $chunkData;
                $usedSources[$source] = true;
            }
        }

        return $selected;
    }


    // ==========================================
    // INTELLIGENT RESPONSE GENERATION
    // ==========================================

    /**
     * Generate contextually intelligent response
     */
    private function generateIntelligentResponse(
        string $originalQuestion,
        string $normalizedQuestion,
        array $analysis,
        array $topChunks,
        array $entities
    ): string {
        // Extract relevant information with enhanced context
        $info = $this->extractRelevantInformation($normalizedQuestion, $topChunks, $analysis);

        if (empty($info['sentences'])) {
            return $this->generateNoInfoResponse($originalQuestion);
        }

        // Build response structure
        $response = $this->buildResponseStructure($info, $analysis, $originalQuestion);

        // Add proper spacing and formatting
        $response = $this->formatResponse($response, $analysis);

        return $response;
    }

    /**
     * Extract relevant information with contextual understanding
     */
    private function extractRelevantInformation(string $question, array $chunks, array $analysis): array
    {
        $relevantSentences = [];
        $sources = [];
        $allEntities = [];

        foreach ($chunks as $chunkData) {
            $sentences = $chunkData['sentences'];
            $chunkScore = $chunkData['score'];
            $chunkEntities = $chunkData['entities'];
            $chunkContent = $chunkData['chunk']['content'] ?? '';

            // If no sentences were extracted but chunk has content, use the whole content
            if (empty($sentences) && !empty($chunkContent)) {
                $sentences = [$chunkContent];
            }

            foreach ($sentences as $sentence) {
                $sentenceScore = $this->scoreSentenceAdvanced($sentence, $question, $analysis);

                // Use a lower threshold (0.05 instead of 0.1) to capture more relevant content
                if ($sentenceScore > 0.05) {
                    $relevantSentences[] = [
                        'text' => $sentence,
                        'score' => $sentenceScore * $chunkScore,
                        'source' => $chunkData['chunk']['source'] ?? 'unknown',
                        'length' => strlen($sentence),
                        'has_entity' => $this->containsKeyEntity($sentence, $chunkEntities),
                    ];
                }
            }

            // If still no relevant sentences and this is a high-scoring chunk, 
            // add the chunk content directly with a base score
            if (empty($relevantSentences) && $chunkScore > 0.1 && !empty($chunkContent)) {
                $relevantSentences[] = [
                    'text' => $chunkContent,
                    'score' => $chunkScore * 0.5, // Reduced score for fallback
                    'source' => $chunkData['chunk']['source'] ?? 'unknown',
                    'length' => strlen($chunkContent),
                    'has_entity' => !empty($chunkEntities),
                ];
            }

            $sources[] = $chunkData['chunk']['source'] ?? 'unknown';
            $allEntities = array_merge_recursive($allEntities, $chunkEntities);
        }

        // Sort by score
        usort($relevantSentences, fn($a, $b) => $b['score'] <=> $a['score']);

        // Calculate confidence
        $confidence = $this->calculateConfidence($relevantSentences, $analysis);

        return [
            'sentences' => $relevantSentences,
            'sources' => array_unique($sources),
            'entities' => $allEntities,
            'confidence' => $confidence,
        ];
    }

    /**
     * Advanced sentence scoring with improved matching
     */
    private function scoreSentenceAdvanced(string $sentence, string $question, array $analysis): float
    {
        $sentence = trim($sentence);
        $sentenceLen = strlen($sentence);

        // Very short sentences get minimal score but not zero
        if ($sentenceLen < 10) {
            return 0.0;
        }

        // Very long text gets reduced score but not eliminated
        $lengthPenalty = 1.0;
        if ($sentenceLen > self::MAX_SENTENCE_LENGTH * 2) {
            $lengthPenalty = 0.5;
        } elseif ($sentenceLen > self::MAX_SENTENCE_LENGTH) {
            $lengthPenalty = 0.8;
        }

        $questionTokens = $this->tokenize($question);
        $sentenceTokens = $this->tokenize($sentence);
        $sentenceLower = strtolower($sentence);

        // Calculate coverage with word variations
        $matches = 0;
        foreach ($questionTokens as $qToken) {
            // Direct match
            if (in_array($qToken, $sentenceTokens)) {
                $matches++;
                continue;
            }

            // Check variations (plural/singular, etc.)
            $variations = $this->getWordVariations($qToken);
            foreach ($variations as $variation) {
                if (in_array($variation, $sentenceTokens)) {
                    $matches += 0.8; // Slight penalty for variation match
                    break;
                }
            }

            // Check for stem match (e.g., headquarters -> headquartered)
            foreach ($sentenceTokens as $sToken) {
                // Check if tokens share a common root (first 6+ chars)
                if (strlen($qToken) >= 6 && strlen($sToken) >= 6) {
                    $qRoot = substr($qToken, 0, min(strlen($qToken) - 2, 8));
                    if (strpos($sToken, $qRoot) === 0 || strpos($qToken, substr($sToken, 0, 6)) === 0) {
                        $matches += 0.6;
                        break;
                    }
                }
            }
        }

        $coverage = count($questionTokens) > 0 ? $matches / count($questionTokens) : 0;

        // Start with base score - give non-zero score for relevant content
        $baseScore = 0.05; // Small base score for all content

        // Apply boosts based on content type
        $boost = 1.0;

        // Entity boost (proper nouns)
        if (preg_match('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/', $sentence)) {
            $boost += 0.15;
        }

        // Number/data boost
        if (preg_match('/\b\d+(?:[.,]\d+)*\b/', $sentence)) {
            $boost += 0.15;
        }

        // Contact information boost
        if (preg_match('/@|\.com|http|phone|email/i', $sentenceLower)) {
            $boost += 0.25;
            $baseScore += 0.1; // Higher base for contact info
        }

        // Pricing information boost
        if (preg_match('/\$|price|cost|fee/i', $sentenceLower)) {
            $boost += 0.20;
            $baseScore += 0.1;
        }

        // Question type specific boosts
        $type = $analysis['type'];

        if ($type === 'definition' && preg_match('/\b(?:is|are|refers to|means|represents)\b/i', $sentenceLower)) {
            $boost += 0.15;
        }

        if ($type === 'method' && preg_match('/\b(?:by|through|using|via|can|should)\b/i', $sentenceLower)) {
            $boost += 0.15;
        }

        // Location type boost
        if ($type === 'location') {
            // Boost for location-related keywords
            if (preg_match('/\b(?:headquarters?|headquartered|located|location|office|address)\b/i', $sentenceLower)) {
                $boost += 0.5;
                $baseScore += 0.2;
            }
            // Boost for address-like patterns (number + words, or postal code patterns)
            if (preg_match('/\d+\s+[A-Z][a-z]+(?:\s+[A-Za-z]+)*/i', $sentence)) {
                $boost += 0.3;
                $baseScore += 0.1;
            }
        }

        // Contact type boosts
        if ($type === 'contact') {
            if (preg_match('/\b(?:phone|email|contact|call|reach)\b/i', $sentenceLower)) {
                $boost += 0.3;
                $baseScore += 0.15;
            }
        }

        // List type - boost items that look like services/features
        if ($type === 'list' || $analysis['requires_list']) {
            if (preg_match('/^[A-Z][a-z]+\s+[A-Z]?[a-z]+/i', $sentence)) {
                $boost += 0.15;
            }
        }

        // Penalize very short sentences unless they're high-value
        if ($sentenceLen < 30 && $baseScore < 0.15) {
            $boost *= 0.7;
        }

        // Penalize generic opening sentences
        if (preg_match('/^(?:yes|no|maybe|perhaps),?\s/i', $sentence)) {
            $boost *= 0.5;
        }

        // Calculate final score
        $finalScore = ($baseScore + $coverage) * $boost * $lengthPenalty;

        return min($finalScore, 1.0); // Cap at 1.0
    }

    /**
     * Check if sentence contains key entities
     */
    private function containsKeyEntity(string $sentence, array $entities): bool
    {
        foreach ($entities as $entityType => $entityList) {
            foreach ($entityList as $entity) {
                if (stripos($sentence, $entity) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Calculate confidence score
     */
    private function calculateConfidence(array $sentences, array $analysis): float
    {
        if (empty($sentences)) {
            return 0.0;
        }

        // Base confidence on top sentence scores
        $topScores = array_slice(array_column($sentences, 'score'), 0, 3);
        $avgScore = array_sum($topScores) / count($topScores);

        // Adjust based on question complexity
        $complexityMultiplier = match ($analysis['complexity']) {
            'simple' => 1.2,
            'moderate' => 1.0,
            'complex' => 0.85,
            default => 1.0,
        };

        $confidence = $avgScore * $complexityMultiplier * 100;

        return min($confidence, 95); // Cap at 95%
    }

    /**
     * Build structured response
     */
    private function buildResponseStructure(array $info, array $analysis, string $question): string
    {
        $type = $analysis['type'];
        $confidence = $info['confidence'];
        $sentences = $info['sentences'];

        // Determine response style
        $style = $this->determineResponseStyle($analysis);

        // Generate opening
        $opening = $this->generateContextualOpening($type, $confidence, $style, $question);

        // Generate main content
        $mainContent = $this->generateMainContent($sentences, $type, $analysis, $info['entities']);

        // Generate supporting details
        $supporting = $this->generateSupportingDetails($sentences, $type, $analysis);

        // Generate conclusion if needed
        $conclusion = $this->generateConclusion($type, $analysis, $confidence);

        // Assemble response with proper spacing
        // For list responses, use single newline after opening, then preserve list formatting
        if ($type === 'list' || $analysis['requires_list']) {
            $response = $opening;
            if (!empty($opening) && !empty($mainContent)) {
                $response .= "\n\n" . $mainContent;
            } elseif (!empty($mainContent)) {
                $response = $mainContent;
            }
            if (!empty($supporting)) {
                $response .= "\n\n" . $supporting;
            }
            if (!empty($conclusion)) {
                $response .= "\n\n" . $conclusion;
            }
            return $response;
        }

        // For other response types, use double newlines between sections
        $parts = array_filter([
            $opening,
            $mainContent,
            $supporting,
            $conclusion,
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * Determine optimal response style
     */
    private function determineResponseStyle(array $analysis): string
    {
        if ($analysis['requires_list']) {
            return 'list';
        }

        if ($analysis['is_comparative']) {
            return 'comparative';
        }

        if ($analysis['intent'] === 'instructional') {
            return 'instructional';
        }

        if (in_array($analysis['type'], ['definition', 'person', 'location'])) {
            return 'concise';
        }

        return 'comprehensive';
    }

    /**
     * Generate contextual opening
     */
    private function generateContextualOpening(string $type, float $confidence, string $style, string $question): string
    {
        $confidenceLevel = $confidence > self::HIGH_CONFIDENCE ? 'high'
            : ($confidence > self::MEDIUM_CONFIDENCE ? 'medium' : 'low');

        // Type-specific openings
        $openings = [
            'definition' => [
                'high' => ['', 'To clarify,', 'In simple terms,'],
                'medium' => ['Based on the information available,', 'From what I understand,', 'The documentation indicates that'],
                'low' => ['I found some information about this:', 'While my information is limited,'],
            ],
            'explanation' => [
                'high' => ['Let me explain:', 'Here\'s how this works:', 'To break this down:'],
                'medium' => ['From what I can gather,', 'Based on the documentation,', 'Here\'s what I found:'],
                'low' => ['I have some relevant information:', 'Based on partial documentation,'],
            ],
            'method' => [
                'high' => ['Here\'s the process:', 'This is how you can do it:', 'Follow these steps:'],
                'medium' => ['Based on the available information,', 'From what I understand,'],
                'low' => ['I found some guidance:', 'There\'s some information about this:'],
            ],
            'list' => [
                'high' => ['', 'Here\'s what I found:', ''],
                'medium' => ['Based on the documentation:', 'I found the following:'],
                'low' => ['Here\'s what I found:', 'The available information includes:'],
            ],
            'pricing' => [
                'high' => ['', 'The pricing details are:', ''],
                'medium' => ['Based on the pricing information:', 'Here\'s the pricing structure:'],
                'low' => ['I found some pricing details:', 'Here\'s what I found on pricing:'],
            ],
            'location' => [
                'high' => ['', 'Here\'s the location information:', ''],
                'medium' => ['Based on the available information,', ''],
                'low' => ['I found some information:', ''],
            ],
            'contact' => [
                'high' => ['', 'Here are the contact details:', ''],
                'medium' => ['Based on the available information,', ''],
                'low' => ['I found some contact information:', ''],
            ],
        ];

        $typeOpenings = $openings[$type] ?? $openings['definition'];
        $options = $typeOpenings[$confidenceLevel];

        $opening = $options[array_rand($options)];

        // Add emphasis for complex questions or high-confidence responses
        if ($style === 'comprehensive' && $confidence > self::HIGH_CONFIDENCE) {
            $emphasisPhrases = [
                'I\'d be happy to help with that.',
                'Let me provide you with the information.',
            ];
            $emphasis = $emphasisPhrases[array_rand($emphasisPhrases)];
            $opening = $emphasis . ' ' . $opening;
        }

        return trim($opening);
    }

    /**
     * Generate main content
     */
    private function generateMainContent(array $sentences, string $type, array $analysis, array $entities): string
    {
        if (empty($sentences)) {
            return "I don't have specific information about that in the available documents.";
        }

        // Route to appropriate content generator based on type
        if ($analysis['is_comparative']) {
            return $this->generateComparativeContent($sentences);
        }

        // For list, pricing, contact, location - just return relevant content as-is
        if ($analysis['requires_list'] || in_array($type, ['list', 'pricing', 'contact', 'location'])) {
            return $this->generateSimpleContent($sentences);
        }

        return $this->generateNarrativeContent($sentences, $analysis);
    }

    /**
     * Generate simple content - returns relevant sentences without over-processing
     */
    private function generateSimpleContent(array $sentences): string
    {
        $content = [];
        $seen = [];

        foreach (array_slice($sentences, 0, 8) as $sentenceData) {
            $text = trim($sentenceData['text']);
            $textLower = strtolower($text);

            // Skip duplicates and very short content
            if (in_array($textLower, $seen) || strlen($text) < 10) {
                continue;
            }
            $seen[] = $textLower;
            $content[] = $text;
        }

        if (empty($content)) {
            return !empty($sentences) ? $sentences[0]['text'] : '';
        }

        return implode("\n", $content);
    }

    /**
     * Generate comparative content
     */
    private function generateComparativeContent(array $sentences): string
    {
        $topSentences = array_slice($sentences, 0, 3);
        $parts = [];

        foreach ($topSentences as $sentenceData) {
            $cleaned = $this->cleanSentence($sentenceData['text']);
            if (!empty($cleaned)) {
                $parts[] = $cleaned;
            }
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        // Connect with comparative transitions
        $transitions = ['On the other hand,', 'In contrast,', 'Conversely,', 'Meanwhile,'];
        $result = $parts[0];

        for ($i = 1; $i < count($parts); $i++) {
            $transition = $transitions[array_rand($transitions)];
            $result .= ' ' . $transition . ' ' . lcfirst($parts[$i]);
        }

        return $result;
    }

    /**
     * Generate narrative content
     */
    private function generateNarrativeContent(array $sentences, array $analysis): string
    {
        $complexity = $analysis['complexity'];

        $sentenceCount = match ($complexity) {
            'complex' => 3,
            'moderate' => 2,
            'simple' => 1,
            default => 2,
        };

        $selectedSentences = array_slice($sentences, 0, $sentenceCount);

        if (count($selectedSentences) === 1) {
            return $this->cleanSentence($selectedSentences[0]['text']);
        }

        // Build cohesive narrative
        $narrative = $this->cleanSentence($selectedSentences[0]['text']);

        for ($i = 1; $i < count($selectedSentences); $i++) {
            $transition = $this->selectTransition($i, count($selectedSentences), $analysis);
            $sentence = $this->cleanSentence($selectedSentences[$i]['text']);

            $narrative .= ' ' . $transition . ' ' . lcfirst($sentence);
        }

        return $narrative;
    }

    /**
     * Select appropriate transition phrase
     */
    private function selectTransition(int $position, int $total, array $analysis): string
    {
        $type = $analysis['type'];

        // Last sentence - use conclusion transitions
        if ($position === $total - 1) {
            $transitions = $this->transitionPhrases['conclusion'];
            return $transitions[array_rand($transitions)];
        }

        // Choose based on context
        if ($type === 'explanation' || $type === 'method') {
            $transitions = $this->transitionPhrases['addition'];
        } elseif ($analysis['is_comparative']) {
            $transitions = $this->transitionPhrases['contrast'];
        } else {
            $transitions = $this->transitionPhrases['addition'];
        }

        return $transitions[array_rand($transitions)];
    }

    /**
     * Generate supporting details
     */
    private function generateSupportingDetails(array $sentences, string $type, array $analysis): string
    {
        // Only add supporting details for comprehensive responses
        if ($analysis['complexity'] !== 'complex' || count($sentences) < 4) {
            return '';
        }

        $supportSentences = array_slice($sentences, 3, 2);
        $details = [];

        foreach ($supportSentences as $sentenceData) {
            $text = $this->cleanSentence($sentenceData['text']);

            if (strlen($text) > 30 && strlen($text) < 180 && $sentenceData['score'] > 0.3) {
                $details[] = $text;
            }
        }

        if (empty($details)) {
            return '';
        }

        $transition = $this->transitionPhrases['addition'][array_rand($this->transitionPhrases['addition'])];
        return $transition . ' ' . lcfirst(implode(' ', $details));
    }

    /**
     * Generate conclusion
     */
    private function generateConclusion(string $type, array $analysis, float $confidence): string
    {
        // Only add conclusion for complex questions or when confidence is low
        if ($analysis['complexity'] !== 'complex' && $confidence > self::MEDIUM_CONFIDENCE) {
            return '';
        }

        if ($confidence < self::MEDIUM_CONFIDENCE) {
            $closings = [
                "If you need more specific information, please let me know.",
                "I can provide more details if you have additional questions.",
                "Feel free to ask if you'd like me to clarify anything.",
            ];
            return $closings[array_rand($closings)];
        }

        if ($type === 'contact') {
            return "I hope this helps you get in touch!";
        }

        if ($type === 'pricing') {
            return "These are the pricing details I found in the documentation.";
        }

        return '';
    }

    /**
     * Format final response with proper spacing and structure
     */
    private function formatResponse(string $response, array $analysis): string
    {
        // Preserve newlines for lists, but clean up excessive whitespace
        // Split by newlines to preserve list structure
        $lines = preg_split('/\n/', $response);
        $formattedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Clean up multiple spaces within the line
                $line = preg_replace('/\s+/', ' ', $line);
                $formattedLines[] = $line;
            }
        }

        // Rejoin with single newlines (preserving list structure)
        $response = implode("\n", $formattedLines);

        // Ensure proper paragraph spacing (double newlines) between major sections
        // But preserve single newlines for lists
        $response = preg_replace('/([.!?])\s*\n\s*([A-Z])/', "$1\n\n$2", $response);

        // Clean up excessive newlines (more than 2 consecutive)
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        // Trim
        $response = trim($response);

        // Ensure ends with proper punctuation (but not if it's a list)
        if (!preg_match('/[.!?]$/', $response) && !preg_match('/\n\d+\./', $response)) {
            $response .= '.';
        }

        return $response;
    }


    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Extract sentences from text with better boundary detection
     * Handles both traditional sentences and list-formatted content
     */
    private function extractSentences(string $text): array
    {
        $results = [];

        // Handle common abbreviations to prevent false splits
        $text = preg_replace('/\b(Dr|Mr|Mrs|Ms|Prof|Sr|Jr|Inc|Ltd|Corp)\./i', '$1<DOT>', $text);

        // First, split by newlines to handle list items separately
        $lines = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check if this is a list item (starts with -, •, *, or number followed by .)
            if (preg_match('/^[\-•\*]\s*(.+)$/', $line, $matches)) {
                // List item - add the content directly
                $item = trim($matches[1]);
                if (strlen($item) >= 10) { // Lower threshold for list items
                    $results[] = str_replace('<DOT>', '.', $item);
                }
            } elseif (preg_match('/^\d+[.)]\s*(.+)$/', $line, $matches)) {
                // Numbered list item
                $item = trim($matches[1]);
                if (strlen($item) >= 10) {
                    $results[] = str_replace('<DOT>', '.', $item);
                }
            } else {
                // Regular text - try to split into sentences
                $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $line, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($sentences as $sentence) {
                    $sentence = trim(str_replace('<DOT>', '.', $sentence));
                    if (strlen($sentence) >= self::MIN_SENTENCE_LENGTH) {
                        $results[] = $sentence;
                    }
                }

                // If no sentences extracted but line has content, use the whole line
                if (empty($sentences) && strlen($line) >= self::MIN_SENTENCE_LENGTH) {
                    $results[] = str_replace('<DOT>', '.', $line);
                }
            }
        }

        // Also try to extract from the whole text if we got nothing from lines
        if (empty($results) && strlen($text) >= self::MIN_SENTENCE_LENGTH) {
            // Split on common delimiters including colons for headers
            $parts = preg_split('/(?<=[.!?:])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $part = trim(str_replace('<DOT>', '.', $part));
                if (strlen($part) >= self::MIN_SENTENCE_LENGTH) {
                    $results[] = $part;
                }
            }

            // Last resort: use the entire text as one "sentence"
            if (empty($results)) {
                $results[] = str_replace('<DOT>', '.', trim($text));
            }
        }

        // Filter by max length and remove duplicates
        return array_values(array_unique(array_filter($results, function ($s) {
            return strlen($s) <= self::MAX_SENTENCE_LENGTH * 2; // Allow longer for full content
        })));
    }

    /**
     * Clean and normalize sentence
     */
    private function cleanSentence(string $sentence): string
    {
        // Trim whitespace
        $sentence = trim($sentence);

        // Normalize whitespace
        $sentence = preg_replace('/\s+/', ' ', $sentence);

        // Remove list markers
        $sentence = preg_replace('/^[\d\-•)\]]+\s*/', '', $sentence);

        // Remove extra punctuation
        $sentence = preg_replace('/([.!?])\1+/', '$1', $sentence);

        // Ensure proper ending
        if (!preg_match('/[.!?]$/', $sentence)) {
            $sentence .= '.';
        }

        // Capitalize first letter
        $sentence = ucfirst($sentence);

        return $sentence;
    }

    /**
     * Normalize word to base form (handle plurals/singulars)
     */
    private function normalizeWord(string $word): string
    {
        $word = strtolower(trim($word));

        if (strlen($word) <= 3) {
            return $word;
        }

        // Common plural to singular rules
        // Words ending in -ies -> -y (services -> service, cities -> city)
        if (preg_match('/^(.+)ies$/', $word, $matches)) {
            return $matches[1] . 'y';
        }

        // Words ending in -es (after s, x, z, ch, sh) -> remove es
        if (preg_match('/^(.+)(?:s|x|z|ch|sh)es$/', $word, $matches)) {
            return $matches[1] . substr($word, -3, 1); // Keep the letter before 'es'
        }

        // Words ending in -es -> -e (cases -> case, but not always)
        if (preg_match('/^(.+)es$/', $word, $matches) && strlen($matches[1]) > 3) {
            // Only if it doesn't end in s, x, z, ch, sh
            if (!preg_match('/[sxz]|ch|sh$/', $matches[1])) {
                return $matches[1] . 'e';
            }
        }

        // Words ending in -s (simple plurals) -> remove s
        if (preg_match('/^(.+)s$/', $word, $matches) && strlen($matches[1]) > 2) {
            // Don't remove 's' if word ends in 'ss' or is too short
            if (!preg_match('/ss$/', $matches[1])) {
                return $matches[1];
            }
        }

        return $word;
    }

    /**
     * Get word variations (plural, singular, and related forms) for matching
     */
    private function getWordVariations(string $word): array
    {
        $word = strtolower($word);
        $variations = [$word];
        $normalized = $this->normalizeWord($word);

        if ($normalized !== $word) {
            $variations[] = $normalized;
        }

        // Also add plural form if we normalized to singular
        if ($normalized !== $word && strlen($normalized) > 2) {
            // Simple pluralization: add 's'
            if (!preg_match('/[sxz]|ch|sh$/', $normalized)) {
                $variations[] = $normalized . 's';
            }
            // Words ending in 'y' -> 'ies'
            if (preg_match('/y$/', $normalized)) {
                $variations[] = substr($normalized, 0, -1) . 'ies';
            }
        }

        // Special case mappings for common related words
        $specialMappings = [
            'headquarters' => ['headquartered', 'headquarter', 'hq'],
            'headquartered' => ['headquarters', 'headquarter', 'hq'],
            'location' => ['located', 'locate', 'locations'],
            'located' => ['location', 'locate', 'locations'],
            'office' => ['offices', 'official'],
            'offices' => ['office', 'official'],
            'service' => ['services', 'serving'],
            'services' => ['service', 'serving'],
            'price' => ['prices', 'pricing', 'priced'],
            'pricing' => ['price', 'prices', 'priced'],
            'contact' => ['contacts', 'contacting'],
            'found' => ['founded', 'founder', 'founding'],
            'founded' => ['found', 'founder', 'founding'],
        ];

        if (isset($specialMappings[$word])) {
            $variations = array_merge($variations, $specialMappings[$word]);
        }

        // Add -ed and -ing forms for verbs
        if (strlen($word) > 4 && !preg_match('/(?:ed|ing)$/', $word)) {
            $variations[] = $word . 'ed';
            $variations[] = $word . 'ing';
            // Handle words ending in 'e'
            if (preg_match('/e$/', $word)) {
                $variations[] = substr($word, 0, -1) . 'ed';
                $variations[] = substr($word, 0, -1) . 'ing';
            }
        }

        // Add word stem (first 6 chars) for partial matching
        if (strlen($word) > 6) {
            $variations[] = substr($word, 0, 6);
        }

        return array_unique($variations);
    }

    /**
     * Tokenize text into meaningful words with preprocessing
     */
    private function tokenize(string $text, bool $removeStopWords = true): array
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Preserve important punctuation-attached words (emails, URLs)
        $text = preg_replace('/([a-z0-9])@([a-z0-9])/', '$1AT$2', $text);
        $text = preg_replace('/([a-z0-9])\.com/', '$1DOTCOM', $text);

        // Remove other punctuation
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);

        // Restore preserved patterns
        $text = str_replace(['AT', 'DOTCOM'], ['@', '.com'], $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Important domain-specific terms that should never be filtered
        $keepDomainTerms = [
            // Location terms
            'headquarters',
            'headquartered',
            'office',
            'offices',
            'location',
            'locations',
            'address',
            'addresses',
            // Business terms  
            'services',
            'service',
            'products',
            'product',
            'pricing',
            'price',
            'contact',
            'team',
            'teams',
            // Question-related words that help with context matching
            'where',
            'what',
            'when',
            'who',
            'how',
            'which',
            'why',
            // General business terms
            'company',
            'founded',
            'mission',
            'values',
            'about',
        ];

        // Remove stop words if requested
        if ($removeStopWords) {
            $words = array_filter($words, function ($word) use ($keepDomainTerms) {
                // Always keep domain-specific terms
                if (in_array($word, $keepDomainTerms)) {
                    return true;
                }
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
            "I apologize, but I don't have specific information about that in the available documents.",

            "Unfortunately, I couldn't find detailed information about that in the documentation I have access to.",

            "I don't have enough information in the current knowledge base to answer that question accurately. Could you rephrase your question or ask about something else?",

            "That's not covered in the documents I have available. Is there something else I can help you with?",

            "I wasn't able to locate specific information about that in the documentation. You might want to contact us directly for more details.",
        ];

        return $responses[array_rand($responses)];
    }
};