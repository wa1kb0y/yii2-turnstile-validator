<?php

namespace easedevs\yii2\turnstile\tests;

use easedevs\yii2\turnstile\TurnstileInputValidator;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\DynamicModel;
use yii\helpers\Json;
use yii\httpclient\Client;
use yii\httpclient\Response;

class TurnstileInputValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        new \yii\web\Application([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'components' => [
                'request' => [
                    'cookieValidationKey' => 'asdfasdfasdfasdf',
                    'scriptFile' => __DIR__ . '/index.php',
                    'scriptUrl' => '/index.php',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        Yii::$app = null;
        parent::tearDown();
    }

    public function testAjaxRequestIsVerified()
    {
        $client = $this->client(['success' => false]);

        $this->assertTrue($this->validate('token', $client)->hasErrors('captcha'));
        $this->assertSame(1, $client->calls);
    }

    public function testValidToken()
    {
        $client = $this->client(['success' => true]);

        $this->assertFalse($this->validate('token', $client)->hasErrors());
        $this->assertSame(1, $client->calls);
    }

    public function testSkipOnAjaxIsOptIn()
    {
        $client = $this->client(['success' => false]);

        $this->assertFalse($this->validate('token', $client, ['skipOnAjax' => true])->hasErrors());
        $this->assertSame(0, $client->calls);
    }

    public function testMalformedResponseIsRejected()
    {
        $client = $this->client(['error-codes' => ['internal-error']]);

        $this->assertTrue($this->validate('token', $client)->hasErrors('captcha'));
    }

    public function testNonStringValueIsRejected()
    {
        $client = $this->client(['success' => true]);

        $this->assertTrue($this->validate(['token'], $client)->hasErrors('captcha'));
        $this->assertSame(0, $client->calls);
    }

    private function validate($value, Client $client, array $options = []): DynamicModel
    {
        $model = new DynamicModel(['captcha' => $value]);
        $model->addRule('captcha', TurnstileInputValidator::class, array_merge([
            'secret' => 'test-secret',
            'siteVerifyUrl' => 'https://turnstile.test/siteverify',
            'httpClient' => $client,
        ], $options));
        $model->validate();
        return $model;
    }

    private function client(array $data): Client
    {
        return new class(['data' => $data]) extends Client {
            public array $data = [];
            public int $calls = 0;

            public function send($request): Response
            {
                $this->calls++;
                return $this->createResponse(Json::encode($this->data), [
                    'http-code' => '200',
                    'content-type' => 'application/json',
                ]);
            }
        };
    }
}
