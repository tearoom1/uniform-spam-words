<?php

namespace Uniform\Guards;

use Kirby\Cms\App;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;

class SpamWordsGuard extends Guard
{
    private SpamLogger $logger;
    private SpamWordList $wordList;

    public function __construct(Form $form, array $options = [])
    {
        parent::__construct($form, $options);
        $this->logger = new SpamLogger();
        $this->wordList = new SpamWordList();
    }

    /**
     * @throws PerformerException
     */
    public function perform()
    {
        if (!option('tearoom1.uniform-spam-words.enabled', true)) {
            return;
        }

        $requestBody = App::instance()->request()->body();
        $message = $this->collectMessage($requestBody);
        $formData = $this->collectFormData($requestBody);

        $this->validateLength($message, $formData);
        $this->validateWordCount($message, $formData);
        $this->validateRegex($message, $formData);
        $this->validateCustom($message, $formData);
        $this->validateSpam($message, $formData);
    }

    /**
     * Validate message length
     */
    private function validateLength(string $message, array $formData): void
    {
        $minLength = option('tearoom1.uniform-spam-words.minLength', null);
        $maxLength = option('tearoom1.uniform-spam-words.maxLength', null);

        if ($minLength === null && $maxLength === null) {
            return;
        }

        $messageLength = mb_strlen($message);

        if ($minLength !== null && $messageLength < $minLength) {
            $this->rejectWithLog('too-short', $message, [
                'min_length' => $minLength,
                'form_data' => $formData,
            ]);
        }

        if ($maxLength !== null && $messageLength > $maxLength) {
            $this->rejectWithLog('too-long', $message, [
                'max_length' => $maxLength,
                'form_data' => $formData,
            ]);
        }
    }

    /**
     * Validate word count
     */
    private function validateWordCount(string $message, array $formData): void
    {
        $minWords = option('tearoom1.uniform-spam-words.minWords', null);
        $maxWords = option('tearoom1.uniform-spam-words.maxWords', null);

        if ($minWords === null && $maxWords === null) {
            return;
        }

        $wordCount = str_word_count($message);

        if ($minWords !== null && $wordCount < $minWords) {
            $this->rejectWithLog('too-few-words', $message, [
                'min_words' => $minWords,
                'form_data' => $formData,
            ]);
        }

        if ($maxWords !== null && $wordCount > $maxWords) {
            $this->rejectWithLog('too-many-words', $message, [
                'max_words' => $maxWords,
                'form_data' => $formData,
            ]);
        }
    }

    /**
     * Validate regex pattern
     */
    private function validateRegex(string $message, array $formData): void
    {
        $regexMatch = option('tearoom1.uniform-spam-words.regexMatch', null);
        if ($regexMatch !== null && !preg_match($regexMatch, $message)) {
            $this->rejectWithLog('regex-mismatch', $message, [
                'form_data' => $formData,
            ]);
        }
    }

    /**
     * Validate with custom validator
     */
    private function validateCustom(string $message, array $formData): void
    {
        $customValidator = option('tearoom1.uniform-spam-words.customValidator', null);
        if ($customValidator !== null && is_callable($customValidator)) {
            if ($customValidator($message) === false) {
                $this->rejectWithLog('custom-validation-failed', $message, [
                    'form_data' => $formData,
                ]);
            }
        }
    }

    /**
     * Evaluate spam score and reject if necessary
     */
    private function validateSpam(string $message, array $formData): void
    {
        $addressCount = $this->countAddresses($message);
        if ($addressCount < option('tearoom1.uniform-spam-words.minAddresses', 1)) {
            return;
        }

        $spamThreshold = option('tearoom1.uniform-spam-words.spamThreshold', 8);
        $addressThreshold = option('tearoom1.uniform-spam-words.addressThreshold', 2);

        $spamCount = $this->wordList->calculateSpamScore($message);

        $totalScore = $addressCount + $spamCount;
        $isSpam = $addressCount > $addressThreshold * 2 || $totalScore > $spamThreshold;
        $isSoftReject = !$isSpam && $addressCount > $addressThreshold;

        $spamData = [
            'address_count' => $addressCount,
            'spam_score' => $spamCount,
            'total_score' => $totalScore,
            'thresholds' => [
                'spam' => $spamThreshold,
                'address' => $addressThreshold,
            ],
            'form_data' => $formData,
        ];

        if ($isSpam) {
            $this->rejectWithLog('rejected', $message, $spamData);
        } elseif ($isSoftReject) {
            $this->rejectWithLog('soft-reject', $message, $spamData);
        }

        $this->logger->log('passed', $message, $spamData);
    }


    /**
     * Count email and link addresses in message
     */
    private function countAddresses(string $message): int
    {
        $emailCount = preg_match_all('%\w+@\w+\.\w+%i', $message);
        $linkCount = preg_match_all('%\b(https?://[^\s<>]*|www\.\w+\.\w+)%is', $message);
        return $linkCount + $emailCount;
    }

    /**
     * Reject with optional debug logging
     */
    private function rejectWithLog(string $reason, string $message, array $data = []): void
    {
        $this->logger->log('rejected', $message, $data, $reason);
        $this->reject($this->getResultMessage($reason));
    }

    /**
     * Collect message from specified form fields
     */
    private function collectMessage($requestBody): string
    {
        $fieldsToCheck = option('tearoom1.uniform-spam-words.fields', ['message']);
        $messageParts = [];

        foreach ((array)$fieldsToCheck as $field) {
            $value = $requestBody->get($field);
            if ($value) {
                $messageParts[] = $value;
            }
        }

        return implode(' ', $messageParts);
    }

    /**
     * Collect form data for debug logging
     */
    private function collectFormData($requestBody): array
    {
        if (!option('tearoom1.uniform-spam-words.debug', false)) {
            return [];
        }

        $formData = [];
        $excludePatterns = ['password', 'token', 'captcha'];
        foreach ($requestBody->toArray() as $key => $value) {
            $exclude = false;
            foreach ($excludePatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    $exclude = true;
                    break;
                }
            }
            if (!$exclude) {
                $formData[$key] = is_string($value) ? mb_substr($value, 0, 100) : $value;
            }
        }

        return $formData;
    }

    /**
     * Get translated message with fallback support for non-language sites
     */
    private function getResultMessage(string $key): string
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

}
