<?php

namespace Devolon\PaymentHighway;

use Devolon\Payment\Contracts\CanRefund;
use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\SetGatewayResultService;
use Httpful\Exception\ConnectionErrorException;
use Solinor\PaymentHighway\PaymentApi;

class PaymentHighwayGateway implements PaymentGatewayInterface, HasUpdateTransactionData, CanRefund
{
    public const NAME = 'payment-highway';

    public function __construct(
        private FormBuilderFactory $formBuilderFactory,
        private PaymentApi $paymentApi,
        private SetGatewayResultService $setGatewayResultService
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function purchase(Transaction $transaction): PurchaseResultDTO
    {
        $formBuilder = $this->formBuilderFactory->create($transaction);
        $form = $formBuilder->generatePaymentParameters(
            round($transaction->money_amount * 100),
            'EUR',
            $transaction->id,
            "Purchase transaction {$transaction->id}",
        );

        return PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => RedirectDTO::fromArray([
                'redirect_url' => $form->getAction(),
                'redirect_method' => $form->getMethod(),
                'redirect_data' => $form->getParameters(),
            ])
        ]);
    }

    public function verify(Transaction $transaction, array $data): bool
    {
        $response = $this->paymentApi->commitFormTransaction(
            $data['sph-transaction-id'],
            round($transaction->money_amount * 100),
            'EUR'
        );

        if (200 !== $response->code || 100 !== $response->body->result->code) {
            return false;
        }

        if (!isset($response->body->committed) || !$response->body->committed) {
            return false;
        }

        $gatewayResult = clone $response->body;
        $gatewayResult->gateway_transaction_id = $data['sph-transaction-id'];

        ($this->setGatewayResultService)(
            $transaction,
            'commit',
            $gatewayResult
        );

        return true;
    }

    public function refund(Transaction $transaction): bool
    {
        try {
            $response = $this->paymentApi->revertTransaction(
                $transaction->gateway_results['commit']['gateway_transaction_id'],
                round($transaction->money_amount * 100)
            );
        } catch (ConnectionErrorException) {
            return false;
        }

        if (200 !== $response->code || 100 !== $response->body->result->code) {
            return false;
        }

        ($this->setGatewayResultService)(
            $transaction,
            'refund',
            $response->body
        );

        return true;
    }

    public function updateTransactionDataRules(string $newStatus): array
    {
        if ($newStatus !== Transaction::STATUS_DONE) {
            return [];
        }

        return [
            'sph-transaction-id' => [
                'required',
                'string'
            ]
        ];
    }
}
