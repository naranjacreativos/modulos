<?php

use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponReissueRepository;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Service\CouponStatusPresenter;
use NaranjaCreativos\FabricSamples\Repository\OrderSampleRepository;
use NaranjaCreativos\FabricSamples\Service\ImageSnapshotService;

class FabricssamplesMysamplesModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;

    public function initContent()
    {
        parent::initContent();

        $idCustomer = (int) $this->context->customer->id;
        $idShop = (int) $this->context->shop->id;
        if (method_exists($this->module, 'repairCustomerHistory')) {
            $this->module->repairCustomerHistory($idCustomer, $idShop);
        }
        if (method_exists($this->module, 'generateEligibleCouponsForCustomer')) {
            $this->module->generateEligibleCouponsForCustomer($idCustomer, $idShop);
        }

        $orderRepository = new OrderSampleRepository();
        $couponRepository = new CouponRepository();
        $snapshotService = new ImageSnapshotService($this->module);
        $rows = $orderRepository->findDisplayByCustomer($idCustomer, $idShop);

        foreach ($rows as &$row) {
            $name = trim((string) ($row['product_name'] ?? ''));
            if ($name !== '' && !preg_match('/^muestra\s*[-–—:]?/iu', $name)) {
                $row['product_name'] = $this->module->l('Muestra') . ' - ' . $name;
            }
            $row['image_url'] = $snapshotService->url((string) ($row['image_snapshot'] ?? ''));
            $row['product_url'] = (string) ($row['product_url'] ?? '');
            try {
                $product = new Product((int) $row['id_product'], false, (int) $this->context->language->id, $idShop);
                if (Validate::isLoadedObject($product)) {
                    if ($row['product_url'] === '') {
                        $row['product_url'] = $this->context->link->getProductLink($product);
                    }
                    if ($row['image_url'] === '') {
                        $cover = Product::getCover((int) $product->id);
                        if (is_array($cover) && !empty($cover['id_image'])) {
                            $row['image_url'] = $this->context->link->getImageLink(
                                (string) $product->link_rewrite,
                                (int) $cover['id_image'],
                                ImageType::getFormattedName('small')
                            );
                        }
                    }
                }
            } catch (Throwable) {
                // Native order data remains sufficient even if the current product no longer exists.
            }
            $currency = !empty($row['id_currency']) ? new Currency((int) $row['id_currency']) : $this->context->currency;
            $row['total_price_formatted'] = PriceFormatter::format((float) ($row['total_price_tax_incl'] ?? 0), $currency);
        }
        unset($row);

        $coupons = array_merge(
            $couponRepository->findByCustomer($idCustomer, $idShop),
            (new CouponReissueRepository())->findByCustomer($idCustomer, $idShop)
        );
        $availableCoupons = 0;
        foreach ($coupons as &$coupon) {
            $order = new Order((int) $coupon['id_order']);
            $currency = Validate::isLoadedObject($order) ? new Currency((int) $order->id_currency) : $this->context->currency;
            $coupon['discount_value_formatted'] = PriceFormatter::format((float) $coupon['discount_value'], $currency);
            $coupon['minimum_order_formatted'] = PriceFormatter::format((float) $coupon['minimum_order'], $currency);
            $status = (new CouponStatusPresenter())->present($coupon);
            $coupon['status'] = $status['key'];
            $coupon['status_label'] = $this->module->l($status['label_source']);
            $coupon['status_front_class'] = $status['front_class'];
            $coupon['status_admin_class'] = $status['admin_class'];
            if ($coupon['status'] === 'available') {
                ++$availableCoupons;
            }
        }
        unset($coupon);

        $this->context->smarty->assign([
            'fs_samples' => $rows,
            'fs_available_coupon_count' => $availableCoupons,
            'fs_coupons' => $coupons,
            'fs_my_coupons_url' => $this->context->link->getModuleLink($this->module->name, 'mycoupons'),
        ]);
        $this->setTemplate('module:fabricssamples/views/templates/front/mysamples.tpl');
    }
}
