<?php

namespace Uniform\Guards;

use Kirby\Cms\App;
use Uniform\Exceptions\PerformerException;
use Kirby\Toolkit\F;

class SpamWordsGuard extends Guard
{
    /**
     * @throws PerformerException
     */
    public function perform()
    {
        if (!option('tearoom1.uniform-spam-words.enabled', true)) {
            return;
        }

        $message = App::instance()->request()->body()->get('message');

        // Check regex pattern match
        $regexMatch = option('tearoom1.uniform-spam-words.regexMatch', null);
        if ($regexMatch !== null && !preg_match($regexMatch, $message)) {
            $this->reject($this->getMessage('regex-mismatch'));
        }

        // Check message length
        $minLength = option('tearoom1.uniform-spam-words.minLength', null);
        $maxLength = option('tearoom1.uniform-spam-words.maxLength', null);
        $messageLength = mb_strlen($message);
        if ($minLength !== null && $messageLength < $minLength) {
            $this->reject($this->getMessage('too-short'));
        }
        if ($maxLength !== null && $messageLength > $maxLength) {
            $this->reject($this->getMessage('too-long'));
        }

        // Check word count
        $minWords = option('tearoom1.uniform-spam-words.minWords', null);
        $maxWords = option('tearoom1.uniform-spam-words.maxWords', null);
        $wordCount = str_word_count($message);
        if ($minWords !== null && $wordCount < $minWords) {
            $this->reject($this->getMessage('too-few-words'));
        }
        if ($maxWords !== null && $wordCount > $maxWords) {
            $this->reject($this->getMessage('too-many-words'));
        }

        // Run custom validator if provided
        $customValidator = option('tearoom1.uniform-spam-words.customValidator', null);
        if ($customValidator !== null && is_callable($customValidator)) {
            $result = $customValidator($message);
            if ($result === false) {
                $this->reject($this->getMessage('custom-validation-failed'));
            }
        }

        // count occurrences of links in message
        // also count emails and www
        $emailCount = preg_match_all('%\w+@\w+\.\w+%i', $message);
        $linkCount = preg_match_all('%\b(https?://[^\s<>]*|www\.\w+\.\w+)%is', $message);
        $addressCount = $linkCount + $emailCount;

        if ($addressCount < option('tearoom1.uniform-spam-words.minAddresses', 1)) {
            return; // no spam
        }

        $spamWords = [];

        if (option('tearoom1.uniform-spam-words.useWordLists', true)) {
            // Try to load from cache first
            $cache = App::instance()->cache('uniform-spam-words');
            $spamWords = $cache->get('wordlists');

            if ($spamWords === null) {
                // Load spam words from all files in directory lists
                $spamWords = [];
                foreach (glob(__DIR__ . '/lists/*.txt') as $file) {
                    // extract number from file name _n.txt
                    $weight = intval(substr(basename($file), -5, 1));
                    $words = file($file, FILE_IGNORE_NEW_LINES);
                    foreach ($words as $word) {
                        $spamWords[strtolower($word)] = $weight;
                    }
                }
                // Cache for 24 hours
                $cache->set('wordlists', $spamWords, 1440);
            }
        }

        // load spam words from config
        $spamWordsMap = option('tearoom1.uniform-spam-words.spamWords', []);
        foreach ($spamWordsMap as $weight => $words) {
            foreach ($words as $word) {
                $spamWords[strtolower($word)] = $weight;
            }
        }

        //        $matches = [];
        $spamCount = 0;
        foreach ($spamWords as $word => $weight) {
            $preg_match_all = preg_match_all('/\b' . preg_quote($word) . '\b/i', $message);
            if ($preg_match_all === 0) {
                continue;
            }
            $spamCount += $preg_match_all * $weight;
            //            $matches[$word] = $weight;
        }

        $spamThreshold = option('tearoom1.uniform-spam-words.spamThreshold', 8);
        $addressThreshold = option('tearoom1.uniform-spam-words.addressThreshold', 2);

        $totalScore = $addressCount + $spamCount;
        $isSpam = $addressCount > $addressThreshold * 2 || $totalScore > $spamThreshold;
        $isSoftReject = !$isSpam && $addressCount > $addressThreshold;

        // Debug logging
        if (option('tearoom1.uniform-spam-words.debug', false)) {
            $this->logDebug([
                'message_length' => $messageLength,
                'word_count' => $wordCount,
                'address_count' => $addressCount,
                'spam_score' => $spamCount,
                'total_score' => $totalScore,
                'is_spam' => $isSpam,
                'is_soft_reject' => $isSoftReject,
                'thresholds' => [
                    'spam' => $spamThreshold,
                    'address' => $addressThreshold,
                ],
            ]);
        }

        if ($isSpam) {
            $this->reject($this->getMessage('rejected'));
        } else if ($isSoftReject) {
            $this->reject($this->getMessage('soft-reject'));
        }
    }

    /**
     * Get translated message with fallback support for non-language sites
     */
    private function getMessage(string $key): string
    {
        $silentReject = option('tearoom1.uniform-spam-words.silentReject', false);
        if ($silentReject) {
            return ' ';
        }

        // Try to get translation from Kirby
        $message = t('tearoom1.uniform-spam-words.msg.' . $key);
        // If translation exists and is not null or the key itself, return it
        if (!empty($message)) {
            return $message;
        }

        // try without msg. prefix for backwards compatibility
        $message = t('tearoom1.uniform-spam-words.' . $key);
        if (!empty($message)) {
            return $message;
        }

        // Fallback messages for single-language sites
        $fallbacks = [
            'rejected' => option('tearoom1.uniform-spam-words.msg.rejected', 'Message rejected as spam.'),
            'soft-reject' => option('tearoom1.uniform-spam-words.msg.soft-reject', 'Too many links or emails in the message body, please send an email instead.'),
            'regex-mismatch' => option('tearoom1.uniform-spam-words.msg.regex-mismatch', 'Message does not match the required pattern.'),
            'too-short' => option('tearoom1.uniform-spam-words.msg.too-short', 'Message is too short.'),
            'too-long' => option('tearoom1.uniform-spam-words.msg.too-long', 'Message is too long.'),
            'too-few-words' => option('tearoom1.uniform-spam-words.msg.too-few-words', 'Message contains too few words.'),
            'too-many-words' => option('tearoom1.uniform-spam-words.msg.too-many-words', 'Message contains too many words.'),
            'custom-validation-failed' => option('tearoom1.uniform-spam-words.msg.custom-validation-failed', 'Message failed custom validation.')
        ];

        return $fallbacks[$key] ?? '';
    }

    /**
     * Log debug information
     */
    private function logDebug(array $data): void
    {
        $logFile = option('tearoom1.uniform-spam-words.debugLogFile', null);
        if ($logFile === null) {
            // Default to Kirby's log directory
            $logFile = App::instance()->root('logs') . '/uniform-spam-words.log';
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = App::instance()->visitor()->ip();
        $logEntry = "[{$timestamp}] IP: {$ip}\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

        F::append($logFile, $logEntry);
    }
}
