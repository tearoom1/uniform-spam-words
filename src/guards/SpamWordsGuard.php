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
        $addressThreshold = $this->option('morja.spamWordsGuard.addressThreshold', 2);
        $spamThreshold = $this->option('morja.spamWordsGuard.spamThreshold', 8);

        $message = App::instance()->request()->body()->get('message');

        // count occurrences of links in message
        // also count emails and www
        $emailCount = preg_match_all('%\w+@\w+\.\w+%i', $message);
        $linkCount = preg_match_all('%\b(https?://[^\s<>]*|www\.\w+\.\w+)%is', $message);
        $addressCount = $linkCount + $emailCount;

        if ($addressCount === 0) {
            return; // no spam
        }


        $spamWords = [];

        if ($this->option('morja.spamWordsGuard.useWordLists', true)) {
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
        $spamWordsMap = $this->option('morja.spamWordsGuard.spamWords', []);
        foreach ($spamWordsMap as $weight => $words) {
            foreach ($words as $word) {
                $spamWords[strtolower($word)] = $weight;
            }
        }

        $spamCount = 0;
        foreach ($spamWords as $word => $weight) {
            $spamCount += preg_match_all('/\b' . $word . '\b/i', $message) * $weight;
        }

        if ($addressCount > $addressThreshold * 2) {
            $this->reject("Message rejected as spam");
        } else if ($addressCount + $spamCount > $spamThreshold) {
            $this->reject("Message rejected as spam");
        } else if ($addressCount > $addressThreshold) {
            $this->reject("Too many links or emails in the message body, please send an email instead");
        }
    }
}
