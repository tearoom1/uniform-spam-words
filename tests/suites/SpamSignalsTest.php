<?php

namespace tests\guards;

load([
    'tests\\TestCase' => '../TestCase.php',
], __DIR__);

use tests\TestCase;
use Uniform\Exceptions\PerformerException;
use Uniform\Form;
use Uniform\Guards\SpamWordsGuard;

class SpamSignalsTest extends TestCase
{
    // A cold sales pitch that passed with a total score of 3 before
    private const SALES_PITCH = "I'm reaching out through your contact form for a reason. "
        . 'Our AI voice agent answers their questions and books the appointment. '
        . 'Test it yourself at https://leadsoftware.example. '
        . 'Our Basic plan needs no credit card. You get 14 days free. '
        . 'Not for you? Unsubscribe here: https://leadsoftware.example/unsubscribe.aspx?d=tearoom.one';

    private const GENUINE = 'Hello, we are a small yoga studio and need a new website with a booking page. '
        . 'Our current site is https://yoga-studio.example, could we talk next week?';

    public function testSalesPitchIsRejected()
    {
        $_POST['message'] = self::SALES_PITCH;

        $this->expectException(PerformerException::class);
        $this->perform();
    }

    public function testSignalsAloneRejectTheSalesPitch()
    {
        $_POST['message'] = self::SALES_PITCH;

        $this->expectException(PerformerException::class);
        $this->perform(['useWordLists' => false]);
    }

    public function testNewPhrasesAloneRejectTheSalesPitch()
    {
        $_POST['message'] = self::SALES_PITCH;

        $this->expectException(PerformerException::class);
        $this->perform(['signals.unsubscribeLink' => 0, 'signals.ownDomain' => 0]);
    }

    public function testGenuineEnquiryPasses()
    {
        $_POST['message'] = self::GENUINE;

        $info = $this->perform();

        $this->assertSame([], $info['matched_signals']);
    }

    public function testUnsubscribeLinkIsASignal()
    {
        $_POST['message'] = 'Please take me off the list, the link https://shop.example/newsletter/opt-out does not work.';

        $info = $this->perform();

        $this->assertSame(['unsubscribe_link' => 6], $info['matched_signals']);
    }

    public function testUnsubscribeWordWithoutLinkIsNoSignal()
    {
        $_POST['message'] = 'How do I unsubscribe from your newsletter? I signed up at https://shop.example';

        $info = $this->perform();

        $this->assertSame([], $info['matched_signals']);
    }

    public function testOwnDomainInForeignLinkIsASignal()
    {
        $_POST['message'] = 'We checked your site, see the report at https://audit.example/report?site=www.tearoom.one';

        $info = $this->perform();

        $this->assertSame(['own_domain_in_link' => 6], $info['matched_signals']);
    }

    public function testEncodedOwnDomainIsASignal()
    {
        $_POST['message'] = 'Your report: https://audit.example/r?u=https%3A%2F%2Ftearoom.one%2F';

        $info = $this->perform();

        $this->assertSame(['own_domain_in_link' => 6], $info['matched_signals']);
    }

    public function testLinkToOwnSiteIsNoSignal()
    {
        $_POST['message'] = 'The form on https://www.tearoom.one/contact?from=tearoom.one shows an error on my phone.';

        $info = $this->perform();

        $this->assertSame([], $info['matched_signals']);
    }

    public function testHostsOptionOverridesSiteUrl()
    {
        $_POST['message'] = 'See https://audit.example/report?site=my-shop.example for details.';

        $info = $this->perform(['signals.hosts' => ['my-shop.example']]);

        $this->assertSame(['own_domain_in_link' => 6], $info['matched_signals']);
    }

    public function testLocalSiteUrlHasNoOwnDomainSignal()
    {
        $_POST['message'] = 'See https://audit.example/report?site=localhost for details.';

        $info = $this->perform([], 'http://localhost');

        $this->assertSame([], $info['matched_signals']);
    }

    public function testWeightZeroDisablesSignals()
    {
        $_POST['message'] = 'Unsubscribe: https://leadsoftware.example/unsubscribe?d=tearoom.one';

        $info = $this->perform(['signals.unsubscribeLink' => 0, 'signals.ownDomain' => 0]);

        $this->assertSame(0, $info['signal_score']);
    }

    /**
     * Run the guard on a site at $url and return the attached debug info
     */
    private function perform(array $options = [], string $url = 'https://tearoom.one'): array
    {
        new \Kirby\Cms\App([
            'roots' => [
                'index' => __DIR__,
            ],
            'urls' => [
                'index' => $url,
            ],
            'options' => [
                'tearoom1.uniform-spam-words' => $options + ['attachDebugInfo' => true],
            ],
        ]);

        $form = new Form;
        (new SpamWordsGuard($form))->perform();

        return json_decode($form->data('spam_words_guard_info', '', false), true);
    }
}
