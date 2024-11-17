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


Kirby::plugin('morja/uniform-spam-words', [
    'translations' => [
        'en' => require_once __DIR__ . '/i18n/en.php',
    ],
]);
