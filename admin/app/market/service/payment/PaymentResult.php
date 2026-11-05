<?php

declare(strict_types=1);

namespace app\market\service\payment;

final class PaymentResult
{
    public function __construct(
        public readonly string $orderNo,
        public readonly string $tradeNo,
        public readonly int $amount,
        public readonly bool $paid,
        public readonly array $raw = []
    ) {
    }
}
