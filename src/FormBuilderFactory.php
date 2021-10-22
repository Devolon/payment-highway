<?php

namespace Devolon\PaymentHighway;

use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Solinor\PaymentHighway\FormBuilder;

class FormBuilderFactory
{
    public function __construct(private GenerateCallbackURLService $generateCallbackURLService)
    {
    }

    public function create(Transaction $transaction): FormBuilder
    {
        $method = "POST";
        $signatureKeyId = config('payment_highway.signature_key_id');
        $signatureSecret = config('payment_highway.signature_secret');
        $account = config('payment_highway.account');
        $merchant = config('payment_highway.merchant');
        $baseUrl = config('payment_highway.base_url');
        $successUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_DONE);
        $failureUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_FAILED);
        $language = "EN";

        return new FormBuilder(
            $method,
            $signatureKeyId,
            $signatureSecret,
            $account,
            $merchant,
            $baseUrl,
            $successUrl,
            $failureUrl,
            $failureUrl,
            $language
        );
    }
}
