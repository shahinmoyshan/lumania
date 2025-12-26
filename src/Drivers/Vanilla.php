<?php

namespace Lumina\Drivers;

use Lumina\Contracts\AIContract;
use function array_slice;

class Vanilla implements AIContract
{
    public function __construct(
        protected array $chunks
    ) {
    }

    public function ask(string $question, array $context = []): string
    {
        if (empty($context)) {
            return "I don't have information about that in the documents.";
        }

        // Just return the most relevant chunks
        $answer = "Based on the results:\n\n";

        foreach (array_slice($context, 0, 2) as $i => $result) {
            $content = $result['chunk']['content'];
            $source = $result['chunk']['source'];

            // Extract most relevant sentence
            $sentences = preg_split('/[.!?]+/', $content);
            $bestSentence = '';
            $maxScore = 0;

            foreach ($sentences as $sentence) {
                $score = $this->scoreSentence($sentence, $question);
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $bestSentence = trim($sentence);
                }
            }

            if (!empty($bestSentence)) {
                $answer .= "• " . $bestSentence . " (from: $source)\n";
            }
        }

        return $answer;
    }

    private function scoreSentence($sentence, $question)
    {
        $sentenceWords = str_word_count(strtolower($sentence), 1);
        $questionWords = str_word_count(strtolower($question), 1);

        $matches = count(array_intersect($sentenceWords, $questionWords));
        return $matches;
    }
}