<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use tests\TestCase;

/**
 * Every option group works flat ('ai.enabled') and nested ('ai' => ['enabled']),
 * and the defaults of the other keys in a group stay in place
 */
class OptionNotationTest extends TestCase
{
    public function testDefaults()
    {
        $this->boot([]);

        $this->assertSame(5, option('tearoom1.uniform-spam-words.rateLimit.max'));
        $this->assertSame(6, option('tearoom1.uniform-spam-words.signals.ownDomain'));
        $this->assertFalse(option('tearoom1.uniform-spam-words.ai.enabled'));
        $this->assertSame('Message is too short.', option('tearoom1.uniform-spam-words.msg.too-short'));
    }

    public function testFlatNotation()
    {
        $this->boot([
            'rateLimit.max' => 2,
            'signals.ownDomain' => 0,
            'ai.enabled' => true,
            'msg.too-short' => 'Trop court.',
        ]);

        $this->assertGroups();
    }

    public function testNestedNotation()
    {
        $this->boot([
            'rateLimit' => ['max' => 2],
            'signals' => ['ownDomain' => 0],
            'ai' => ['enabled' => true],
            'msg' => ['too-short' => 'Trop court.'],
        ]);

        $this->assertGroups();
    }

    private function assertGroups(): void
    {
        // the set values
        $this->assertSame(2, option('tearoom1.uniform-spam-words.rateLimit.max'));
        $this->assertSame(0, option('tearoom1.uniform-spam-words.signals.ownDomain'));
        $this->assertTrue(option('tearoom1.uniform-spam-words.ai.enabled'));
        $this->assertSame('Trop court.', option('tearoom1.uniform-spam-words.msg.too-short'));

        // their neighbours keep the defaults
        $this->assertSame(60, option('tearoom1.uniform-spam-words.rateLimit.minutes'));
        $this->assertSame(6, option('tearoom1.uniform-spam-words.signals.unsubscribeLink'));
        $this->assertSame('borderline', option('tearoom1.uniform-spam-words.ai.mode'));
        $this->assertSame('Message is too long.', option('tearoom1.uniform-spam-words.msg.too-long'));
    }

    private function boot(array $options): void
    {
        new \Kirby\Cms\App([
            'roots' => ['index' => __DIR__],
            'options' => ['tearoom1.uniform-spam-words' => $options],
        ]);
    }
}
