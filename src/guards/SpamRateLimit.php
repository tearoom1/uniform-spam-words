<?php

namespace Uniform\Guards;

use Kirby\Cms\App;

/**
 * Limits how many messages one visitor can send within a time window.
 * Only messages that passed all checks count. Visitors are told apart by
 * IP and, optionally, by the email address they enter; both are stored as hashes.
 */
class SpamRateLimit
{
    private int $max;
    private int $minutes;
    private array $keys;

    public function __construct()
    {
        $this->max = (int)option('tearoom1.uniform-spam-words.rateLimit.max', 5);
        $this->minutes = max(1, (int)option('tearoom1.uniform-spam-words.rateLimit.minutes', 60));
        $this->keys = $this->keys();
    }

    /**
     * Off when disabled or when there is nothing to count by (e.g. CLI without IP)
     */
    public function isEnabled(): bool
    {
        return $this->max > 0 && $this->keys !== [];
    }

    /**
     * True when this visitor already sent the maximum within the window
     */
    public function isExceeded(): bool
    {
        return $this->isEnabled() && $this->count() >= $this->max;
    }

    /**
     * Messages this visitor sent within the window, by IP or email, whichever is higher
     */
    public function count(): int
    {
        $counts = array_map(fn($key) => $this->entry($key)['count'] ?? 0, $this->keys);

        return $counts === [] ? 0 : max($counts);
    }

    /**
     * Count one passed message for this visitor
     */
    public function hit(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        foreach ($this->keys as $key) {
            $entry = $this->entry($key) ?? ['count' => 0, 'until' => time() + $this->minutes * 60];
            $entry['count']++;

            $minutesLeft = (int)ceil(($entry['until'] - time()) / 60);
            $this->cache()->set($key, $entry, max(1, $minutesLeft));
        }
    }

    public function limitData(): array
    {
        return [
            'rate_limit' => [
                'max' => $this->max,
                'minutes' => $this->minutes,
            ],
        ];
    }

    /**
     * The entry for a key, or null when there is none or its window is over
     */
    private function entry(string $key): ?array
    {
        $entry = $this->cache()->get($key);

        if (!is_array($entry) || ($entry['until'] ?? 0) <= time()) {
            return null;
        }

        return $entry;
    }

    /**
     * One key per IPv4 address and per IPv6 /64 network, since IPv6
     * privacy addresses change within the same connection; with
     * rateLimit.byEmail also one per entered email address
     */
    private function keys(): array
    {
        $keys = [];

        $ip = App::instance()->visitor()->ip() ?? '';
        if ($ip !== '') {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ip = bin2hex(substr(inet_pton($ip), 0, 8)) . '::/64';
            }
            $keys[] = $this->hashKey('ip', $ip);
        }

        if (option('tearoom1.uniform-spam-words.rateLimit.byEmail', false)) {
            $field = option('tearoom1.uniform-spam-words.rateLimit.emailField', 'email');
            $email = mb_strtolower(trim((string)App::instance()->request()->body()->get($field)));
            if ($email !== '') {
                $keys[] = $this->hashKey('email', $email);
            }
        }

        return $keys;
    }

    private function hashKey(string $type, string $value): string
    {
        return 'rate-' . $type . '-' . hash('sha256', $value . '|' . App::instance()->root('index'));
    }

    private function cache(): \Kirby\Cache\Cache
    {
        return App::instance()->cache('tearoom1.uniform-spam-words');
    }
}
