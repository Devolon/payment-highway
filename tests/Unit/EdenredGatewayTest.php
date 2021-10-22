<?php

namespace Devolon\PaymentHighway\Tests\Unit;

use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\PaymentGatewayDiscoveryService;
use Devolon\Payment\Services\SetGatewayResultService;
use Devolon\PaymentHighway\FormBuilderFactory;
use Devolon\PaymentHighway\EdenredGateway;
use Devolon\PaymentHighway\Tests\PaymentHighwayTestCase;
use Httpful\Response;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Mockery\MockInterface;
use Solinor\PaymentHighway\FormBuilder;
use Solinor\PaymentHighway\Model\Form;
use Solinor\PaymentHighway\PaymentApi;
use stdClass;

class EdenredGatewayTest extends PaymentHighwayTestCase
{
    use WithFaker;

    public function testGetName()
    {
        // Arrange
        $gateway = $this->resolveGateway();

        // Act
        $result = $gateway->getName();

        // Assert
        $this->assertEquals('edenred', $result);
    }

    public function testItRegisteredAsGateway()
    {
        // Arrange
        $paymentGatewayDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        // Act
        $result = $paymentGatewayDiscoveryService->get('edenred');

        // Assert
        $this->assertInstanceOf(EdenredGateway::class, $result);
        $this->assertInstanceOf(HasUpdateTransactionData::class, $result);
    }

    public function testPurchase()
    {
        // Arrange
        $formBuilder = $this->mockFormBuilder();
        $formBuilderFactory = $this->mockFormBuilderFactory();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();

        $formBaseURI = 'http://' . $this->faker->domainName . '/';
        $formPaymentURI = $this->faker->word;
        $expectedRedirectDTO = RedirectDTO::fromArray([
            'redirect_url' => "{$formBaseURI}{$formPaymentURI}",
            'redirect_method' => 'POST',
            'redirect_data' => [
                $this->faker->word => $this->faker->word,
            ],
        ]);
        $expectedPurchaseResultDTO = PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => $expectedRedirectDTO,
        ]);
        $paymentForm = new Form(
            $expectedRedirectDTO->redirect_method,
            $formBaseURI,
            $formPaymentURI,
            $expectedRedirectDTO->redirect_data
        );

        // Expect
        $formBuilderFactory
            ->shouldReceive('create')
            ->with($transaction)
            ->once()
            ->andReturn($formBuilder);

        $formBuilder
            ->shouldReceive('generatePaymentParameters')
            ->with(
                round($transaction->money_amount * 100),
                'EUR',
                $transaction->id,
                "Purchase transaction {$transaction->id}"
            )
            ->once()
            ->andReturn($paymentForm);

        // Act
        $result = $gateway->purchase($transaction);

        // Assert
        $this->assertEquals($expectedPurchaseResultDTO, $result);
    }

    public function testVerifySuccessfully()
    {
        // Arrange
        $formBuilder = $this->mockPaymentApi();
        $setGatewayResultService = $this->mockSetGatewayResultService();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();
        $sphTransactionId = $this->faker->uuid;
        $commitResponse = $this->mockResponse();

        // Expect
        $commitResponse->body = $this->successfulVerifyResponse();
        $commitResponse->code = 200;

        $formBuilder
            ->shouldReceive('commitFormTransaction')
            ->with($sphTransactionId, round($transaction->money_amount * 100), 'EUR')
            ->once()
            ->andReturn($commitResponse);

        $setGatewayResultService
            ->shouldReceive('__invoke')
            ->with($transaction, 'commit', $commitResponse->body)
            ->once();

        // Act
        $result = $gateway->verify($transaction, ['sph-transaction-id' => $sphTransactionId]);

        // Assert
        $this->assertTrue($result);
        $transaction->refresh();
    }

    /**
     * @dataProvider failedCommitData
     */
    public function testVerifyFailed(int $code, stdClass $body)
    {
        // Arrange
        $formBuilder = $this->mockPaymentApi();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();
        $sphTransactionId = $this->faker->uuid;
        $commitResponse = $this->mockResponse();

        // Expect
        $commitResponse->body = $body;
        $commitResponse->code = $code;

        $formBuilder
            ->shouldReceive('commitFormTransaction')
            ->with($sphTransactionId, round($transaction->money_amount * 100), 'EUR')
            ->once()
            ->andReturn($commitResponse);

        // Act
        $result = $gateway->verify($transaction, ['sph-transaction-id' => $sphTransactionId]);

        // Assert
        $this->assertFalse($result);
    }

    public function testUpdateTransactionDataRulesWithDoneStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [
            'sph-transaction-id' => [
                'required',
                'string'
            ]
        ];

        // Act
        $result = $gateway->updateTransactionDataRules('done');

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function testUpdateTransactionDataRulesWithFailedStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [];

        // Act
        $result = $gateway->updateTransactionDataRules('failed');

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function failedCommitData(): array
    {
        $notCommittedResponseBody = new stdClass();
        $notCommittedResponseBody->committed = false;

        return [
            'Not OK response code' => [
                400,
                $this->successfulVerifyResponse(),
            ],
            'OK response but committed key is not present' => [
                200,
                new stdClass(),
            ],
            'OK response but committed key is false' => [
                200,
                $notCommittedResponseBody,
            ],
        ];
    }

    private function resolveGateway(): EdenredGateway
    {
        return resolve(EdenredGateway::class);
    }

    private function resolvePaymentGatewayDiscoveryService(): PaymentGatewayDiscoveryService
    {
        return resolve(PaymentGatewayDiscoveryService::class);
    }

    private function mockFormBuilder(): MockInterface
    {
        return $this->mock(FormBuilder::class);
    }

    private function mockFormBuilderFactory(): MockInterface
    {
        return $this->mock(FormBuilderFactory::class);
    }

    private function mockPaymentApi(): MockInterface
    {
        return $this->mock(PaymentApi::class);
    }

    private function discoverGateway(): PaymentGatewayInterface
    {
        $paymentDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        return $paymentDiscoveryService->get('edenred');
    }

    private function mockResponse(): MockInterface
    {
        return Mockery::mock(Response::class);
    }

    private function successfulVerifyResponse(): stdClass
    {
        $json = <<<JSON
{
  "committed": true,
  "committed_amount": 2499,
  "card_token": "77ffbaca-9658-4aef-ac9e-f85da3164bdb",
  "recurring": true,
  "filing_code": "181004705200",
  "cardholder_authentication": "no",
  "card": {
    "type": "Visa",
    "partial_pan": "0024",
    "expire_year": "2023",
    "expire_month": "11",
    "cvc_required": "no",
    "bin": "415301",
    "funding": "debit",
    "country_code": "FI",
    "category": "unknown",
    "card_fingerprint": "da6b0df36efd17c0e7f6967b9e440a0c61b6bd3d96b62f14c90155a1fb883597",
    "pan_fingerprint": "e858e18daac509247f463292641237d6a74ce44e0971ba2de4a14874928a8805"
  },
  "result": {
    "code": 100,
    "message": "OK"
  }
}
JSON;

        return json_decode($json);
    }

    private function mockSetGatewayResultService(): MockInterface
    {
        return $this->mock(SetGatewayResultService::class);
    }
}
