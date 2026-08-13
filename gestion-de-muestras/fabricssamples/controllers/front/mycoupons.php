<?php

use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponReissueRepository;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Service\CouponStatusPresenter;

class FabricssamplesMycouponsModuleFrontController extends ModuleFrontController
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

        $repository = new CouponRepository();
        $coupons = array_merge(
            $repository->findByCustomer($idCustomer, $idShop),
            (new CouponReissueRepository())->findByCustomer($idCustomer, $idShop)
        );
        usort($coupons, static fn (array $left, array $right): int => strcmp(
            (string) ($right['date_add'] ?? ''),
            (string) ($left['date_add'] ?? '')
        ));
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
        }
        unset($coupon);

        $this->context->smarty->assign([
            'fs_coupons' => $coupons,
            'fs_samples_url' => $this->context->link->getModuleLink($this->module->name, 'mysamples'),
        ]);
        $this->setTemplate('module:fabricssamples/views/templates/front/mycoupons.tpl');
    }
}
