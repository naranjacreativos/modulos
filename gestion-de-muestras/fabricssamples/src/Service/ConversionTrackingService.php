<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Repository\ConversionRepository;

final class ConversionTrackingService
{
    public function __construct(private ConversionRepository $repository)
    {
    }

    public function track(\Order $order, array $currentSamples): void
    {
        if ((int) $order->id_customer <= 0) {
            return;
        }

        $sampleCustomizationIds = array_map('intval', array_column($currentSamples, 'id_customization'));
        $purchasedProducts = [];
        $details = \Db::getInstance()->executeS(
            'SELECT product_id, id_customization FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order=' . (int) $order->id
        );
        foreach (is_array($details) ? $details : [] as $detail) {
            if (in_array((int) $detail['id_customization'], $sampleCustomizationIds, true)) {
                continue;
            }
            $purchasedProducts[] = (int) $detail['product_id'];
        }
        $purchasedProducts = array_values(array_unique(array_filter($purchasedProducts)));
        if ($purchasedProducts === []) {
            return;
        }

        $couponId = (int) \Db::getInstance()->getValue(
            'SELECT mapped.id_fabricssamples_coupon FROM ('
            . ' SELECT fsc.id_fabricssamples_coupon,fsc.id_cart_rule,0 is_reissue'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon` fsc'
            . ' UNION ALL'
            . ' SELECT fsr.id_fabricssamples_coupon,fsr.id_cart_rule,1 is_reissue'
            . ' FROM `' . _DB_PREFIX_ . 'fabricssamples_coupon_reissue` fsr'
            . ' ) mapped'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'order_cart_rule` ocr'
            . ' ON ocr.id_cart_rule=mapped.id_cart_rule AND ocr.deleted=0'
            . ' WHERE ocr.id_order=' . (int) $order->id
            . ' ORDER BY mapped.is_reissue DESC,mapped.id_fabricssamples_coupon DESC'
        );

        foreach ($this->repository->sampledProductsBeforeOrder(
            (int) $order->id_customer,
            (int) $order->id_shop,
            (int) $order->id
        ) as $sampled) {
            $idProduct = (int) $sampled['id_product'];
            if (!in_array($idProduct, $purchasedProducts, true)) {
                continue;
            }
            $this->repository->insert([
                'id_sample_order' => (int) $sampled['id_sample_order'],
                'id_purchase_order' => (int) $order->id,
                'id_customer' => (int) $order->id_customer,
                'id_shop' => (int) $order->id_shop,
                'id_product' => $idProduct,
                'id_fabricssamples_coupon' => $couponId,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
