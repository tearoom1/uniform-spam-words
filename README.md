# Spam Words Guard for Kirby Uniform

This plugin implements a simple spam words guard for Martin Zurowietz' [`kirby-uniform`](https://github.com/mzur/kirby-uniform) plugin for Kirby.

## Getting started

Use one of the following methods to install & use `tearoom1/uniform-spam-words`:


### Git submodule

If you know your way around Git, you can download this plugin as a [submodule](https://github.com/blog/2104-working-with-submodules):

```text
git submodule add https://github.com/tearoom1/uniform-spam-words.git site/plugins/uniform-spam-words
```


### Composer

```text
composer require tearoom1/uniform-spam-words
```


### Clone or download

1. Clone or download this repository from github: https://github.com/tearoom1/uniform-spam-words.git
2. Unzip / Move the folder to `site/plugins`.


## Usage

### Controller

To use the plugin, you have to enable the guard by calling `spamWordsGuard()` on the `$form` object.

For more information, check out the `kirby-uniform` docs on its [magic methods](https://kirby-uniform.readthedocs.io/en/latest/guards/guards/#magic-methods):

```php
$form = new Form();

if ($kirby->request()->is('POST')) {
    # Call security
    $form->spamWordsGuard();

    # .. more code
}
```


### Configuration

The plugin comes with a few lists of words and phrases that are used to build a spam score. Thus the plugin should work out of the box.

You may change certain options in your `config.php` globally:

```php
return [
    'tearoom1.uniform-spam-words' => [
        'addressThreshold' => 2, // the number of addresses like links and emails that are allowed, default 2
        'spamThreshold' => 8, // the threshold for the spam score, default 8
        'useWordLists' => true, // Use the default word lists, default true
        'spamWords' => [ // define your own spam words, the key number defines the weight of the words
            1 => ['promotion', 'free'], // weight 1, increases spam likelihood only a little
            6 => ['seo', 'marketing'], // weight 6, increases spam likelihood a lot
        ],
        'silentReject' => false, // Reject spam without showing error messages (returns a space as error message), default false
        // Custom error messages for single-language sites
        'rejected' => 'Message rejected as spam.',
        'soft-reject' => 'Too many links or emails in the message body, please send an email instead.',
    ],
];
```

- The `addressThreshold` defines the number of addresses like links and emails that are allowed in the message. If the number of addresses exceeds this threshold, the form submission is blocked.
- If no addresses can be found, then the message is considered as safe and no spam words are checked.
- The spam score is calculated by counting the occurrences of spam keywords in the message. The score is increased by the weight of the keyword. If the score exceeds the `spamThreshold`, the form submission is blocked.
- Set `silentReject` to `true` to reject spam submissions without displaying any of the configured error messages. I does return a space character as error message though.

### Custom Error Messages

**Single-language sites:** Define custom messages in `config.php`:

```php
'tearoom1.uniform-spam-words' => [
    'rejected' => 'Your custom spam rejection message',
    'soft-reject' => 'Your custom soft rejection message',
],
```

**Multi-language sites:** Define translations in your language files (`site/languages/*.php`):

```php
return [
    'code' => 'en',
    'name' => 'English',
    'translations' => [
        'tearoom1.uniform-spam-words.rejected' => 'Your custom spam rejection message',
        'tearoom1.uniform-spam-words.soft-reject' => 'Your custom soft rejection message',
    ],
];
```

## License

This plugin is licensed under the [MIT License](LICENSE), but **using Kirby in production** requires you to [buy a license](https://getkirby.com/buy).
