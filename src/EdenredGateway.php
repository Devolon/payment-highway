<?php

namespace Devolon\PaymentHighway;

use Devolon\PaymentHighway\PaymentHighwayGateway;

class EdenredGateway extends PaymentHighwayGateway
{
    public const NAME = 'edenred';

    public function getName(): string
    {
        return self::NAME;
    }
}
