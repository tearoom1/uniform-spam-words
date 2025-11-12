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

You may change certain options in your `config.php`, e.g.:

```php
return [
    'tearoom1.uniform-spam-words' => [
        'enabled' => true, // enable the plugin, default true
        'minAddresses' => 0, // the minimum number of addresses like links and emails that are needed to check for spam, default 1
        'addressThreshold' => 2, // the number of addresses like links and emails that are allowed, default 2
        'spamThreshold' => 8, // the threshold for the spam score, default 8
        'regexMatch' => null, // the regex pattern to match against the message, default null (disabled)
        'minLength' => 10, // the minimum length of the message, default null (disabled)
        'maxLength' => 500, // the maximum length of the message, default null (disabled)
        'minWords' => 3, // the minimum number of words in the message, default null (disabled)
        'maxWords' => null, // the maximum number of words in the message, default null (disabled)
        'useWordLists' => true, // Use the default word lists, default true
        'spamWords' => [ // define your own spam words, the key number defines the weight of the words
            1 => ['promotion', 'free'], // weight 1, increases spam likelihood only a little
            6 => ['seo', 'marketing'], // weight 6, increases spam likelihood a lot
        ],
        'silentReject' => false, // Reject spam without showing error messages (returns a space as error message), default false
    ],
];
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| **Validation Options** ||||
| `regexMatch` | string\|null | `null` | Optional regex pattern that the message must match (e.g., `/^[a-zA-Z0-9\s]+$/`). Set to `null` to disable. |
| `minLength` | int\|null | `null` | Minimum character length required for the message. Set to `null` to disable. |
| `maxLength` | int\|null | `null` | Maximum character length allowed for the message. Set to `null` to disable. |
| `minWords` | int\|null | `null` | Minimum word count required for the message. Set to `null` to disable. |
| `maxWords` | int\|null | `null` | Maximum word count allowed for the message. Set to `null` to disable. |
| **Spam Detection Options** ||||
| `minAddresses` | int | `0` | Minimum number of addresses required to trigger spam word checking. Set to `0` to always check. |
| `addressThreshold` | int | `2` | Number of addresses (links/emails) allowed before triggering spam check. |
| `spamThreshold` | int | `8` | Spam score threshold for rejection. Higher values are more lenient. |
| `useWordLists` | bool | `true` | Use built-in spam word lists. Set to `false` to only use custom words. |
| `spamWords` | array | `[]` | Custom spam words with weights. Format: `[weight => ['word1', 'word2']]`. Higher weight = stronger spam signal. |
| **Other Options** ||||
| `enabled` | bool | `true` | Enable or disable the plugin globally. |
| `silentReject` | bool | `false` | Reject spam without showing error messages (returns a space character). |

**How it works:**
1. Validates regex pattern (if configured)
2. Checks message length constraints
3. Checks word count constraints
4. If addresses found (>= minAddresses), calculates spam score from keywords
5. Rejects if thresholds exceeded

### Custom Error Messages

**Single-language sites:** Define custom messages in `config.php`:

```php
'tearoom1.uniform-spam-words' => [
    'msg' => [
      'rejected' => 'Your custom spam rejection message',
      'soft-reject' => 'Your custom soft rejection message',
      'regex-mismatch' => 'Message does not match the required pattern.',
      'too-short' => 'Message is too short.',
      'too-long' => 'Message is too long.',
      'too-few-words' => 'Message contains too few words.',
      'too-many-words' => 'Message contains too many words.',
    ],
],
```

**Multi-language sites:** Define translations in your language files (`site/languages/*.php`):

```php
return [
    'code' => 'en',
    'name' => 'English',
    'translations' => [
        'tearoom1.uniform-spam-words.msg.rejected' => 'Your custom spam rejection message',
        'tearoom1.uniform-spam-words.msg.soft-reject' => 'Your custom soft rejection message',
        'tearoom1.uniform-spam-words.msg.regex-mismatch' => 'Message does not match the required pattern.',
        'tearoom1.uniform-spam-words.msg.too-short' => 'Message is too short.',
        'tearoom1.uniform-spam-words.msg.too-long' => 'Message is too long.',
        'tearoom1.uniform-spam-words.msg.too-few-words' => 'Message contains too few words.',
        'tearoom1.uniform-spam-words.msg.too-many-words' => 'Message contains too many words.',
    ],
];
```

## License

This plugin is licensed under the [MIT License](LICENSE)

## Credits

- Developed by Mathis Koblin

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://coff.ee/tearoom1)
