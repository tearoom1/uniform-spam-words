<?php

namespace tests\Guards;

load([
    'Uniform\\Guards\\SpamWordsGuard' => '../../src/guards/SpamWordsGuard.php',
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
use Uniform\Guards\SpamWordsGuard;

class SpamWordsGuardTest extends TestCase
{

    /**
     * @throws PerformerException
     */
    public function testNoSpam()
    {
        $_POST['message'] = 'This is a test trial message with http://example.com and test@example.com';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testAlmostSpam()
    {
        $_POST['message'] = 'Hi Admin, can I get a discount? I do not have much money and can\'t pay 100% at the moment. Could you help me?
        I would like to buy a ticket. Here is my email: myemail@test.com and my website: http://example.com. Please answer me.
        I am happy to send you more info. Also I have health issues and need a trial.';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testJustNotSpam()
    {
        $_POST['message'] = 'asdasd money www.help.com my@md.de ad freel 100% trial best spam protect and money buy.';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testJustSpam()
    {
        $_POST['message'] = 'asdasd money www.help.com my@md.de ad freel 100% trial best spam protect and money buy. SEO.';

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    /**
     * @throws PerformerException
     */
    public function testSpam()
    {
        $_POST['message'] = 'This is a test message with lots of money and click here, also win trial and SEO here.
        Ad is Winner and buy customer 100% free discount. Vist our website: http://example.com and check our SEO tool.';

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    /**
     * @throws PerformerException
     */
    public function testJustNoSpam()
    {
        $_POST['message'] = 'Promotion free mail@example.com https://www.example.com';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testNoAddressNoSpam()
    {
        $_POST['message'] = 'Seo seo promotion free';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testSeoNoSpam()
    {
        $_POST['message'] = 'Seo promotion www.example.com';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testSeoSpam()
    {
        $_POST['message'] = 'Seo seo www.example.com';

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    /**
     * @throws PerformerException
     */
    public function testSpamKey()
    {
        $_POST['message'] = 'Spam KEY www.example.com';

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    /**
     * @throws PerformerException
     */
    public function testAddressSpam()
    {
        $_POST['message'] = 'https://example.com
            , test@example.com
            , www.google.com
            , win@example-web.com';

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    /**
     * @return void
     * @throws PerformerException
     */
    public function perform(): void
    {
        $guard = new SpamWordsGuard(new Form, [
                'morja.spamWordsGuard.spamThreshold' => 8,
                'morja.spamWordsGuard.addressThreshold' => 2,
                'morja.spamWordsGuard.useWordLists' => true,
                'morja.spamWordsGuard.spamWords' => [
                    1 => ['promotion', 'free'],
                    6 => ['seo', 'marketing'],
                    9 => ['spam key'],
                ],
        ]);
        $guard->perform();
    }

}
