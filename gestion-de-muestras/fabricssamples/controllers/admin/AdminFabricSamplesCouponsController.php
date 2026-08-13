<?php

require_once dirname(__DIR__, 2) . '/config/autoload.php';

use NaranjaCreativos\FabricSamples\Repository\AuditRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponReissueRepository;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Service\AuditService;
use NaranjaCreativos\FabricSamples\Service\CouponStatusPresenter;
use NaranjaCreativos\FabricSamples\Security\AdminControllerSecurityTrait;

class AdminFabricSamplesCouponsController extends ModuleAdminController
{
    use AdminControllerSecurityTrait;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('Cupones de muestras');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitEditFabricSamplesCoupon')) {
            if ($this->guardAdminAction('edit')) {
                $this->saveCouponEdit();
            }
        }
        if (Tools::isSubmit('issueReplacementFabricSamplesCoupon')) {
            if ($this->guardAdminAction('edit')) {
                $this->issueReplacementCoupon((int) Tools::getValue('issueReplacementFabricSamplesCoupon'));
            }
        }
        if (Tools::isSubmit('deleteFabricSamplesCoupon')) {
            if ($this->guardAdminAction('delete')) {
                $this->deleteCoupons([(int) Tools::getValue('deleteFabricSamplesCoupon')]);
            }
        }
        if (Tools::isSubmit('bulkDeleteFabricSamplesCoupons')) {
            $ids = Tools::getValue('couponBox', []);
            if ($this->guardAdminAction('delete')) {
                $this->deleteCoupons(is_array($ids) ? array_map('intval', $ids) : []);
            }
        }
        if (Tools::isSubmit('repairFabricSamplesHistory')) {
            if ($this->guardAdminAction('edit')) {
                $result = method_exists($this->module, 'rebuildHistoryAndCoupons')
                    ? $this->module->rebuildHistoryAndCoupons((int) $this->context->shop->id)
                    : ['history' => 0, 'coupons' => 0];
                $this->confirmations[] = sprintf(
                    $this->module->l('Reparación completada: %d muestras históricas y %d cupones generados.'),
                    (int) ($result['history'] ?? 0),
                    (int) ($result['coupons'] ?? 0)
                );
                $this->auditService()->log(
                    'coupon_history_repair',
                    'module',
                    'fabricssamples',
                    [],
                    $result,
                    'Reparación manual de históricos y cupones.',
                    (int) $this->context->shop->id
                );
            }
        }
        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        if (method_exists($this->module, 'purgeOrphanOrderData')) {
            $this->module->purgeOrphanOrderData();
        }
        $repository = new CouponRepository();
        $contextShopId = (int) Shop::getContextShopID();
        $rows = $repository->findForAdmin($contextShopId, 1000);
        $couponIds = array_values(array_filter(array_map(
            'intval',
            array_column($rows, 'id_fabricssamples_coupon')
        )));
        try {
            $reissuesByCoupon = method_exists($this->module, 'couponReissues')
                ? $this->module->couponReissues($couponIds)
                : [];
        } catch (Throwable $exception) {
            $reissuesByCoupon = [];
            PrestaShopLogger::addLog('fabricssamples coupon reissue listing: ' . $exception->getMessage(), 2);
            $this->warnings[] = $this->module->l('No se pudo actualizar el estado del historial de reemisiones. Use Diagnóstico para revisar el esquema.');
        }
        $statusPresenter = new CouponStatusPresenter();
        foreach ($rows as &$row) {
            $order = new Order((int) $row['id_order']);
            $currency = Validate::isLoadedObject($order) ? new Currency((int) $order->id_currency) : $this->context->currency;
            $row['discount_value_formatted'] = PriceFormatter::format((float) $row['discount_value'], $currency);
            $row['minimum_order_formatted'] = PriceFormatter::format((float) $row['minimum_order'], $currency);
            $status = $statusPresenter->present($row);
            $row['status'] = $status['key'];
            $row['status_label'] = $this->module->l($status['label_source']);
            $row['status_front_class'] = $status['front_class'];
            $row['status_admin_class'] = $status['admin_class'];
            $row['order_link'] = $this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => (int) $row['id_order'],
                'vieworder' => 1,
            ]);
            $row['customer_link'] = $this->context->link->getAdminLink('AdminCustomers', true, [], [
                'id_customer' => (int) $row['id_customer'],
                'viewcustomer' => 1,
            ]);
            $row['cart_rule_link'] = !empty($row['id_cart_rule'])
                ? $this->context->link->getAdminLink('AdminCartRules', true, [], [
                    'id_cart_rule' => (int) $row['id_cart_rule'],
                    'updatecart_rule' => 1,
                ])
                : '';
            $row['edit_link'] = self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name)
                . '&edit_coupon=' . (int) $row['id_fabricssamples_coupon'];
            $row['reissues'] = $reissuesByCoupon[(int) $row['id_fabricssamples_coupon']] ?? [];
            $row['has_pending_reissue'] = false;
            foreach ($row['reissues'] as &$reissue) {
                $reissueStatus = $statusPresenter->present([
                    'state' => (string) ($reissue['computed_state'] ?? $reissue['state'] ?? ''),
                    'used' => (string) ($reissue['computed_state'] ?? '') === 'used',
                    'date_to' => (string) ($reissue['date_to'] ?? ''),
                    'cart_rule_active' => (int) ($reissue['cart_rule_active'] ?? 0),
                ]);
                $reissue['status'] = $reissueStatus['key'];
                $reissue['status_label'] = $this->module->l($reissueStatus['label_source']);
                $reissue['status_admin_class'] = $reissueStatus['admin_class'];
                $reissue['cart_rule_link'] = !empty($reissue['id_cart_rule'])
                    ? $this->context->link->getAdminLink('AdminCartRules', true, [], [
                        'id_cart_rule' => (int) $reissue['id_cart_rule'],
                        'updatecart_rule' => 1,
                    ])
                    : '';
                if ($reissue['status'] === 'available') {
                    $row['has_pending_reissue'] = true;
                }
            }
            unset($reissue);
            $row['can_issue_replacement'] = ($row['status'] ?? '') === 'used'
                && empty($row['deleted_permanently'])
                && !empty($row['id_cart_rule'])
                && !$row['has_pending_reissue'];
        }
        unset($row);

        $this->context->smarty->assign([
            'fs_admin_coupons' => $rows,
            'fs_coupon_stats' => $repository->stats($contextShopId),
            'fs_admin_link' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_repair_link' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_config_link' => $this->context->link->getAdminLink('AdminFabricSamples'),
            'fs_coupon_feature_enabled' => (bool) Configuration::get('FABRICS_SAMPLES_COUPON_ENABLED'),
            'fs_show_shop_column' => Shop::isFeatureActive(),
        ]);

        $idEditCoupon = (int) Tools::getValue('edit_coupon');
        if ($idEditCoupon > 0) {
            $this->content .= $this->renderEditForm($repository, $idEditCoupon, $contextShopId);
        }

        $this->content .= $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/coupon_list.tpl'
        );
        $this->context->smarty->assign(['content' => $this->content]);
    }

    private function deleteCoupons(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            $this->errors[] = $this->module->l('Seleccione al menos un cupón para eliminar.');
            return;
        }

        $repository = new CouponRepository();
        $reissueRepository = new CouponReissueRepository();
        $contextShopId = (int) Shop::getContextShopID();
        $deleted = 0;
        foreach ($ids as $idCoupon) {
            $coupon = $repository->findById($idCoupon);
            if ($coupon === []) {
                continue;
            }
            if ($contextShopId > 0 && (int) $coupon['id_shop'] !== $contextShopId) {
                $this->errors[] = $this->module->l('No tiene permiso para eliminar un cupón de otra tienda.');
                continue;
            }

            $suppressed = $repository->suppressOrder(
                (int) $coupon['id_order'],
                (int) $coupon['id_shop'],
                (int) $coupon['id_customer'],
                'manual_delete'
            );
            $markedDeleted = $repository->markDeletedPermanently(
                $idCoupon,
                isset($this->context->employee->id) ? (int) $this->context->employee->id : 0
            );
            if (!$suppressed && !$markedDeleted) {
                $this->errors[] = $this->module->l('No se pudo registrar el borrado permanente del cupón.');
                continue;
            }

            $nativeRuleIds = array_values(array_unique(array_filter(array_merge(
                [(int) ($coupon['id_cart_rule'] ?? 0)],
                $reissueRepository->cartRuleIds($idCoupon)
            ))));
            foreach ($nativeRuleIds as $idCartRule) {
                $cartRule = new CartRule((int) $idCartRule);
                if (Validate::isLoadedObject($cartRule) && !$cartRule->delete()) {
                    $cartRule->active = 0;
                    $cartRule->update();
                    $this->warnings[] = sprintf($this->module->l('La regla de carrito %d no pudo eliminarse y se dejó desactivada.'), $idCartRule);
                }
            }
            $reissueRepository->markDeletedByCoupon($idCoupon);

            Db::getInstance()->update(
                'fabricssamples_conversion',
                ['id_fabricssamples_coupon' => 0],
                'id_fabricssamples_coupon=' . $idCoupon
            );
            if ($markedDeleted || $suppressed) {
                ++$deleted;
                $this->auditService()->log(
                    'coupon_permanent_delete',
                    'coupon',
                    $idCoupon,
                    $coupon,
                    [
                        'suppressed' => $suppressed,
                        'marked_deleted' => $markedDeleted,
                        'native_cart_rules_deleted_or_disabled' => $nativeRuleIds,
                    ],
                    'Borrado permanente desde el back office.',
                    (int) $coupon['id_shop']
                );
            }
        }

        if ($deleted > 0) {
            $this->confirmations[] = sprintf($this->module->l('%d cupón(es) eliminado(s) correctamente.'), $deleted);
        }
    }


    private function renderEditForm(CouponRepository $repository, int $idCoupon, int $contextShopId): string
    {
        $coupon = $repository->findById($idCoupon);
        if ($coupon === []) {
            $this->errors[] = $this->module->l('El cupón solicitado no existe.');
            return '';
        }
        if ($contextShopId > 0 && (int) $coupon['id_shop'] !== $contextShopId) {
            $this->errors[] = $this->module->l('No tiene permiso para editar un cupón de otra tienda.');
            return '';
        }
        $rule = new CartRule((int) $coupon['id_cart_rule']);
        if (!Validate::isLoadedObject($rule)) {
            $this->errors[] = $this->module->l('La regla de carrito nativa asociada no existe. Use Reparar histórico y generar cupones.');
            return '';
        }
        $this->context->smarty->assign([
            'fs_edit_coupon' => $coupon,
            'fs_edit_rule_active' => (int) $rule->active,
            'fs_edit_date_from' => date('Y-m-d\TH:i', strtotime((string) $coupon['date_from'])),
            'fs_edit_date_to' => date('Y-m-d\TH:i', strtotime((string) $coupon['date_to'])),
            'fs_edit_action' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
            'fs_edit_cancel' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite($this->controller_name),
        ]);
        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/coupon_form.tpl');
    }

    private function saveCouponEdit(): void
    {
        $idCoupon = (int) Tools::getValue('id_fabricssamples_coupon');
        $repository = new CouponRepository();
        $coupon = $repository->findById($idCoupon);
        if ($coupon === []) {
            $this->errors[] = $this->module->l('El cupón solicitado no existe.');
            return;
        }
        $contextShopId = (int) Shop::getContextShopID();
        if ($contextShopId > 0 && (int) $coupon['id_shop'] !== $contextShopId) {
            $this->errors[] = $this->module->l('No tiene permiso para editar un cupón de otra tienda.');
            return;
        }

        $code = strtoupper(trim((string) Tools::getValue('coupon_code')));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code) ?: '';
        $discountValue = (float) str_replace(',', '.', (string) Tools::getValue('discount_value'));
        $minimumOrder = (float) str_replace(',', '.', (string) Tools::getValue('minimum_order'));
        $dateFromRaw = (string) Tools::getValue('date_from');
        $dateToRaw = (string) Tools::getValue('date_to');
        $dateFromTs = strtotime($dateFromRaw);
        $dateToTs = strtotime($dateToRaw);
        $active = (int) Tools::getValue('coupon_active') === 1 ? 1 : 0;

        if ($code === '') {
            $this->errors[] = $this->module->l('El código del cupón no puede estar vacío.');
        }
        if ($discountValue <= 0) {
            $this->errors[] = $this->module->l('El descuento debe ser mayor que cero.');
        }
        if ($minimumOrder < 0) {
            $this->errors[] = $this->module->l('La compra mínima no puede ser negativa.');
        }
        if (!$dateFromTs || !$dateToTs || $dateToTs <= $dateFromTs) {
            $this->errors[] = $this->module->l('La fecha de caducidad debe ser posterior a la fecha de inicio.');
        }
        $existingRuleId = (int) CartRule::getIdByCode($code);
        if ($existingRuleId > 0 && $existingRuleId !== (int) $coupon['id_cart_rule']) {
            $this->errors[] = $this->module->l('Ya existe otra regla de carrito con ese código.');
        }
        if ($this->errors) {
            return;
        }

        $rule = new CartRule((int) $coupon['id_cart_rule']);
        if (!Validate::isLoadedObject($rule)) {
            $this->errors[] = $this->module->l('La regla de carrito nativa asociada no existe.');
            return;
        }
        $rule->code = $code;
        $rule->reduction_amount = $discountValue;
        $rule->minimum_amount = $minimumOrder;
        $rule->date_from = date('Y-m-d H:i:s', $dateFromTs);
        $rule->date_to = date('Y-m-d H:i:s', $dateToTs);
        $rule->active = $active;
        if (!$rule->update()) {
            $this->errors[] = $this->module->l('No se pudo actualizar la regla de carrito de PrestaShop.');
            return;
        }

        if (!$repository->updateById($idCoupon, [
            'code' => pSQL($code),
            'discount_mode' => 'manual',
            'discount_value' => $discountValue,
            'minimum_order' => $minimumOrder,
            'date_from' => $rule->date_from,
            'date_to' => $rule->date_to,
            'state' => $active ? 'available' : 'inactive',
            'state_reason' => 'manual_edit',
            'date_state' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ])) {
            $this->errors[] = $this->module->l('La regla nativa se actualizó, pero no se pudo actualizar el registro interno del módulo.');
            return;
        }

        $updated = $repository->findById($idCoupon);
        $this->auditService()->log(
            'coupon_update',
            'coupon',
            $idCoupon,
            $coupon,
            $updated,
            'Edición manual del cupón.',
            (int) $coupon['id_shop']
        );
        $this->confirmations[] = $this->module->l('Cupón actualizado correctamente.');
        $_GET['edit_coupon'] = 0;
    }

    private function issueReplacementCoupon(int $idCoupon): void
    {
        $repository = new CouponRepository();
        $coupon = $repository->findById($idCoupon);
        if ($coupon === []) {
            $this->errors[] = $this->module->l('El cupón solicitado no existe.');
            return;
        }
        $contextShopId = (int) Shop::getContextShopID();
        if ($contextShopId > 0 && (int) $coupon['id_shop'] !== $contextShopId) {
            $this->errors[] = $this->module->l('No tiene permiso para emitir un cupón de otra tienda.');
            return;
        }

        try {
            $sendSelection = Tools::getValue('sendReplacementEmail', []);
            $sendEmail = is_array($sendSelection) && !empty($sendSelection[$idCoupon]);
            $employeeName = '';
            if (isset($this->context->employee)) {
                $employeeName = trim(
                    (string) ($this->context->employee->firstname ?? '') . ' '
                    . (string) ($this->context->employee->lastname ?? '')
                );
            }
            $result = $this->module->issueReplacementCoupon(
                $idCoupon,
                isset($this->context->employee->id) ? (int) $this->context->employee->id : 0,
                $employeeName,
                $sendEmail
            );
            $reissue = (array) ($result['reissue'] ?? []);
            $this->auditService()->log(
                'coupon_reissue',
                'coupon_reissue',
                (int) ($reissue['id_fabricssamples_coupon_reissue'] ?? 0),
                $coupon,
                $reissue,
                'Se emitió un cupón nuevo sin alterar el cupón original ni sus pedidos.',
                (int) $coupon['id_shop']
            );
            $this->confirmations[] = sprintf(
                $this->module->l('Cupón de reemplazo #%d emitido correctamente: %s.'),
                (int) ($reissue['reissue_number'] ?? 0),
                (string) ($reissue['code'] ?? '')
            );
            if (!empty($result['email_error'])) {
                $this->warnings[] = $this->module->l('El cupón se creó correctamente, pero el correo no pudo enviarse. Puede comunicar el nuevo código manualmente.');
            } elseif (!empty($result['email_sent'])) {
                $this->confirmations[] = $this->module->l('El nuevo código también se envió por correo al cliente.');
            }
        } catch (Throwable $exception) {
            $reference = bin2hex(random_bytes(6));
            PrestaShopLogger::addLog('fabricssamples coupon reissue [' . $reference . ']: ' . $exception->getMessage(), 3);
            $this->errors[] = sprintf(
                $this->module->l('No se pudo emitir el cupón de reemplazo. Referencia: %s.'),
                $reference
            );
        }
    }
    private function auditService(): AuditService
    {
        return new AuditService(new AuditRepository());
    }
}
