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
                $weight = intval(substr(basename($file), -5, 1));
                $words = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($words as $word) {
                    $spamWords[trim($word)] = $weight;
                }
            }
        }

        $customPaths = option('tearoom1.uniform-spam-words.wordListPaths', null);
        if ($customPaths !== null) {
            foreach ((array)$customPaths as $path) {
                $spamWords = array_merge($spamWords, $this->loadWordListFromPath($path));
            }
        }

        return $spamWords;
    }

    /**
     * Merge spam words from config
     */
    private function mergeConfigSpamWords(array $spamWords): array
    {
        $configWords = option('tearoom1.uniform-spam-words.spamWords', []);
        foreach ($configWords as $weight => $words) {
            foreach ((array)$words as $word) {
                $spamWords[trim($word)] = $weight;
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
            $weight = intval(substr(basename($path), -5, 1));
            $weight = $weight ?: 1;

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $word) {
                $words[trim($word)] = $weight;
            }
        } elseif (is_dir($path)) {
            // Directory - load all .txt files
            foreach (glob($path . '/*.txt') as $file) {
                $weight = intval(substr(basename($file), -5, 1));
                $weight = $weight ?: 1;

                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $word) {
                    $words[trim($word)] = $weight;
                }
            }
        }

        return $words;
    }

    /**
     * Calculate spam score based on word matches
     */
    public function calculateSpamScore(string $message): int
    {
        $spamCount = 0;
        $spamWords = $this->loadSpamWords();
        foreach ($spamWords as $word => $weight) {
            $matches = preg_match_all('/\b' . preg_quote($word) . '\b/i', $message);
            if ($matches > 0) {
                $spamCount += $matches * $weight;
            }
        }
        return $spamCount;
    }
}
