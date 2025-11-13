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

### How it works
1. Checks message length constraints
2. Checks word count constraints
3. Validates regex pattern (if configured)
4. Runs custom validator (if configured)
5. If addresses found (>= minAddresses), calculates spam score from keywords
6. Rejects if thresholds exceeded

## Configuration

The plugin comes with a few lists of words and phrases that are used to build a spam score. Thus the plugin should work out of the box.

You may change certain options in your global `config.php`


### Configuration Options

| Option | Type | Default | Description                                                                                                                                                                               |
|--------|------|---------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Validation Options** ||         |                                                                                                                                                                                           |
| `minLength` | int\|null | `null`  | Minimum character length required for the message. Set to `null` to disable.                                                                                                              |
| `maxLength` | int\|null | `null`  | Maximum character length allowed for the message. Set to `null` to disable.                                                                                                               |
| `minWords` | int\|null | `null`  | Minimum word count required for the message. Set to `null` to disable.                                                                                                                    |
| `maxWords` | int\|null | `null`  | Maximum word count allowed for the message. Set to `null` to disable.                                                                                                                     |
| `regexMatch` | string\|null | `null`  | Optional regex pattern that the message must match (e.g., `/^[a-zA-Z0-9\s]+$/`). Set to `null` to disable.                                                                                |
| `customValidator` | callable\|null | `null`  | Custom validation callback that receives the message and returns `true` (valid) or `false` (invalid). Set to `null` to disable.                                                           |
| **Spam Detection Options** ||         |                                                                                                                                                                                           |
| `minAddresses` | int | `1`     | Minimum number of addresses required to trigger spam word checking. Set to `0` to always check.                                                                                           |
| `addressThreshold` | int | `2`     | Number of addresses (links/emails) allowed before triggering spam check.                                                                                                                  |
| `addressesWeight` | int | `1`     | Weight multiplier for each address (link/email) found. Each address contributes `addressesWeight` points to the total spam score.                                                         |
| `spamThreshold` | int | `8`     | Spam score threshold for rejection. Higher values are more lenient.                                                                                                                       |
| `useWordLists` | bool | `true`  | Use built-in spam word lists. Set to `false` to disable built-in lists.                                                                                                                   |
| `wordListPaths` | string\|array\|null | `null`  | Custom paths to word list files or directories. Always additive (added to built-in lists if enabled). Can be a single path (string) or multiple paths (array).                            |
| `spamWords` | array | `[]`    | Custom spam words with weights. Format: `[weight => ['word1', 'word2']]`. Higher weight = stronger spam signal.                                                                           |
| **Other Options** ||         |                                                                                                                                                                                           |
| `enabled` | bool | `true`  | Enable or disable the plugin globally.                                                                                                                                                    |
| `fields` | array | `['message']` | Form fields to check for spam. Specify multiple fields to combine them for spam checking (e.g., `['message', 'subject', 'body']`).                                                        |
| `wordListCache` | bool | `true`  | Enable caching of word lists. Set to `false` to reload word lists on every request (useful for development).                                                                              |
| `silentReject` | bool | `false` | Reject spam without showing error messages (returns a space character).                                                                                                                   |
| `debug` | bool | `false` | Enable debug logging. Logs ALL validation attempts with reason, metrics, form data, checked fields, anonymized IP, and timestamp.                                                         |
| `debugLogFile` | string\|null | `null`  | Path to debug log file. Defaults to `uniform-spam-words.log` in the kirby logs directory (e.g. `site/logs/uniform-spam-words.log`).                                                       |
| `attachDebugInfo` | bool | `false` | Attach spam check data to Form object (accessible via `$form->data('spam_words_guard_info')`) for use in email templates. Be aware that by default all fields are sent in uniform emails. |


### Message Rejection & Spam Score
- A message is rejected if the spam score is higher than the threshold. Or if the number of addresses are higher than twice the `addressThreshold`.
- A messages is *soft* rejected (different message) if the number of addresses are higher than `addressThreshold` but the spam score is lower than the threshold.
- The spam score is calculated by adding the weights of all matched words.
Plus the number of addresses found times the `addressesWeight`.

### Common Use Cases

#### Full Configuration Example
```php
return [
    'tearoom1.uniform-spam-words' => [
        'enabled' => true, // enable the plugin, default true
        'minLength' => 10, // the minimum length of the message, default null (disabled)
        'maxLength' => 500, // the maximum length of the message, default null (disabled)
        'minWords' => 3, // the minimum number of words in the message, default null (disabled)
        'maxWords' => null, // the maximum number of words in the message, default null (disabled)
        'regexMatch' => null, // the regex pattern to match against the message, default null (disabled)
        'customValidator' => null, // custom validation callback, default null (disabled)
        'minAddresses' => 0, // the minimum number of addresses like links and emails that are needed to check for spam, default 1
        'addressThreshold' => 2, // the number of addresses like links and emails that are allowed, default 2
        'addressesWeight' => 2, // the weight multiplier for each address (link/email), default 1
        'spamThreshold' => 10, // the threshold for the spam score, default 8
        'useWordLists' => true, // Use the default word lists, default true
        'spamWords' => [ // define your own spam words, the key number defines the weight of the words
            1 => ['promotion', 'free'], // weight 1, increases spam likelihood only a little
            6 => ['seo', 'marketing'], // weight 6, increases spam likelihood a lot
            // Note: If a word exists in both built-in/file lists and spamWords config,
            // the config weight will override the file weight (no double counting)
        ],
        'silentReject' => false, // Reject spam without showing error messages (returns a space as error message), default false
        'debug' => true, // Enable debug logging, default false
        'debugLogFile' => 'site/logs/my_custom_logfile.log', // Path to debug log file, default null (uses Kirby's log directory)
    ],
];
```

#### Contact Form with Basic Spam Protection
```php
return [
    'tearoom1.uniform-spam-words' => [
        'minLength' => 10,
        'minWords' => 3,
        'addressThreshold' => 2,
        'spamThreshold' => 8,
    ],
];
```

#### Strict Comment System
```php
return [
    'tearoom1.uniform-spam-words' => [
        'minLength' => 20,
        'maxLength' => 1000,
        'minWords' => 5,
        'maxWords' => 200,
        'addressThreshold' => 1, // Only allow 1 link
        'spamThreshold' => 5, // Stricter threshold
    ],
];
```

#### Multi-Field Spam Checking
```php
return [
    'tearoom1.uniform-spam-words' => [
        'fields' => ['subject', 'message', 'company'], // Check multiple fields
        'spamThreshold' => 10,
    ],
];
```

#### Increase Weight for Links/Emails
```php
return [
    'tearoom1.uniform-spam-words' => [
        'addressesWeight' => 3, // Each link/email counts as 3 points instead of 1
        'spamThreshold' => 12, // Adjust threshold accordingly
    ],
];
```

#### Development Mode (No Caching)
```php
return [
    'tearoom1.uniform-spam-words' => [
        'wordListCache' => false, // Disable caching for testing
        'debug' => true,  // Enable detailed logging
        'debugLogFile' => '/path/to/spam-debug.log',
    ],
];
```

#### Custom Validation with Industry-Specific Terms
```php
return [
    'tearoom1.uniform-spam-words' => [
        'spamWords' => [
            3 => ['crypto', 'nft', 'investment'],
            5 => ['guaranteed', 'risk-free'],
            8 => ['get rich quick'],
        ],
        'customValidator' => function($message) {
            // Reject if message contains phone numbers
            return !preg_match('/\d{3}[-.\s]?\d{3}[-.\s]?\d{4}/', $message);
        },
    ],
];
```

### Debug Mode for Tuning
```php
return [
    'tearoom1.uniform-spam-words' => [
        'debug' => true,
        'debugLogFile' => '/path/to/spam-debug.log',
    ],
];
```

**Debug logging captures:**
- ✅ All rejections (regex, length, words, custom validator, spam detection)
- ✅ Successful validations (messages that pass all checks)
- ✅ Rejection reason and relevant metrics for each attempt
- ✅ **Form data** - All submitted form fields (truncated to 100 chars, excludes passwords/tokens)
- ✅ **Checked fields** - Which fields were combined for spam checking
- ✅ Anonymized IP address (GDPR compliant) and timestamp for tracking

**Example log entry:**
```json
{
    "status": "rejected",
    "reason": "spam_score",
    "checked_fields": ["message"],
    "message_length": 150,
    "word_count": 25,
    "address_count": 3,
    "addresses_weight": 1,
    "address_score": 3,
    "spam_score": 12,
    "total_score": 15,
    "matched_words": {
        "seo": {
            "weight": 6,
            "count": 2,
            "subtotal": 12
        }
    },
    "thresholds": {
        "spam": 8,
        "address": 2
    },
    "form_data": {
        "name": "John Doe",
        "email": "spam@example.com",
        "message": "Buy now! Amazing SEO services..."
    }
}
```

**IP Anonymization (GDPR Compliant):**
- IPv4: Last octet masked (e.g., `192.168.1.123` → `192.168.1.0`)
- IPv6: Last 4 segments masked (e.g., `2001:db8:85a3:8d3:1319:8a2e:370:7348` → `2001:db8:85a3:8d3:0000:0000:0000:0000`)

## Custom Word Lists

You can use your own spam word list files instead of or in addition to the built-in lists.

### File Format

Word list files should be plain text files (`.txt`) with:
- One term (single word or combination) per line
- Weight encoded in filename as `_n.txt` where `n` is the weight (1-9)
- Example: `custom_spam_5.txt` (weight 5)

**Example file content (`my_spam_words_7.txt`):**
```
casino
lottery
winner
prize
```

### Configuration Options

**Single file:**
```php
'tearoom1.uniform-spam-words' => [
    'wordListPaths' => '/path/to/my_spam_words_7.txt',
],
```

**Multiple files:**
```php
'tearoom1.uniform-spam-words' => [
    'wordListPaths' => [
        '/path/to/my_spam_words_5.txt',
        '/path/to/another_list_8.txt',
    ],
],
```

**Directory (loads all .txt files):**
```php
'tearoom1.uniform-spam-words' => [
    'wordListPaths' => '/path/to/my-spam-lists',
],
```

**Multiple directories:**
```php
'tearoom1.uniform-spam-words' => [
    'wordListPaths' => [
        '/path/to/spam-lists',
        '/path/to/more-lists',
    ],
],
```

### Combining with Built-in Lists

Custom word lists are **added to** the built-in lists (if enabled). The options work independently:

**Built-in + Custom (default):**
```php
'tearoom1.uniform-spam-words' => [
    'useWordLists' => true,  // Use built-in lists (default)
    'wordListPaths' => '/path/to/my-lists', // Add custom lists
],
```

### Notes

- Custom word lists are cached for 24 hours (same as built-in lists)
- Weight determines spam score contribution (higher = more spam-like)
- Words are case-insensitive
- Empty lines are skipped
- Files must be readable by the web server

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
      'custom-validation-failed' => 'Message failed custom validation.',
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
        'tearoom1.uniform-spam-words.msg.custom-validation-failed' => 'Message failed custom validation.',
    ],
];
```

## License

This plugin is licensed under the [MIT License](LICENSE)

## Credits

- Developed by Mathis Koblin

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://coff.ee/tearoom1)
