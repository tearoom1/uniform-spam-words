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

$pluginOptions = [];

// Load translations only if languages are enabled
if (Kirby::instance()->multilang()) {
    $pluginOptions['translations'] = [
        'en' => require_once __DIR__ . '/i18n/en.php',
        'de' => require_once __DIR__ . '/i18n/de.php',
    ];
}

Kirby::plugin('tearoom1/uniform-spam-words', $pluginOptions);
