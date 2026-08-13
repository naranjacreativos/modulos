<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Domain;

/**
 * Resolves customer/group limit exceptions without any PrestaShop dependency.
 * Customer rules have priority over group rules. Among group rules, exempt wins.
 */
final class LimitExceptionPolicy
{
    /**
     * @param array<string,mixed> $customerRule
     * @param list<array<string,mixed>> $groupRules
     * @param array{max_total:int,max_product:int,period_days:int} $defaults
     * @return array{exempt:bool,max_total:int,max_product:int,period_days:int,source_type:string,source_id:int}
     */
    public function resolve(array $customerRule, array $groupRules, array $defaults): array
    {
        $base = [
            'exempt' => false,
            'max_total' => max(0, (int) $defaults['max_total']),
            'max_product' => max(0, (int) $defaults['max_product']),
            'period_days' => max(1, (int) $defaults['period_days']),
            'source_type' => 'default',
            'source_id' => 0,
        ];

        if ($this->isActive($customerRule)) {
            return $this->applyRule($customerRule, $base, 'customer');
        }

        foreach ($groupRules as $rule) {
            if (!$this->isActive($rule)) {
                continue;
            }
            if ((string) ($rule['mode'] ?? '') === 'exempt') {
                return $this->applyRule($rule, $base, 'group');
            }
        }

        foreach ($groupRules as $rule) {
            if ($this->isActive($rule)) {
                return $this->applyRule($rule, $base, 'group');
            }
        }

        return $base;
    }

    /** @param array<string,mixed> $rule */
    private function isActive(array $rule): bool
    {
        return $rule !== [] && (int) ($rule['active'] ?? 0) === 1;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array{exempt:bool,max_total:int,max_product:int,period_days:int,source_type:string,source_id:int} $base
     * @return array{exempt:bool,max_total:int,max_product:int,period_days:int,source_type:string,source_id:int}
     */
    private function applyRule(array $rule, array $base, string $sourceType): array
    {
        $base['source_type'] = $sourceType;
        $base['source_id'] = max(0, (int) ($rule['target_id'] ?? 0));
        if ((string) ($rule['mode'] ?? '') === 'exempt') {
            $base['exempt'] = true;
            return $base;
        }

        $limitFields = [
            'max_total_period' => 'max_total',
            'max_product_period' => 'max_product',
            'period_days' => 'period_days',
        ];
        foreach ($limitFields as $source => $target) {
            $value = (int) ($rule[$source] ?? 0);
            if ($value > 0) {
                $base[$target] = $value;
            }
        }

        return $base;
    }
}
