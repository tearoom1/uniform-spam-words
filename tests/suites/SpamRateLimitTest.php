<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use Kirby\Toolkit\Dir;
use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
use Uniform\Guards\SpamAiCheck;
use Uniform\Guards\SpamWordsGuard;

class SpamRateLimitTest extends TestCase
{
    private const MESSAGE = 'Hello, could we talk about a new website for our studio next week?';

    private string $cacheRoot;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheRoot = sys_get_temp_dir() . '/uniform-spam-words-' . uniqid();
        $_POST['message'] = self::MESSAGE;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    }

    protected function tearDown(): void
    {
        Dir::remove($this->cacheRoot);
        unset($_SERVER['REMOTE_ADDR']);
        SpamAiCheck::$transport = null;
        parent::tearDown();
    }

    public function testSixthMessageWithinTheHourIsRejected()
    {
        $this->sendTimes(5);

        try {
            $this->perform();
            $this->fail('Expected the sixth message to be rejected');
        } catch (PerformerException $e) {
            $this->assertStringContainsString('several messages', $e->getMessage());
        }
    }

    public function testRejectedMessagesDoNotCount()
    {
        $_POST['message'] = 'Hi';
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->perform(['minWords' => 5]);
            } catch (PerformerException) {
                // too few words, expected
            }
        }

        $_POST['message'] = self::MESSAGE;
        $this->sendTimes(5);

        $this->assertTrue(true);
    }

    public function testOtherVisitorsAreNotAffected()
    {
        $this->sendTimes(5);

        $_SERVER['REMOTE_ADDR'] = '203.0.113.8';
        $this->perform();

        $this->assertTrue(true);
    }

    public function testIpv6AddressesOfOneNetworkShareTheLimit()
    {
        $_SERVER['REMOTE_ADDR'] = '2a02:908:2816:cf80:fc15:3496:d29:784f';
        $this->sendTimes(4);

        $_SERVER['REMOTE_ADDR'] = '2a02:908:2816:cf80:85f0:af3f:fdd:e689';
        $this->sendTimes(1);

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    public function testCustomMaximum()
    {
        $this->sendTimes(2, ['rateLimit.max' => 2]);

        $this->expectException(PerformerException::class);
        $this->perform(['rateLimit.max' => 2]);
    }

    public function testZeroDisablesTheLimit()
    {
        $this->sendTimes(8, ['rateLimit.max' => 0]);

        $this->assertTrue(true);
    }

    public function testNoIpNoLimit()
    {
        unset($_SERVER['REMOTE_ADDR']);
        $this->sendTimes(8);

        $this->assertTrue(true);
    }

    public function testEmailLimitIsOffByDefault()
    {
        $_POST['email'] = 'info@example.org';
        for ($i = 1; $i <= 6; $i++) {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.' . $i;
            $this->perform();
        }

        $this->assertTrue(true);
    }

    public function testEmailLimitAcrossChangingIps()
    {
        $options = ['rateLimit.byEmail' => true];
        for ($i = 1; $i <= 5; $i++) {
            $_SERVER['REMOTE_ADDR'] = '203.0.113.' . $i;
            $_POST['email'] = $i % 2 ? 'Info@Example.org' : ' info@example.org ';
            $this->perform($options);
        }

        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';
        $this->expectException(PerformerException::class);
        $this->perform($options);
    }

    public function testAiIsNotAskedForFirstMessage()
    {
        $this->fakeAi(0.99);

        $this->perform($this->repeatOptions());

        $this->assertSame(0, $this->aiRequests);
    }

    public function testAiRejectsSecondMessageOfRepeatVisitor()
    {
        $this->fakeAi(0.99);
        $this->perform($this->repeatOptions());

        $_POST['message'] = 'PI ORDNUNG FORMAT WATCH LAMM';
        try {
            $this->perform($this->repeatOptions());
            $this->fail('Expected the AI to reject the second message');
        } catch (PerformerException) {
            $this->assertSame(1, $this->aiRequests);
        }
    }

    public function testAiPassesGenuineSecondMessage()
    {
        $this->fakeAi(0.1);

        $this->sendTimes(2, $this->repeatOptions());

        $this->assertSame(1, $this->aiRequests);
    }

    public function testAiRepeatIsOffByDefault()
    {
        $this->fakeAi(0.99);

        $this->sendTimes(2, ['ai.enabled' => true, 'ai.apiKey' => 'test-key']);

        $this->assertSame(0, $this->aiRequests);
    }

    public function testAiCannotPassClearSpamOfRepeatVisitor()
    {
        $this->fakeAi(0.0);
        $this->perform($this->repeatOptions());

        $_POST['message'] = 'Unsubscribe: https://leads.example/unsubscribe?d=x.example Make money, act now, click here!';
        $this->expectException(PerformerException::class);
        $this->perform($this->repeatOptions());
    }

    private int $aiRequests = 0;

    private function fakeAi(float $probability): void
    {
        SpamAiCheck::$transport = function () use ($probability) {
            $this->aiRequests++;
            return [200, json_encode([
                'answers' => ['spam' => ['type' => 'noul', 'noul' => $probability]],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 10, 'cost' => 0.00001],
            ])];
        };
    }

    private function repeatOptions(): array
    {
        return ['ai.enabled' => true, 'ai.apiKey' => 'test-key', 'ai.repeat' => true];
    }

    private function sendTimes(int $times, array $options = []): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->perform($options);
        }
    }

    private function perform(array $options = []): void
    {
        new \Kirby\Cms\App([
            'roots' => [
                'index' => __DIR__,
                'cache' => $this->cacheRoot,
            ],
            'options' => [
                'tearoom1.uniform-spam-words' => $options,
            ],
        ]);

        (new SpamWordsGuard(new Form))->perform();
    }
}
