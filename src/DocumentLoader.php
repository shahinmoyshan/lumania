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

            // Detect document sections first for better boundary awareness
            $sections = $this->splitIntoSections($content);
            $chunkIndex = 0;
            $seenContent = []; // Track content hashes to prevent duplicates

            foreach ($sections as $section) {
                $sectionChunks = $this->chunkSection($section, $doc['filename'], $chunkIndex, $seenContent);
                foreach ($sectionChunks as $chunk) {
                    $chunks[] = $chunk;
                    $chunkIndex++;
                }
            }
        }

        return $chunks;
    }

    /**
     * Split content into logical sections (by headers, major breaks)
     */
    private function splitIntoSections($content)
    {
        // Split by section headers (numbered sections, markdown headers, or title-like lines)
        $sectionPattern = '/(?=^(?:#{1,6}\s+|\d+\.\s+[A-Z]|[A-Z][A-Za-z\s&]+(?:\n|$)(?=\n)))/m';
        $sections = preg_split($sectionPattern, $content, -1, PREG_SPLIT_NO_EMPTY);

        // If no sections found, treat entire content as one section
        if (count($sections) <= 1) {
            return [$content];
        }

        return array_filter(array_map('trim', $sections));
    }

    /**
     * Chunk a single section with proper overlap and deduplication
     */
    private function chunkSection($section, $filename, &$chunkIndex, &$seenContent)
    {
        $chunks = [];
        $paragraphs = $this->splitIntoParagraphs($section);

        $currentChunk = '';
        $currentSentences = [];
        $lastOverlapText = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            // Check if this paragraph is a section header - try to keep it with following content
            $isHeader = $this->isSectionHeader($paragraph);

            $sentences = $this->splitIntoSentences($paragraph);

            // If paragraph couldn't be split into sentences, treat whole paragraph as one unit
            if (empty($sentences)) {
                $sentences = [$paragraph];
            }

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (empty($sentence)) {
                    continue;
                }

                $testChunk = trim($currentChunk . ' ' . $sentence);
                $testLength = strlen($testChunk);

                // If adding this sentence would exceed chunk size
                if ($testLength > $this->chunkSize && !empty($currentChunk)) {
                    // Don't break if current chunk is too small (< 40% of target)
                    if (strlen($currentChunk) < $this->chunkSize * 0.4) {
                        $currentChunk = $testChunk;
                        $currentSentences[] = $sentence;
                        continue;
                    }

                    // Save current chunk if not duplicate
                    $chunkContent = trim($currentChunk);
                    $contentHash = md5($chunkContent);

                    if (!isset($seenContent[$contentHash]) && strlen($chunkContent) > 50) {
                        $seenContent[$contentHash] = true;
                        $chunks[] = [
                            'id' => md5($filename . '_' . $chunkIndex),
                            'source' => $filename,
                            'content' => $chunkContent,
                            'chunk_index' => $chunkIndex,
                            'char_count' => strlen($chunkContent),
                            'word_count' => str_word_count($chunkContent),
                            'has_structure' => $this->hasStructure($chunkContent)
                        ];
                        $chunkIndex++;
                    }

                    // Calculate overlap - use fewer sentences for less redundancy
                    $overlapSentences = $this->getOverlapSentences($currentSentences, min($this->chunkOverlap, 30));
                    $overlapText = implode(' ', $overlapSentences);

                    // Avoid using same overlap text repeatedly
                    if ($overlapText === $lastOverlapText) {
                        $overlapText = '';
                        $overlapSentences = [];
                    }
                    $lastOverlapText = $overlapText;

                    // Start new chunk with overlap
                    $currentChunk = trim($overlapText . ' ' . $sentence);
                    $currentSentences = array_merge($overlapSentences, [$sentence]);
                } else {
                    // Add sentence to current chunk
                    $currentChunk = $testChunk;
                    $currentSentences[] = $sentence;
                }
            }
        }

        // Add remaining chunk if substantial
        if (!empty(trim($currentChunk))) {
            $chunkContent = trim($currentChunk);
            $contentHash = md5($chunkContent);

            // Only add if not duplicate and has meaningful content
            if (!isset($seenContent[$contentHash]) && strlen($chunkContent) > 50) {
                $seenContent[$contentHash] = true;
                $chunks[] = [
                    'id' => md5($filename . '_' . $chunkIndex),
                    'source' => $filename,
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
     * Check if text appears to be a section header
     */
    private function isSectionHeader($text)
    {
        $text = trim($text);
        // Short text with title case or all caps, or starts with number followed by dot
        return strlen($text) < 100 && (
            preg_match('/^#{1,6}\s+/', $text) ||           // Markdown header
            preg_match('/^\d+\.\s+[A-Z]/', $text) ||       // Numbered section
            preg_match('/^[A-Z][A-Za-z\s&]+$/', $text) ||  // Title Case line
            preg_match('/^[A-Z\s&]+$/', $text)             // ALL CAPS line
        );
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
     * Limited to prevent excessive duplication
     */
    private function getOverlapSentences(array $sentences, $overlapWords)
    {
        if (empty($sentences)) {
            return [];
        }

        $overlapSentences = [];
        $wordCount = 0;
        $maxSentences = 2; // Limit overlap to max 2 sentences to prevent duplication
        $sentenceCount = 0;

        // Get sentences from the end until we reach overlap word count or max sentences
        for ($i = count($sentences) - 1; $i >= 0 && $sentenceCount < $maxSentences; $i--) {
            $sentence = $sentences[$i];
            $sentenceWords = str_word_count($sentence);

            // Don't include very short sentences as overlap (likely headers or fragments)
            if ($sentenceWords < 5 && !empty($overlapSentences)) {
                continue;
            }

            if ($wordCount + $sentenceWords <= $overlapWords || empty($overlapSentences)) {
                array_unshift($overlapSentences, $sentence);
                $wordCount += $sentenceWords;
                $sentenceCount++;
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
