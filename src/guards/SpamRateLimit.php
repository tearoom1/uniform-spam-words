<?php

namespace Uniform\Guards;

use Kirby\Cms\App;

/**
 * Limits how many messages one visitor can send within a time window.
 * Only messages that passed all checks count; the IP is stored as a hash.
 */
class SpamRateLimit
{
    private int $max;
    private int $minutes;

    public function __construct()
    {
        $this->max = (int)option('tearoom1.uniform-spam-words.rateLimit.max', 5);
        $this->minutes = max(1, (int)option('tearoom1.uniform-spam-words.rateLimit.minutes', 60));
    }

    /**
     * Off when disabled or when the visitor has no IP to count by (e.g. CLI)
     */
    public function isEnabled(): bool
    {
        return $this->max > 0 && !empty(App::instance()->visitor()->ip());
    }

    /**
     * True when this visitor already sent the maximum within the window
     */
    public function isExceeded(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $entry = $this->entry();

        return $entry !== null && $entry['count'] >= $this->max;
    }

    /**
     * Count one passed message for this visitor
     */
    public function hit(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $entry = $this->entry() ?? ['count' => 0, 'until' => time() + $this->minutes * 60];
        $entry['count']++;

        $minutesLeft = (int)ceil(($entry['until'] - time()) / 60);
        $this->cache()->set($this->key(), $entry, max(1, $minutesLeft));
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
     * The visitor's entry, or null when there is none or its window is over
     */
    private function entry(): ?array
    {
        $entry = $this->cache()->get($this->key());

        if (!is_array($entry) || ($entry['until'] ?? 0) <= time()) {
            return null;
        }

        return $entry;
    }

    /**
     * One key per IPv4 address and per IPv6 /64 network, since IPv6
     * privacy addresses change within the same connection
     */
    private function key(): string
    {
        $ip = App::instance()->visitor()->ip() ?? '';

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            $ip = bin2hex(substr($packed, 0, 8)) . '::/64';
        }

        return 'rate-' . hash('sha256', $ip . '|' . App::instance()->root('index'));
    }

    private function cache(): \Kirby\Cache\Cache
    {
        return App::instance()->cache('tearoom1.uniform-spam-words');
    }
}
