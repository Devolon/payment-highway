<?php

namespace Devolon\PaymentHighway;

use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\ServiceProvider;
use Solinor\PaymentHighway\FormBuilder;
use Solinor\PaymentHighway\PaymentApi;

class DevolonPaymentHighwayServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (env('IS_PAYMENT_HIGHWAY_AVAILABLE', false)) {
            $this->app->tag(PaymentHighwayGateway::class, PaymentGatewayInterface::class);
        }

        if (env('IS_EDENRED_AVAILABLE', false)) {
            $this->app->tag(EdenredGateway::class, PaymentGatewayInterface::class);
        }

        $this->app->singleton(PaymentApi::class, function () {
            $serviceUrl = config('payment_highway.base_url');
            $signatureKeyId = config('payment_highway.signature_key_id');
            $signatureSecret = config('payment_highway.signature_secret');
            $account = config('payment_highway.account');
            $merchant = config('payment_highway.merchant');

            return new PaymentApi($serviceUrl, $signatureKeyId, $signatureSecret, $account, $merchant);
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/payment_highway.php', 'payment_highway');
        $this->publishes([
            __DIR__ . '/../config/payment_highway.php' => config_path('payment_highway.php')
        ], 'payment-highway-config');
    }
}
