<?php

namespace Uniform\Guards;

use Kirby\Cms\App;

class SpamWordList
{
    /**
     * Load spam words from cache, files, and config
     */
    public function loadSpamWords(): array
    {
        $useCache = option('tearoom1.uniform-spam-words.wordListCache', true);
        $spamWords = [];

        if ($useCache) {
            $cache = App::instance()->cache('uniform-spam-words');
            $spamWords = $cache->get('wordlists');
        }

        if ($spamWords === null || !$useCache) {
            $spamWords = $this->loadWordListsFromFiles();

            if ($useCache) {
                $cache->set('wordlists', $spamWords, 1440);
            }
        }

        return $this->mergeConfigSpamWords($spamWords);
    }

    /**
     * Load word lists from built-in and custom files
     */
    private function loadWordListsFromFiles(): array
    {
        $spamWords = [];

        if (option('tearoom1.uniform-spam-words.useWordLists', true)) {
            foreach (glob(__DIR__ . '/lists/*.txt') as $file) {
                // Merge with + operator to preserve existing keys (later files override earlier ones)
                $spamWords = $this->getWords($file) + $spamWords;
            }
        }

        $customPaths = option('tearoom1.uniform-spam-words.wordListPaths', null);
        if ($customPaths !== null) {
            foreach ((array)$customPaths as $path) {
                // Custom paths override built-in lists for duplicate words
                $spamWords = $this->loadWordListFromPath($path) + $spamWords;
            }
        }

        return $spamWords;
    }

    /**
     * Merge spam words from config
     * Config words override weights from built-in/custom file lists
     */
    private function mergeConfigSpamWords(array $spamWords): array
    {
        $configWords = option('tearoom1.uniform-spam-words.spamWords', []);
        foreach ($configWords as $weight => $words) {
            foreach ((array)$words as $word) {
                // Overwrite existing weight if word already exists
                $spamWords[mb_strtolower(trim($word))] = $weight;
            }
        }
        return $spamWords;
    }

    /**
     * Load word list from a file or directory path
     */
    private function loadWordListFromPath(string $path): array
    {
        $words = [];

        if (is_file($path)) {
            // Single file - extract weight from filename _n.txt
            $words = $this->getWords($path);
        } elseif (is_dir($path)) {
            // Directory - load all .txt files (later files override earlier ones for duplicates)
            // remove trailing slash from path
            $path = rtrim($path, '/');
            foreach (glob($path . '/*.txt') as $file) {
                $words = $this->getWords($file) + $words;
            }
        }

        return $words;
    }

    /**
     * Calculate spam score based on word matches
     * Returns array with 'score' and 'matches' (word => [weight, count, subtotal])
     */
    public function calculateSpamScore(string $message): array
    {
        $spamCount = 0;
        $matchedWords = [];
        $spamWords = $this->loadSpamWords();

        foreach ($spamWords as $word => $weight) {
            $matches = preg_match_all('/\b' . preg_quote($word) . '\b/i', $message);
            if ($matches > 0) {
                $subtotal = $matches * $weight;
                $spamCount += $subtotal;
                $matchedWords[$word] = [
                    'weight' => $weight,
                    'count' => $matches,
                    'subtotal' => $subtotal,
                ];
            }
        }

        return [
            'score' => $spamCount,
            'matches' => $matchedWords,
        ];
    }

    /**
     * @param mixed $file
     * @param array $words
     * @return array
     */
    public function getWords(mixed $file): array
    {
        $weight = intval(substr(basename($file), -5, 1));
        $weight = $weight ?: 1;

        $words = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $word) {
            $words[mb_strtolower(trim($word))] = $weight;
        }
        return $words;
    }
}
