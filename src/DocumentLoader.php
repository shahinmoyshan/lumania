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
     * Enhanced with semantic boundary detection and proper overlap handling
     */
    public function chunkDocuments(array $documents)
    {
        $chunks = [];

        foreach ($documents as $doc) {
            $content = $this->normalizeText($doc['content']);
            $paragraphs = $this->splitIntoParagraphs($content);

            $currentChunk = '';
            $currentSentences = [];
            $overlapSentences = [];
            $chunkIndex = 0;

            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (empty($paragraph)) {
                    continue;
                }

                $sentences = $this->splitIntoSentences($paragraph);

                foreach ($sentences as $sentence) {
                    $sentence = trim($sentence);
                    if (empty($sentence)) {
                        continue;
                    }

                    $testChunk = trim($currentChunk . ' ' . $sentence);
                    $testLength = strlen($testChunk);

                    // If adding this sentence would exceed chunk size
                    if ($testLength > $this->chunkSize && !empty($currentChunk)) {
                        // Save current chunk
                        $chunkContent = trim($currentChunk);
                        $chunks[] = [
                            'id' => md5($doc['filename'] . '_' . $chunkIndex),
                            'source' => $doc['filename'],
                            'content' => $chunkContent,
                            'chunk_index' => $chunkIndex,
                            'char_count' => strlen($chunkContent),
                            'word_count' => str_word_count($chunkContent),
                            'has_structure' => $this->hasStructure($chunkContent)
                        ];

                        // Calculate overlap: use last N sentences based on chunkOverlap
                        $overlapSentences = $this->getOverlapSentences($currentSentences, $this->chunkOverlap);
                        $overlapText = implode(' ', $overlapSentences);

                        // Start new chunk with overlap
                        $currentChunk = trim($overlapText . ' ' . $sentence);
                        $currentSentences = array_merge($overlapSentences, [$sentence]);
                        $chunkIndex++;
                    } else {
                        // Add sentence to current chunk
                        $currentChunk = $testChunk;
                        $currentSentences[] = $sentence;
                    }
                }

                // If paragraph break and chunk is getting large, consider breaking
                if (strlen($currentChunk) > $this->chunkSize * 0.8 && !empty($currentChunk)) {
                    // Save current chunk at paragraph boundary
                    $chunkContent = trim($currentChunk);
                    $chunks[] = [
                        'id' => md5($doc['filename'] . '_' . $chunkIndex),
                        'source' => $doc['filename'],
                        'content' => $chunkContent,
                        'chunk_index' => $chunkIndex,
                        'char_count' => strlen($chunkContent),
                        'word_count' => str_word_count($chunkContent),
                        'has_structure' => $this->hasStructure($chunkContent)
                    ];

                    // Start new chunk with overlap
                    $overlapSentences = $this->getOverlapSentences($currentSentences, $this->chunkOverlap);
                    $currentChunk = implode(' ', $overlapSentences);
                    $currentSentences = $overlapSentences;
                    $chunkIndex++;
                }
            }

            // Add remaining chunk
            if (!empty(trim($currentChunk))) {
                $chunkContent = trim($currentChunk);
                $chunks[] = [
                    'id' => md5($doc['filename'] . '_' . $chunkIndex),
                    'source' => $doc['filename'],
                    'content' => $chunkContent,
                    'chunk_index' => $chunkIndex,
                    'char_count' => strlen($chunkContent),
                    'word_count' => str_word_count($chunkContent),
                    'has_structure' => $this->hasStructure($chunkContent)
                ];
            }
        }

        return $chunks;
    }

    /**
     * Normalize text before processing
     */
    private function normalizeText($text)
    {
        // Normalize whitespace but preserve paragraph breaks
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Preserve double newlines as paragraph breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Split text into paragraphs
     */
    private function splitIntoParagraphs($text)
    {
        // Split by double newlines or markdown headers
        $paragraphs = preg_split('/\n\s*\n|(?=^#{1,6}\s+)/m', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter(array_map('trim', $paragraphs));
    }

    /**
     * Get overlap sentences based on word count
     */
    private function getOverlapSentences(array $sentences, $overlapWords)
    {
        if (empty($sentences)) {
            return [];
        }

        $overlapSentences = [];
        $wordCount = 0;

        // Get sentences from the end until we reach overlap word count
        for ($i = count($sentences) - 1; $i >= 0; $i--) {
            $sentence = $sentences[$i];
            $sentenceWords = str_word_count($sentence);

            if ($wordCount + $sentenceWords <= $overlapWords || empty($overlapSentences)) {
                array_unshift($overlapSentences, $sentence);
                $wordCount += $sentenceWords;
            } else {
                break;
            }
        }

        return $overlapSentences;
    }

    /**
     * Check if text has structured elements
     */
    private function hasStructure($text)
    {
        // Check for lists, tables, markdown, etc.
        return preg_match('/^[\s]*[-*+]\s|^\d+\.\s|^\|.*\|/m', $text) === 1;
    }

    /**
     * Split text into sentences with enhanced detection
     * Handles abbreviations, decimals, URLs, emails, and quotes
     */
    private function splitIntoSentences($text)
    {
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (empty($text)) {
            return [];
        }

        // Common abbreviations that shouldn't end sentences
        $abbreviations = [
            'mr',
            'mrs',
            'ms',
            'dr',
            'prof',
            'sr',
            'jr',
            'vs',
            'etc',
            'e.g',
            'i.e',
            'a.m',
            'p.m',
            'inc',
            'ltd',
            'corp',
            'co',
            'st',
            'ave',
            'blvd',
            'rd',
            'no',
            'vol',
            'pp',
            'ed',
            'eds',
            'approx',
            'est',
            'min',
            'max',
            'fig',
            'no',
            'nos',
            'p',
            'pp',
            'ch',
            'sec',
            'ref'
        ];

        // Pattern to match sentence endings while avoiding false positives
        // Matches: . ! ? followed by space and capital letter or end of string
        // But excludes: abbreviations, decimals, URLs, emails

        // First, protect URLs and emails
        $text = preg_replace_callback(
            '/(https?:\/\/[^\s]+|www\.[^\s]+|[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
            function ($matches) {
                return str_replace(['.', '!', '?'], ['[DOT]', '[EXCL]', '[QUEST]'], $matches[0]);
            },
            $text
        );

        // Protect decimal numbers and currency
        $text = preg_replace_callback('/(\d+\.\d+|\$\d+\.\d+)/', function ($m) {
            return str_replace('.', '[DOT]', $m[0]);
        }, $text);

        // Protect abbreviations (case-insensitive)
        $abbrevPattern = '/\b(' . implode('|', array_map('preg_quote', $abbreviations)) . ')\./i';
        $text = preg_replace_callback($abbrevPattern, function ($m) {
            return str_replace('.', '[DOT]', $m[0]);
        }, $text);

        // Now split on sentence endings
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z]|$)/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Restore protected characters
        $sentences = array_map(function ($sentence) {
            return str_replace(['[DOT]', '[EXCL]', '[QUEST]'], ['.', '!', '?'], trim($sentence));
        }, $sentences);

        // Filter out empty sentences
        return array_filter($sentences, function ($s) {
            return !empty(trim($s));
        });
    }
}
