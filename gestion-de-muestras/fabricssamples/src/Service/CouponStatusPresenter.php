<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Domain\CouponState;

final class CouponStatusPresenter
{
    /**
     * @return array{key:string,label_source:string,front_class:string,admin_class:string}
     */
    public function present(array $coupon): array
    {
        $storedState = (string) ($coupon['state'] ?? '');
        $used = !empty($coupon['used']);
        $dateTo = trim((string) ($coupon['date_to'] ?? ''));
        $timestamp = $dateTo !== '' ? strtotime($dateTo) : false;
        $expired = $timestamp !== false && $timestamp < time();
        $active = !empty($coupon['cart_rule_active']);

        $state = $storedState;
        if (!in_array($state, CouponState::all(), true)) {
            $state = $used ? CouponState::USED : ($expired ? CouponState::EXPIRED : ($active ? CouponState::AVAILABLE : CouponState::INACTIVE));
        } elseif ($state !== CouponState::DELETED_PERMANENTLY) {
            // Native CartRule usage and dates are authoritative for visible state.
            if ($used) {
                $state = CouponState::USED;
            } elseif ($expired) {
                $state = CouponState::EXPIRED;
            } elseif ($state === CouponState::AVAILABLE && !$active) {
                $state = CouponState::INACTIVE;
            }
        }

        return match ($state) {
            CouponState::USED => $this->row('used', 'Usado', 'is-used', 'label-default'),
            CouponState::EXPIRED => $this->row('expired', 'Caducado', 'is-expired', 'label-warning'),
            CouponState::AVAILABLE, CouponState::GENERATED, CouponState::ELIGIBLE => $this->row('available', 'Disponible', 'is-available', 'label-success'),
            CouponState::CANCELLED => $this->row('cancelled', 'Cancelado', 'is-cancelled', 'label-danger'),
            CouponState::DELETED_PERMANENTLY => $this->row('deleted_permanently', 'Eliminado', 'is-deleted', 'label-danger'),
            CouponState::PENDING => $this->row('pending', 'Pendiente', 'is-pending', 'label-info'),
            default => $this->row('inactive', 'Inactivo', 'is-inactive', 'label-default'),
        };
    }

    /** @return array{key:string,label_source:string,front_class:string,admin_class:string} */
    private function row(string $key, string $label, string $frontClass, string $adminClass): array
    {
        return [
            'key' => $key,
            'label_source' => $label,
            'front_class' => $frontClass,
            'admin_class' => $adminClass,
        ];
    }
}
