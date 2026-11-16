<?php

namespace Uniform\Guards;

use Kirby\Cms\App;

/**
 * Signals that a message was sent from a template, not written by a person:
 * an unsubscribe link, or a link that carries the site's own domain.
 */
class SpamSignals
{
    private const URL_PATTERN = '%\b(https?://[^\s<>]*|www\.[^\s<>]+)%i';
    private const UNSUBSCRIBE_PATTERN = '%unsubscribe|opt[-_]?out|optout%i';

    /**
     * Score all signals found in the message
     * Returns array with 'score' and 'matches' (signal => weight)
     */
    public function calculateScore(string $message): array
    {
        $urls = $this->findUrls($message);
        $matches = [];

        $unsubscribeWeight = option('tearoom1.uniform-spam-words.signals.unsubscribeLink', 6);
        if ($unsubscribeWeight > 0 && $this->hasUnsubscribeLink($urls)) {
            $matches['unsubscribe_link'] = $unsubscribeWeight;
        }

        $ownDomainWeight = option('tearoom1.uniform-spam-words.signals.ownDomain', 6);
        if ($ownDomainWeight > 0 && $this->hasOwnDomainInLink($urls, $this->siteHosts())) {
            $matches['own_domain_in_link'] = $ownDomainWeight;
        }

        return [
            'score' => array_sum($matches),
            'matches' => $matches,
        ];
    }

    /**
     * A real visitor never puts an unsubscribe link into a contact form
     */
    private function hasUnsubscribeLink(array $urls): bool
    {
        foreach ($urls as $url) {
            if (preg_match(self::UNSUBSCRIBE_PATTERN, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A link to another site that carries this site's domain, e.g.
     * https://example.com/unsubscribe?d=my-site.com, was filled in per recipient
     */
    private function hasOwnDomainInLink(array $urls, array $hosts): bool
    {
        if ($hosts === []) {
            return false;
        }

        foreach ($urls as $url) {
            $linkHost = $this->normalizeHost(parse_url($this->withScheme($url), PHP_URL_HOST) ?? '');
            if (in_array($linkHost, $hosts, true)) {
                continue;
            }

            $rest = strtolower(rawurldecode(preg_replace('%^(https?://)?[^/?#]*%i', '', $url)));
            foreach ($hosts as $host) {
                if (str_contains($rest, $host)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Hosts of this site: the signals.hosts option, otherwise the host of the site URL
     */
    private function siteHosts(): array
    {
        $hosts = option('tearoom1.uniform-spam-words.signals.hosts');
        if ($hosts === null) {
            $hosts = [parse_url(App::instance()->url('index') ?? '', PHP_URL_HOST) ?? ''];
        }

        $hosts = array_map(fn($host) => $this->normalizeHost((string)$host), (array)$hosts);

        // Local hosts without a dot would match far too much
        return array_values(array_filter($hosts, fn($host) => str_contains($host, '.')));
    }

    private function findUrls(string $message): array
    {
        preg_match_all(self::URL_PATTERN, $message, $matches);
        return $matches[1];
    }

    private function withScheme(string $url): string
    {
        return preg_match('%^https?://%i', $url) ? $url : 'http://' . $url;
    }

    private function normalizeHost(string $host): string
    {
        return preg_replace('%^www\.%', '', strtolower($host));
    }
}
