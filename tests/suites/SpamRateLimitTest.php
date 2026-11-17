<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use Kirby\Toolkit\Dir;
use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
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
