<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
use Uniform\Guards\SpamWordsGuard;

class SpamMessagesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $_POST['message'] = 'Hi';
    }

    public function testDefaultMessage()
    {
        $this->assertSame('Message contains too few words.', $this->rejection(['minWords' => 5]));
    }

    public function testCustomMessagesAsNestedArray()
    {
        $message = $this->rejection([
            'minWords' => 5,
            'msg' => ['too-few-words' => 'Le message contient trop peu de mots.'],
        ]);

        $this->assertSame('Le message contient trop peu de mots.', $message);
    }

    public function testCustomMessagesAsFlatKeys()
    {
        $message = $this->rejection([
            'minWords' => 5,
            'msg.too-few-words' => 'Le message contient trop peu de mots.',
        ]);

        $this->assertSame('Le message contient trop peu de mots.', $message);
    }

    public function testNestedArrayKeepsDefaultsForOtherMessages()
    {
        $message = $this->rejection([
            'minWords' => 5,
            'msg' => ['rejected' => 'Le message est rejeté.'],
        ]);

        $this->assertSame('Message contains too few words.', $message);
    }

    private function rejection(array $options): string
    {
        new \Kirby\Cms\App([
            'roots' => ['index' => __DIR__],
            'options' => ['tearoom1.uniform-spam-words' => $options],
        ]);

        try {
            (new SpamWordsGuard(new Form))->perform();
        } catch (PerformerException $e) {
            return $e->getMessage();
        }

        $this->fail('Expected the message to be rejected');
    }
}
