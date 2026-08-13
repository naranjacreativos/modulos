<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\CouponState;
use NaranjaCreativos\FabricSamples\Domain\CouponTransitionPolicy;
use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\OrderSampleRepository;

final class CouponLifecycleService
{
    public function __construct(
        private ModuleConfiguration $configuration,
        private OrderSampleRepository $orderRepository,
        private CouponRepository $couponRepository,
        private CouponService $couponService,
        private CouponTransitionPolicy $transitionPolicy
    ) {
    }

    public function synchronizeOrderById(int $idOrder, ?\OrderState $newState = null, string $source = 'manual'): array
    {
        if ($idOrder <= 0) {
            return [];
        }
        $order = new \Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return [];
        }

        return $this->synchronizeOrder($order, $newState, $source);
    }

    public function synchronizeOrder(\Order $order, ?\OrderState $newState = null, string $source = 'order'): array
    {
        if (!\Validate::isLoadedObject($order)) {
            return [];
        }

        $idOrder = (int) $order->id;
        $suppressed = $this->couponRepository->isSuppressed($idOrder);
        $coupon = $this->couponRepository->findByOrder($idOrder);
        $samples = $this->remainingSamples($this->orderRepository->findForCouponByOrder($idOrder));
        $hasSamples = $samples !== [];
        $terminal = $this->isTerminalOrder($order, $newState);
        $eligible = $this->isEligible($order, $newState) && !$terminal && $hasSamples;

        if ($coupon === []) {
            if (!$this->transitionPolicy->shouldGenerate(
                $this->configuration->getBool('COUPON_ENABLED'),
                $eligible,
                $suppressed,
                $hasSamples
            )) {
                return [];
            }

            $coupon = $this->couponService->generateForOrder($order, $samples);
            if ($coupon !== []) {
                $this->updateState($coupon, CouponState::AVAILABLE, 'generated:' . $source, (int) ($newState->id ?? $order->current_state));
                return $this->couponRepository->findByOrder($idOrder);
            }
            return [];
        }

        $used = $this->couponService->isUsed($coupon);
        $dateTo = strtotime((string) ($coupon['date_to'] ?? ''));
        $expired = $dateTo !== false && $dateTo < time();
        $rule = new \CartRule((int) ($coupon['id_cart_rule'] ?? 0));
        $ruleLoaded = \Validate::isLoadedObject($rule);
        $active = $ruleLoaded && !empty($rule->active);

        $targetState = $this->transitionPolicy->stateForExisting(
            $suppressed,
            $used,
            $expired,
            $terminal || !$hasSamples,
            $active
        );

        if ($targetState === CouponState::DELETED_PERMANENTLY) {
            if ($ruleLoaded && $active && !$this->couponService->setActive($coupon, false)) {
                throw new \RuntimeException('No se pudo desactivar el cupón eliminado permanentemente.');
            }
            $this->updateState($coupon, $targetState, 'suppressed:' . $source, (int) ($newState->id ?? $order->current_state));
            return $this->couponRepository->findByOrder($idOrder);
        }

        if ($used) {
            $this->updateState($coupon, CouponState::USED, 'used:' . $source, (int) ($newState->id ?? $order->current_state));
            return $this->couponRepository->findByOrder($idOrder);
        }

        if ($terminal || !$hasSamples) {
            if ($ruleLoaded && $active && !$this->couponService->setActive($coupon, false)) {
                throw new \RuntimeException('No se pudo desactivar el cupón de un pedido cancelado o reembolsado.');
            }
            $reason = $terminal ? 'order_terminal:' . $source : 'no_remaining_samples:' . $source;
            $this->updateState($coupon, CouponState::CANCELLED, $reason, (int) ($newState->id ?? $order->current_state));
            return $this->couponRepository->findByOrder($idOrder);
        }

        if ($expired) {
            if ($ruleLoaded && $active && !$this->couponService->setActive($coupon, false)) {
                throw new \RuntimeException('No se pudo desactivar el cupón caducado.');
            }
            $this->updateState($coupon, CouponState::EXPIRED, 'expired:' . $source, (int) ($newState->id ?? $order->current_state));
            return $this->couponRepository->findByOrder($idOrder);
        }

        if (!$ruleLoaded && $eligible) {
            // Keep the order idempotent: recreate only when the native rule was removed accidentally,
            // never when the order has a permanent suppression marker.
            $this->couponRepository->deleteByOrder($idOrder);
            $coupon = $this->couponService->generateForOrder($order, $samples);
            if ($coupon !== []) {
                $this->updateState($coupon, CouponState::AVAILABLE, 'rule_recreated:' . $source, (int) ($newState->id ?? $order->current_state));
            }
            return $this->couponRepository->findByOrder($idOrder);
        }

        if ($this->transitionPolicy->shouldReactivate(
            (string) ($coupon['state'] ?? CouponState::INACTIVE),
            $eligible,
            $suppressed,
            false,
            false
        )) {
            if ($this->couponService->setActive($coupon, true)) {
                $active = true;
            }
        }

        if ($eligible && $ruleLoaded && (string) ($coupon['discount_mode'] ?? '') !== 'manual') {
            $coupon = $this->couponService->recalculateForOrder($order, $samples, $coupon);
            $active = (bool) (new \CartRule((int) ($coupon['id_cart_rule'] ?? 0)))->active;
        }

        $state = $active ? CouponState::AVAILABLE : CouponState::INACTIVE;
        $this->updateState($coupon, $state, 'synchronized:' . $source, (int) ($newState->id ?? $order->current_state));

        return $this->couponRepository->findByOrder($idOrder);
    }

    /** @return list<array<string,mixed>> */
    private function remainingSamples(array $samples): array
    {
        $remaining = [];
        foreach ($samples as $sample) {
            $quantity = max(0, (int) ($sample['quantity'] ?? 0));
            $idOrderDetail = (int) ($sample['id_order_detail'] ?? 0);
            if ($idOrderDetail > 0) {
                $detail = \Db::getInstance()->getRow(
                    'SELECT product_quantity_refunded, product_quantity_return'
                    . ' FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order_detail=' . $idOrderDetail
                );
                $cancelled = max(
                    (int) ($detail['product_quantity_refunded'] ?? 0),
                    (int) ($detail['product_quantity_return'] ?? 0)
                );
                $quantity = max(0, $quantity - min($quantity, max(0, $cancelled)));
            }
            if ($quantity <= 0) {
                continue;
            }
            $unitExcl = (float) ($sample['unit_price_tax_excl'] ?? 0);
            $unitIncl = (float) ($sample['unit_price_tax_incl'] ?? 0);
            $sample['quantity'] = $quantity;
            $sample['total_price_tax_excl'] = $unitExcl * $quantity;
            $sample['total_price_tax_incl'] = $unitIncl * $quantity;
            $remaining[] = $sample;
        }

        return $remaining;
    }

    private function updateState(array $coupon, string $state, string $reason, int $idOrderState): void
    {
        $idCoupon = (int) ($coupon['id_fabricssamples_coupon'] ?? 0);
        if ($idCoupon <= 0) {
            return;
        }
        $this->couponRepository->updateById($idCoupon, [
            'state' => pSQL($state),
            'state_reason' => pSQL(substr($reason, 0, 255)),
            'date_state' => date('Y-m-d H:i:s'),
            'last_order_state' => max(0, $idOrderState),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);
    }

    private function isEligible(\Order $order, ?\OrderState $state): bool
    {
        if (!$this->configuration->getBool('COUPON_ENABLED')) {
            return false;
        }
        if ($this->configuration->getString('COUPON_TRIGGER', null, 'order') === 'order') {
            return true;
        }

        return $this->isPaidOrder($order, $state);
    }

    private function isPaidOrder(\Order $order, ?\OrderState $state): bool
    {
        $paymentStateId = (int) \Configuration::get('PS_OS_PAYMENT');
        if ($state instanceof \OrderState && \Validate::isLoadedObject($state)) {
            if (!empty($state->paid) || ($paymentStateId > 0 && (int) $state->id === $paymentStateId)) {
                return true;
            }
        }
        $current = new \OrderState((int) $order->current_state);
        return \Validate::isLoadedObject($current)
            && (!empty($current->paid) || ($paymentStateId > 0 && (int) $current->id === $paymentStateId));
    }

    private function isTerminalOrder(\Order $order, ?\OrderState $state): bool
    {
        $idState = $state instanceof \OrderState && \Validate::isLoadedObject($state)
            ? (int) $state->id
            : (int) $order->current_state;
        $terminalIds = array_values(array_filter(array_map('intval', [
            \Configuration::get('PS_OS_CANCELED'),
            \Configuration::get('PS_OS_REFUND'),
            \Configuration::get('PS_OS_ERROR'),
        ])));

        return in_array($idState, $terminalIds, true);
    }
}
