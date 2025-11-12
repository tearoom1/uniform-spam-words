<?php

namespace Uniform\Guards;

use Kirby\Cms\App;
use Uniform\Exceptions\PerformerException;

class SpamWordsGuard extends Guard
{
    /**
     * @throws PerformerException
     */
    public function perform()
    {

        $message = App::instance()->request()->body()->get('message');

        // Check regex pattern match
        $regexMatch = option('tearoom1.uniform-spam-words.regexMatch', '');
        if (!empty($regexMatch) && !preg_match($regexMatch, $message)) {
            $this->reject($this->getMessage('regex-mismatch'));
        }

        // Check message length
        $minLength = option('tearoom1.uniform-spam-words.minLength', 0);
        $maxLength = option('tearoom1.uniform-spam-words.maxLength', 0);
        $messageLength = mb_strlen($message);
        if ($minLength > 0 && $messageLength < $minLength) {
            $this->reject($this->getMessage('too-short'));
        }
        if ($maxLength > 0 && $messageLength > $maxLength) {
            $this->reject($this->getMessage('too-long'));
        }

        // Check word count
        $minWords = option('tearoom1.uniform-spam-words.minWords', 0);
        $maxWords = option('tearoom1.uniform-spam-words.maxWords', 0);
        $wordCount = str_word_count($message);
        if ($minWords > 0 && $wordCount < $minWords) {
            $this->reject($this->getMessage('too-few-words'));
        }
        if ($maxWords > 0 && $wordCount > $maxWords) {
            $this->reject($this->getMessage('too-many-words'));
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
            // load spam words from all files in directory lists
            foreach (glob(__DIR__ . '/lists/*.txt') as $file) {
                // extract number from file name _n.txt
                $weight = intval(substr(basename($file), -5, 1));
                $words = file($file, FILE_IGNORE_NEW_LINES);
                foreach ($words as $word) {
                    $spamWords[strtolower($word)] = $weight;
                }
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

        if ($addressCount > $addressThreshold * 2 ||
            $addressCount + $spamCount > $spamThreshold) {
            $this->reject($this->getMessage('rejected'));
        } else if ($addressCount > $addressThreshold) {
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
            'too-many-words' => option('tearoom1.uniform-spam-words.msg.too-many-words', 'Message contains too many words.')
        ];

        return $fallbacks[$key] ?? '';
    }
}
