<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
use Uniform\Guards\SpamAiCheck;
use Uniform\Guards\SpamWordsGuard;

class SpamAiCheckTest extends TestCase
{
    // Total score 6, just below the default threshold 8 (passes without AI)
    private const ALMOST_SPAM = 'asdasd money www.help.com my@md.de ad freel 100% trial best spam protect and money buy.';
    // Total score 9, just above the threshold (rejected without AI)
    private const JUST_SPAM = self::ALMOST_SPAM . ' Money money money.';
    // Total score 1, far below the threshold
    private const CLEAR_HAM = 'Hello, I would like to book a session next week. My website is http://example.com';

    /** @var list<array{url: string, options: array}> */
    private array $requests = [];

    protected function tearDown(): void
    {
        SpamAiCheck::$transport = null;
        parent::tearDown();
    }

    public function testOffByDefault()
    {
        $this->fakeAi(0.99);
        $_POST['message'] = self::ALMOST_SPAM;

        $this->performWithOptions([]);

        $this->assertCount(0, $this->requests);
    }

    public function testAiRejectsCloseCallBelowThreshold()
    {
        $this->fakeAi(0.95);
        $_POST['message'] = self::ALMOST_SPAM;

        $this->expectException(PerformerException::class);
        $this->performWithOptions($this->aiOptions());
    }

    public function testAiPassesCloseCallAboveThreshold()
    {
        $this->fakeAi(0.1);
        $_POST['message'] = self::JUST_SPAM;

        $this->performWithOptions($this->aiOptions());

        $this->assertCount(1, $this->requests);
    }

    public function testClearCasesDoNotCallAi()
    {
        $this->fakeAi(0.99);
        $_POST['message'] = self::CLEAR_HAM;

        $this->performWithOptions($this->aiOptions());

        $this->assertCount(0, $this->requests);
    }

    public function testTooManyAddressesStayRejectedWithoutAi()
    {
        $this->fakeAi(0.0);
        $_POST['message'] = 'a@b.de c@d.de e@f.de g@h.de i@j.de';

        try {
            $this->performWithOptions($this->aiOptions(['ai.mode' => 'always']));
            $this->fail('Expected rejection');
        } catch (PerformerException) {
            $this->assertCount(0, $this->requests);
        }
    }

    public function testFailedRequestFallsBackToWordScore()
    {
        SpamAiCheck::$transport = function (string $url, array $options) {
            $this->requests[] = compact('url', 'options');
            return [502, '{"error":{"code":502,"message":"Provider returned error"}}'];
        };

        // Below threshold: passes as before
        $_POST['message'] = self::ALMOST_SPAM;
        $this->performWithOptions($this->aiOptions());
        $this->assertCount(1, $this->requests);

        // Above threshold: rejected as before
        $_POST['message'] = self::JUST_SPAM;
        $this->expectException(PerformerException::class);
        $this->performWithOptions($this->aiOptions());
    }

    public function testTransportExceptionFallsBackToWordScore()
    {
        SpamAiCheck::$transport = fn () => throw new \RuntimeException('timeout');
        $_POST['message'] = self::ALMOST_SPAM;

        $this->performWithOptions($this->aiOptions());

        $this->assertTrue(true);
    }

    public function testMissingApiKeySkipsRequest()
    {
        $this->fakeAi(0.99);
        $_POST['message'] = self::ALMOST_SPAM;

        $this->performWithOptions($this->aiOptions(['ai.apiKey' => null]));

        $this->assertCount(0, $this->requests);
    }

    public function testAlwaysModeChecksMessagesWithoutAddresses()
    {
        $this->fakeAi(0.97);
        $_POST['message'] = 'Dear owner, we boost your Google ranking within days. Reply for a free audit.';

        $this->expectException(PerformerException::class);
        $this->performWithOptions($this->aiOptions(['ai.mode' => 'always']));
    }

    public function testCustomThreshold()
    {
        $this->fakeAi(0.7);
        $_POST['message'] = self::ALMOST_SPAM;

        $this->expectException(PerformerException::class);
        $this->performWithOptions($this->aiOptions(['ai.threshold' => 0.6]));
    }

    public function testRequestSendsOnlyMessageAndContext()
    {
        $this->fakeAi(0.1);
        $_POST['name'] = 'Jane Doe';
        $_POST['email'] = 'jane@example.com';
        $_POST['message'] = self::ALMOST_SPAM;

        $this->performWithOptions($this->aiOptions([
            'ai.context' => 'Contact form of a yoga studio',
            'ai.model' => 'cloudflare/clef-flash',
        ]));

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $payload = json_decode($request['options']['data'], true);

        $this->assertSame(SpamAiCheck::DEFAULT_ENDPOINT, $request['url']);
        $this->assertSame('POST', $request['options']['method']);
        $this->assertSame('Bearer test-key', $request['options']['headers']['Authorization']);
        $this->assertSame('cloudflare/clef-flash', $payload['model']);
        $this->assertSame(['message' => self::ALMOST_SPAM, 'form_context' => 'Contact form of a yoga studio'], $payload['state']);
        $this->assertSame('noul', $payload['questions']['spam']['type']);
        $this->assertStringNotContainsString('jane@example.com', $request['options']['data']);
        $this->assertStringNotContainsString('Jane Doe', $request['options']['data']);
    }

    public function testClassifyReadsProbabilityAndCost()
    {
        $this->fakeAi(1.7, 0.00002);

        $result = (new SpamAiCheck(['apiKey' => 'test-key']))->classify('hello');

        $this->assertSame(1.0, $result['probability']);
        $this->assertSame(0.00002, $result['cost']);
        $this->assertSame(SpamAiCheck::DEFAULT_MODEL, $result['model']);
        $this->assertNull($result['error']);
    }

    public function testClassifyRejectsUnexpectedResponse()
    {
        SpamAiCheck::$transport = fn () => [200, '{"answers":{}}'];

        $result = (new SpamAiCheck(['apiKey' => 'test-key']))->classify('hello');

        $this->assertNull($result['probability']);
        $this->assertSame('unexpected response', $result['error']);
    }

    private function fakeAi(float $probability, float $cost = 0.00001): void
    {
        SpamAiCheck::$transport = function (string $url, array $options) use ($probability, $cost) {
            $this->requests[] = compact('url', 'options');
            return [200, json_encode([
                'answers' => ['spam' => ['type' => 'noul', 'noul' => $probability]],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 10, 'cost' => $cost],
            ])];
        };
    }

    private function aiOptions(array $overrides = []): array
    {
        return $overrides + [
            'ai.enabled' => true,
            'ai.apiKey' => 'test-key',
        ];
    }

    /**
     * @throws PerformerException
     */
    private function performWithOptions(array $options): void
    {
        new \Kirby\Cms\App([
            'roots' => [
                'index' => __DIR__,
            ],
            'options' => [
                'tearoom1.uniform-spam-words' => $options,
            ],
        ]);

        (new SpamWordsGuard(new Form))->perform();
    }
}
