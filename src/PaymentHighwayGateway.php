<?php

namespace Devolon\PaymentHighway;

use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\SetGatewayResultService;
use Solinor\PaymentHighway\FormBuilder;
use Solinor\PaymentHighway\PaymentApi;

class PaymentHighwayGateway implements PaymentGatewayInterface, HasUpdateTransactionData
{
    public const NAME = 'payment-highway';

    public function __construct(
        private FormBuilderFactory $formBuilderFactory,
        private PaymentApi $paymentApi,
        private SetGatewayResultService $setGatewayResultService
    ) {
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

        if ($response->code !== 200) {
            return false;
        }

        if (!isset($response->body->committed) || !$response->body->committed) {
            return false;
        }

        ($this->setGatewayResultService)($transaction, 'commit', $response->body);

        return true;
    }

    public function getName(): string
    {
        return self::NAME;
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
