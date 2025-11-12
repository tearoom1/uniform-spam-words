<?php

/**
 * 'SpamWordsGuard' - Simple spam guard for 'mzur/kirby-uniform' & Kirby
 *
 * @package   Kirby CMS
 * @author    Mathis Koblin
 * @version   1.0.0
 * @license   MIT
 */

load([
    'Uniform\\Guards\\SpamWordsGuard' => 'src/guards/SpamWordsGuard.php'
], __DIR__);


use Kirby\Cms\App as Kirby;

// add default options
$pluginOptions = [
    'enabled' => true, // enable the plugin, default true
    'addressThreshold' => 2, // the number of addresses like links and emails that are allowed, default 2
    'spamThreshold' => 8, // the threshold for the spam score, default 8
    'minAddresses' => 1, // the minimum number of addresses like links and emails that are needed to check for spam, default 1
    'regexMatch' => '', // the regex pattern to match against the message, default empty string
    'minLength' => 0, // the minimum length of the message, default 0
    'maxLength' => 0, // the maximum length of the message, default 0
    'minWords' => 0, // the minimum number of words in the message, default 0
    'maxWords' => 0, // the maximum number of words in the message, default 0
    'useWordLists' => true, // Use the default word lists, default true
    'spamWords' => [ // define your own spam words, the key number defines the weight of the words
        1 => ['promotion', 'free'], // weight 1, increases spam likelihood only a little
        6 => ['seo', 'marketing'], // weight 6, increases spam likelihood a lot
    ],
    'silentReject' => false, // Reject spam without showing error messages (returns a space as error message), default false
    // Custom error messages for single-language sites
    'msg.rejected' => 'Message rejected as spam.',
    'msg.soft-reject' => 'Too many links or emails in the message body, please send an email instead.',
    'msg.regex-mismatch' => 'Message does not match the required pattern.',
    'msg.too-short' => 'Message is too short.',
    'msg.too-long' => 'Message is too long.',
    'msg.too-few-words' => 'Message contains too few words.',
    'msg.too-many-words' => 'Message contains too many words.',
];

// Load translations only if languages are enabled
if (Kirby::instance()->multilang()) {
    $pluginOptions['translations'] = [
        'en' => require_once __DIR__ . '/i18n/en.php',
        'de' => require_once __DIR__ . '/i18n/de.php',
    ];
}

Kirby::plugin('tearoom1/uniform-spam-words', $pluginOptions);
