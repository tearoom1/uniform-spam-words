<?php

namespace Uniform\Guards;

use Kirby\Cms\App;
use Kirby\Toolkit\F;

class SpamLogger
{
    /**
     * Log debug information
     */
    public function log(string $status, string $message, array $data = [], ?string $reason = null): void
    {
        if (!option('tearoom1.uniform-spam-words.debug', false)) {
            return;
        }

        // Collect checked fields for debug logging
        $requestBody = App::instance()->request()->body();
        $fieldsToCheck = option('tearoom1.uniform-spam-words.fields', ['message']);
        $checkedFields = [];
        foreach ((array)$fieldsToCheck as $field) {
            if ($requestBody->get($field)) {
                $checkedFields[] = $field;
            }
        }

        // Map reason rejected to spam score
        $reason = match ($reason) {
            'rejected' => 'spam_score',
            'soft-reject' => 'address_count',
            default => $reason,
        };

        // Add message length, word count, and checked fields to log data
        $logData = [
                'status' => $status,
                'reason' => $reason,
                'checked_fields' => $checkedFields,
                'message_length' => mb_strlen($message),
                'word_count' => str_word_count($message),
            ] + $data;

        $logFile = option('tearoom1.uniform-spam-words.debugLogFile')
            ?? App::instance()->root('logs') . '/uniform-spam-words.log';

        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->anonymizeIp(App::instance()->visitor()->ip());
        $logEntry = "[{$timestamp}] IP: {$ip}\n" . json_encode($logData, JSON_PRETTY_PRINT) . "\n\n";

        F::append($logFile, $logEntry);
    }

    /**
     * Anonymize IP address for GDPR compliance
     */
    private function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: mask last octet
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: mask last 4 segments
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 4);
            return implode(':', $parts) . '::';
        }
        return $ip;
    }
}
