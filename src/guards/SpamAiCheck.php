<?php

namespace Uniform\Guards;

use Closure;
use Kirby\Http\Remote;
use Throwable;

/**
 * Asks a decision model (OpenRouter Decisions API, Jev by default) whether a message is spam.
 * Independent of Uniform, so it can be reused for other forms.
 */
class SpamAiCheck
{
    public const DEFAULT_ENDPOINT = 'https://openrouter.ai/api/alpha/decisions';
    public const DEFAULT_MODEL = 'typesafe/jev-1.14';

    /**
     * Replaces the HTTP call in tests: receives (url, options) and returns [status, body]
     */
    public static ?Closure $transport = null;

    public function __construct(private array $options)
    {
    }

    /**
     * Probability that the message is spam, between 0 and 1.
     * Returns null when the check is not configured or the request fails,
     * so the caller can fall back to the word score.
     *
     * @return array{probability: float|null, model: string, cost: float|null, error: string|null}
     */
    public function classify(string $message): array
    {
        $model = $this->options['model'] ?? self::DEFAULT_MODEL;
        $result = ['probability' => null, 'model' => $model, 'cost' => null, 'error' => null];

        $apiKey = $this->options['apiKey'] ?? null;
        if (empty($apiKey)) {
            $result['error'] = 'missing api key';
            return $result;
        }

        try {
            [$status, $body] = $this->send($this->options['endpoint'] ?? self::DEFAULT_ENDPOINT, [
                'method' => 'POST',
                'timeout' => $this->options['timeout'] ?? 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'data' => json_encode($this->payload($model, $message)),
            ]);
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $data = json_decode((string)$body, true);
        if ($status >= 400 || !is_array($data)) {
            $result['error'] = 'HTTP ' . $status . ': ' . ($data['error']['message'] ?? mb_substr((string)$body, 0, 200));
            return $result;
        }

        $probability = $data['answers']['spam']['noul'] ?? null;
        if (!is_numeric($probability)) {
            $result['error'] = 'unexpected response';
            return $result;
        }

        $result['probability'] = max(0.0, min(1.0, (float)$probability));
        $result['cost'] = isset($data['usage']['cost']) ? (float)$data['usage']['cost'] : null;

        return $result;
    }

    /**
     * Only the checked message and the optional site context are sent, never names or emails from other fields
     */
    public function payload(string $model, string $message): array
    {
        $state = ['message' => $message];
        if (!empty($this->options['context'])) {
            $state['form_context'] = $this->options['context'];
        }

        return [
            'model' => $model,
            'state' => $state,
            'questions' => [
                'spam' => [
                    'type' => 'noul',
                    'instructions' => 'Is this contact form message spam?',
                    'criteria' => [
                        'true' => 'Unsolicited advertising, SEO or marketing offers, scams, phishing, link dropping or automated junk.',
                        'false' => 'A genuine message from a person who wants to reach the site owner, even if it mentions prices, links or offers.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function send(string $url, array $options): array
    {
        if (self::$transport !== null) {
            return (self::$transport)($url, $options);
        }

        // Kirby's HTTP client doesn't throw on 4xx/5xx responses
        $response = Remote::request($url, $options);
        return [$response->code(), (string)$response->content()];
    }
}
