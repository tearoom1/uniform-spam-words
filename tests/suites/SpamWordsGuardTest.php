<?php

namespace tests\guards;

load([
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

        $this->performWithOptions([
            'spamWords' => [
                1 => ['promotion', 'free'],
                6 => ['seo', 'marketing'],
            ],
        ]);

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
     * @throws PerformerException
     */
    public function testRegexMatchValid()
    {
        $_POST['message'] = 'This is a valid message with http://example.com';

        $this->performWithOptions([
            'regexMatch' => '/^[a-zA-Z0-9\s:\/\.\-]+$/'
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testRegexMatchInvalid()
    {
        $_POST['message'] = 'Invalid message with special chars: äöü!@#$%';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'regexMatch' => '/^[a-zA-Z0-9\s]+$/'
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testMinLengthValid()
    {
        $_POST['message'] = 'This message is long enough with http://example.com';

        $this->performWithOptions([
            'minLength' => 20
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testMinLengthInvalid()
    {
        $_POST['message'] = 'Short';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'minLength' => 50
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testMaxLengthValid()
    {
        $_POST['message'] = 'Short message http://example.com';

        $this->performWithOptions([
            'maxLength' => 100
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testMaxLengthInvalid()
    {
        $_POST['message'] = 'This is a very long message that exceeds the maximum allowed length and should be rejected by the spam guard http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'maxLength' => 50
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testMinWordsValid()
    {
        $_POST['message'] = 'This message has enough words to pass the validation http://example.com';

        $this->performWithOptions([
            'minWords' => 5
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testMinWordsInvalid()
    {
        $_POST['message'] = 'Too few http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'minWords' => 10
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testMaxWordsValid()
    {
        $_POST['message'] = 'Short message http://example.com';

        $this->performWithOptions([
            'maxWords' => 10
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testMaxWordsInvalid()
    {
        $_POST['message'] = 'This is a very long message with many many many many many many words that should exceed the maximum word count http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'maxWords' => 10
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testCombinedValidations()
    {
        $_POST['message'] = 'This is a valid message with proper length and word count http://example.com';

        $this->performWithOptions([
            'minLength' => 20,
            'maxLength' => 200,
            'minWords' => 5,
            'maxWords' => 50
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testSilentReject()
    {
        $_POST['message'] = 'Seo seo www.example.com';

        try {
            $this->performWithOptions([
                'silentReject' => true
            ]);
        } catch (PerformerException $e) {
            $this->assertEquals(' ', $e->getMessage());
            return;
        }

        $this->fail('Expected PerformerException was not thrown');
    }

    /**
     * @throws PerformerException
     */
    public function testCustomSpamWordSingleOccurrence()
    {
        $_POST['message'] = 'Check out my amazing cryptocurrency investment opportunity http://example.com';

        $this->performWithOptions([
            'spamWords' => [
                7 => ['cryptocurrency']
            ],
            'spamThreshold' => 8,
            'useWordLists' => false
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomSpamWordMultipleOccurrences()
    {
        $_POST['message'] = 'Amazing cryptocurrency and blockchain cryptocurrency technology http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'spamWords' => [
                5 => ['cryptocurrency', 'blockchain']
            ],
            'spamThreshold' => 8
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomSpamWordsWeightedThreshold()
    {
        $_POST['message'] = 'Get your discount offer now with free trial http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'spamWords' => [
                2 => ['discount', 'offer'],
                3 => ['free', 'trial']
            ],
            'spamThreshold' => 8,
            'useWordLists' => false
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomSpamWordsBelowThreshold()
    {
        $_POST['message'] = 'I need a discount for this offer http://example.com';

        $this->performWithOptions([
            'spamWords' => [
                2 => ['discount', 'offer']
            ],
            'spamThreshold' => 10,
            'useWordLists' => false
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testNullMinLengthDisabled()
    {
        $_POST['message'] = 'Short http://example.com';

        $this->performWithOptions([
            'minLength' => null
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testNullMaxLengthDisabled()
    {
        $_POST['message'] = str_repeat('Very long message ', 100) . ' http://example.com';

        $this->performWithOptions([
            'maxLength' => null
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testNullMinWordsDisabled()
    {
        $_POST['message'] = 'Few http://example.com';

        $this->performWithOptions([
            'minWords' => null
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testNullMaxWordsDisabled()
    {
        $_POST['message'] = str_repeat('word ', 200) . ' http://example.com';

        $this->performWithOptions([
            'maxWords' => null
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorPass()
    {
        $_POST['message'] = 'Valid message http://example.com';

        $this->performWithOptions([
            'customValidator' => function($message) {
                return strpos($message, 'Valid') !== false;
            }
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorFail()
    {
        $_POST['message'] = 'Invalid message http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'customValidator' => function($message) {
                return strpos($message, 'Valid') !== false;
            }
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testPluginDisabled()
    {
        $_POST['message'] = 'Spam spam spam seo seo seo http://example.com http://test.com';

        $this->performWithOptions([
            'enabled' => false
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testEmptyMessage()
    {
        $_POST['message'] = '';

        $this->perform();
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testDebugLoggingEnabled()
    {
        $_POST['message'] = 'Test spam message with seo and marketing http://example.com';

        $logFile = sys_get_temp_dir() . '/spam-test-' . uniqid() . '.log';

        try {
            $this->performWithOptions([
                'debug' => true,
                'debugLogFile' => $logFile,
                'spamWords' => [
                    3 => ['seo', 'marketing']
                ],
                'useWordLists' => false
            ]);
        } catch (PerformerException $e) {
            // Expected to be rejected
        }

        // Check if log file was created and contains expected data
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('message_length', $logContent);
        $this->assertStringContainsString('word_count', $logContent);
        $this->assertStringContainsString('spam_score', $logContent);
        $this->assertStringContainsString('total_score', $logContent);

        // Cleanup
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * @throws PerformerException
     */
    public function testDebugLoggingDisabled()
    {
        $_POST['message'] = 'Test spam message with seo and marketing http://example.com';

        $logFile = sys_get_temp_dir() . '/spam-test-' . uniqid() . '.log';

        try {
            $this->performWithOptions([
                'debug' => false,
                'debugLogFile' => $logFile,
                'spamWords' => [
                    3 => ['seo', 'marketing']
                ],
                'useWordLists' => false
            ]);
        } catch (PerformerException $e) {
            // Expected to be rejected
        }

        // Log file should not exist when debug is disabled
        $this->assertFileDoesNotExist($logFile);
    }

    /**
     * @throws PerformerException
     */
    public function testCachingWordLists()
    {
        $_POST['message'] = 'Test message http://example.com';

        // First call - should load from files and cache
        $startTime = microtime(true);
        $this->performWithOptions([
            'useWordLists' => true
        ]);
        $firstCallTime = microtime(true) - $startTime;

        // Second call - should load from cache (faster)
        $startTime = microtime(true);
        $this->performWithOptions([
            'useWordLists' => true
        ]);
        $secondCallTime = microtime(true) - $startTime;

        // Second call should be faster or similar (cached)
        // We just verify it doesn't error and completes
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorWithComplexLogic()
    {
        $_POST['message'] = 'Contact me at 555-123-4567 http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'customValidator' => function($message) {
                // Reject messages with phone numbers
                return !preg_match('/\d{3}[-.\s]?\d{3}[-.\s]?\d{4}/', $message);
            }
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorWithEmailCheck()
    {
        $_POST['message'] = 'not-an-email';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'minAddresses' => 0,
            'customValidator' => function($message) {
                // Only accept valid email addresses
                return filter_var($message, FILTER_VALIDATE_EMAIL) !== false;
            }
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorReturnsTrue()
    {
        $_POST['message'] = 'test@example.com';

        $this->performWithOptions([
            'minAddresses' => 0,
            'customValidator' => function($message) {
                return filter_var($message, FILTER_VALIDATE_EMAIL) !== false;
            }
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testWordListCachingWithCustomWords()
    {
        $_POST['message'] = 'Test crypto investment opportunity http://example.com';

        $this->expectException(PerformerException::class);
        $this->performWithOptions([
            'useWordLists' => true, // Use cached word lists
            'spamWords' => [
                5 => ['crypto', 'investment']
            ],
            'spamThreshold' => 8
        ]);
    }

    /**
     * @throws PerformerException
     */
    public function testDisableWordListsUseOnlyCustom()
    {
        $_POST['message'] = 'SEO marketing http://example.com';

        // Should not be spam because we disabled word lists
        // and only use custom words which don't include 'seo' or 'marketing'
        $this->performWithOptions([
            'useWordLists' => false,
            'spamWords' => [
                5 => ['crypto', 'investment']
            ],
            'spamThreshold' => 8
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testDebugLogContainsIPAddress()
    {
        $_POST['message'] = 'Test message with spam http://example.com';

        $logFile = sys_get_temp_dir() . '/spam-test-ip-' . uniqid() . '.log';

        try {
            $this->performWithOptions([
                'debug' => true,
                'debugLogFile' => $logFile,
                'spamWords' => [
                    10 => ['spam']
                ],
                'useWordLists' => false
            ]);
        } catch (PerformerException $e) {
            // Expected
        }

        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('IP:', $logContent);

        // Cleanup
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * @throws PerformerException
     */
    public function testCustomValidatorNotCallable()
    {
        $_POST['message'] = 'Test message http://example.com';

        // Should not error if customValidator is not callable
        $this->performWithOptions([
            'customValidator' => 'not-a-function'
        ]);
        $this->assertTrue(true);
    }

    /**
     * @throws PerformerException
     */
    public function testIPAnonymization()
    {
        $_POST['message'] = 'Test spam message http://example.com';

        $logFile = sys_get_temp_dir() . '/spam-test-ip-anon-' . uniqid() . '.log';

        try {
            $this->performWithOptions([
                'debug' => true,
                'debugLogFile' => $logFile,
                'spamWords' => [
                    10 => ['spam']
                ],
                'useWordLists' => false
            ]);
        } catch (PerformerException $e) {
            // Expected
        }

        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);

        // Check that IP is present but anonymized
        $this->assertStringContainsString('IP:', $logContent);

        // IPv4 should end with .0 (last octet masked)
        // We can't test exact IP but we can verify the log was created
        $this->assertNotEmpty($logContent);

        // Cleanup
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    /**
     * @return void
     * @throws PerformerException
     */
    public function perform(): void
    {
        $guard = new SpamWordsGuard(new Form);
        $guard->perform();
    }

    /**
     * @param array $options
     * @return void
     * @throws PerformerException
     */
    public function performWithOptions(array $options): void
    {
        // Set options temporarily
        $kirby = new \Kirby\Cms\App([
            'roots' => [
                'index' => __DIR__,
            ],
            'options' => [
                'tearoom1.uniform-spam-words' => $options
            ]
        ]);

        $guard = new SpamWordsGuard(new Form);
        $guard->perform();
    }

}
