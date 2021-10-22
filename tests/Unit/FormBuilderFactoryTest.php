<?php

namespace Devolon\PaymentHighway\Tests\Unit;

use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Devolon\PaymentHighway\Tests\PaymentHighwayTestCase;
use Devolon\PaymentHighway\FormBuilderFactory;
use Hamcrest\Core\AnyOf;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;
use Solinor\PaymentHighway\FormBuilder;

class FormBuilderFactoryTest extends PaymentHighwayTestCase
{
    use WithFaker;

    public function testCreate()
    {
        // Arrange
        $transaction = Transaction::factory()->create();
        $successUrl = $this->faker->url;
        $failureUrl = $this->faker->url;
        $generateCallbackURLService = $this->mockGenerateCallbackURLService();
        $factory = $this->resolveFactory();

        $signatureKeyId = $this->faker->word;
        $signatureSecret = $this->faker->word;
        $account = $this->faker->word;
        $merchant = $this->faker->word;
        $baseUrl = $this->faker->url;

        config([
            'payment_highway.signature_key_id' => $signatureKeyId,
            'payment_highway.signature_secret' => $signatureSecret,
            'payment_highway.account' => $account,
            'payment_highway.merchant' => $merchant,
            'payment_highway.base_url' => $baseUrl,
        ]);

        $expectedFormBuilder = new FormBuilder(
            'POST',
            $signatureKeyId,
            $signatureSecret,
            $account,
            $merchant,
            $baseUrl,
            $successUrl,
            $failureUrl,
            $failureUrl,
            'EN',
        );

        // Expected
        $generateCallbackURLService
            ->shouldReceive('__invoke')
            ->with($transaction, AnyOf::anyOf([Transaction::STATUS_DONE, Transaction::STATUS_FAILED]))
            ->andReturnUsing(function ($tx, $status) use ($successUrl, $failureUrl) {
                return match ($status) {
                    Transaction::STATUS_DONE => $successUrl,
                    Transaction::STATUS_FAILED => $failureUrl,
                };
            });

        // Act
        $result = $factory->create($transaction);

        // Assert
        $this->assertEquals($expectedFormBuilder, $result);
    }

    private function mockGenerateCallbackURLService(): MockInterface
    {
        return $this->mock(GenerateCallbackURLService::class);
    }

    private function resolveFactory(): FormBuilderFactory
    {
        return resolve(FormBuilderFactory::class);
    }
}
