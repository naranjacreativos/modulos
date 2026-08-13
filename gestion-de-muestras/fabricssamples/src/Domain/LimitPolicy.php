<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

/**
 * Pure limit evaluator. It deliberately has no PrestaShop dependency so the
 * most important cart rules can be covered by fast unit tests.
 */
final class LimitPolicy
{
    /**
     * @param array{cart_total_after:int,product_after:int,customer_total_after:int,customer_product_after:int} $state
     * @param array{max_total:int,max_product:int,max_customer_total:int,max_customer_product:int} $limits
     * @return array{code:string,limit:int}|null
     */
    public function firstViolation(array $state, array $limits): ?array
    {
        $checks = [
            'total' => ['value' => $state['cart_total_after'], 'limit' => $limits['max_total']],
            'product' => ['value' => $state['product_after'], 'limit' => $limits['max_product']],
            'customer_total' => ['value' => $state['customer_total_after'], 'limit' => $limits['max_customer_total']],
            'customer_product' => [
                'value' => $state['customer_product_after'],
                'limit' => $limits['max_customer_product'],
            ],
        ];

        foreach ($checks as $code => $check) {
            if ($check['limit'] > 0 && $check['value'] > $check['limit']) {
                return ['code' => $code, 'limit' => $check['limit']];
            }
        }

        return null;
    }
}
