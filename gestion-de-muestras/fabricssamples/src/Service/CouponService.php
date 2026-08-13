<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Domain\CouponState;
use NaranjaCreativos\FabricSamples\Domain\CouponValuePolicy;
use NaranjaCreativos\FabricSamples\Domain\CouponReactivationPolicy;
use NaranjaCreativos\FabricSamples\Domain\CouponReissuePolicy;
use NaranjaCreativos\FabricSamples\Infrastructure\DatabaseLock;
use NaranjaCreativos\FabricSamples\Presentation\PriceFormatter;
use NaranjaCreativos\FabricSamples\Repository\CouponRepository;
use NaranjaCreativos\FabricSamples\Repository\CouponReissueRepository;

final class CouponService
{
    public function __construct(
        private \Module $module,
        private ModuleConfiguration $configuration,
        private CouponRepository $repository,
        private CouponReissueRepository $reissueRepository,
        private CouponValuePolicy $valuePolicy,
        private CouponReactivationPolicy $reactivationPolicy,
        private CouponReissuePolicy $reissuePolicy,
        private DatabaseLock $databaseLock
    ) {
    }

    public function generateForOrder(\Order $order, array $samples): array
    {
        if (!$this->configuration->getBool('COUPON_ENABLED') || $samples === [] || (int) $order->id_customer <= 0) {
            return [];
        }
        if ($this->repository->isSuppressed((int) $order->id)) {
            return [];
        }

        $existing = $this->repository->findByOrder((int) $order->id);
        if ($existing !== [] && (!empty($existing['deleted_permanently']) || ($existing['state'] ?? '') === CouponState::DELETED_PERMANENTLY)) {
            return [];
        }
        if ($existing !== []) {
            $rule = new \CartRule((int) ($existing['id_cart_rule'] ?? 0));
            if (\Validate::isLoadedObject($rule)) {
                return $existing;
            }
            $this->repository->deleteByOrder((int) $order->id);
        }

        $calculation = $this->calculate($samples);
        if ($calculation['discount_value'] <= 0.0) {
            return [];
        }

        $currency = new \Currency((int) $order->id_currency);
        $dateFrom = date('Y-m-d H:i:s');
        $validDays = min(3650, max(1, (int) $this->configuration->get('COUPON_VALID_DAYS')));
        $dateTo = date('Y-m-d H:i:s', strtotime('+' . $validDays . ' days'));
        $code = $this->generateCode($this->configuration->getString('COUPON_CODE_PREFIX', null, 'MUESTRA'));

        $rule = new \CartRule();
        $rule->id_customer = (int) $order->id_customer;
        $rule->date_from = $dateFrom;
        $rule->date_to = $dateTo;
        $rule->description = sprintf('fabricssamples order %d', (int) $order->id);
        $rule->quantity = 1;
        $rule->quantity_per_user = 1;
        $rule->priority = 1;
        $rule->partial_use = $this->configuration->getBool('COUPON_PARTIAL_USE');
        $rule->code = $code;
        $rule->minimum_amount = max(0.0, (float) $this->configuration->get('COUPON_MINIMUM_ORDER'));
        $rule->minimum_amount_tax = 1;
        $rule->minimum_amount_currency = (int) $order->id_currency;
        $rule->minimum_amount_shipping = 0;
        $rule->country_restriction = 0;
        $rule->carrier_restriction = 0;
        $rule->group_restriction = 0;
        $rule->cart_rule_restriction = 0;
        $rule->product_restriction = $this->configuration->getBool('COUPON_LIMIT_TO_PRODUCTS') && $calculation['product_ids'] !== [];
        $rule->shop_restriction = 1;
        $rule->id_shop_list = [(int) $order->id_shop];
        $rule->free_shipping = 0;
        $rule->reduction_percent = 0;
        $rule->reduction_amount = $calculation['discount_value'];
        $rule->reduction_tax = 1;
        $rule->reduction_currency = (int) $order->id_currency;
        $rule->reduction_product = 0;
        $rule->reduction_exclude_special = 0;
        $rule->highlight = 1;
        $rule->active = 1;
        foreach (\Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $rule->name[$idLang] = $this->configuration->getString('COUPON_NAME', $idLang, 'Descuento por muestras de tejidos');
        }

        if (!$rule->add()) {
            throw new \RuntimeException('No se pudo crear la regla de carrito.');
        }

        try {
            if (!$this->ensureShopRestriction((int) $rule->id, (int) $order->id_shop)) {
                throw new \RuntimeException('No se pudo limitar el cupón a la tienda.');
            }
            if ($rule->product_restriction) {
                $this->addProductRestriction((int) $rule->id, $calculation['product_ids']);
            }
        } catch (\Throwable $exception) {
            $rule->delete();
            throw $exception;
        }

        $data = [
            'id_order' => (int) $order->id,
            'id_customer' => (int) $order->id_customer,
            'id_shop' => (int) $order->id_shop,
            'id_cart_rule' => (int) $rule->id,
            'code' => pSQL($code),
            'discount_mode' => pSQL($calculation['mode']),
            'discount_value' => $calculation['discount_value'],
            'sample_total_tax_incl' => $calculation['sample_total'],
            'minimum_order' => (float) $rule->minimum_amount,
            'limited_to_products' => (int) $rule->product_restriction,
            'product_ids' => pSQL(implode(',', $calculation['product_ids'])),
            'email_sent' => 0,
            'state' => CouponState::AVAILABLE,
            'state_reason' => pSQL('created'),
            'date_state' => date('Y-m-d H:i:s'),
            'last_order_state' => (int) $order->current_state,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ];
        if (!$this->repository->insert($data)) {
            $rule->delete();
            throw new \RuntimeException('No se pudo registrar el cupón del módulo.');
        }

        $coupon = $this->repository->findByOrder((int) $order->id);
        if ($coupon !== [] && $this->configuration->getBool('COUPON_SEND_EMAIL')) {
            $this->sendEmail($order, $coupon, $currency);
        }

        return $coupon;
    }

    public function recalculateForOrder(\Order $order, array $samples, array $coupon): array
    {
        if ($coupon === [] || $samples === [] || !empty($coupon['deleted_permanently'])) {
            return $coupon;
        }
        $rule = new \CartRule((int) ($coupon['id_cart_rule'] ?? 0));
        if (!\Validate::isLoadedObject($rule) || $this->isUsed($coupon)) {
            return $coupon;
        }

        $calculation = $this->calculate($samples);
        if ($calculation['discount_value'] <= 0.0) {
            return $coupon;
        }
        $rule->reduction_amount = $calculation['discount_value'];
        $rule->minimum_amount = max(0.0, (float) $this->configuration->get('COUPON_MINIMUM_ORDER'));
        if (!$rule->update()) {
            throw new \RuntimeException('No se pudo recalcular la regla de carrito del cupón.');
        }

        $this->repository->updateById((int) $coupon['id_fabricssamples_coupon'], [
            'discount_mode' => pSQL($calculation['mode']),
            'discount_value' => $calculation['discount_value'],
            'sample_total_tax_incl' => $calculation['sample_total'],
            'minimum_order' => (float) $rule->minimum_amount,
            'product_ids' => pSQL(implode(',', $calculation['product_ids'])),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return $this->repository->findByOrder((int) $order->id);
    }

    public function setActive(array $coupon, bool $active): bool
    {
        $idCartRule = (int) ($coupon['id_cart_rule'] ?? 0);
        if ($idCartRule <= 0) {
            return false;
        }
        $rule = new \CartRule($idCartRule);
        if (!\Validate::isLoadedObject($rule)) {
            return false;
        }
        if ((bool) $rule->active === $active) {
            return true;
        }
        $rule->active = $active ? 1 : 0;
        return (bool) $rule->update();
    }

    public function isUsed(array $coupon): bool
    {
        $idCartRule = (int) ($coupon['id_cart_rule'] ?? 0);
        return $this->reactivationPolicy->isConsumed(
            $this->repository->usageCount($idCartRule),
            (int) ($coupon['reactivation_count'] ?? 0)
        );
    }

    /** @return array{reissue:array<string,mixed>,email_sent:bool,email_error:bool} */
    public function issueReplacement(
        array $coupon,
        int $idEmployee,
        string $employeeName,
        bool $sendEmail
    ): array {
        $idCoupon = (int) ($coupon['id_fabricssamples_coupon'] ?? 0);
        $idOriginalCartRule = (int) ($coupon['id_cart_rule'] ?? 0);
        if ($idCoupon <= 0 || $idOriginalCartRule <= 0) {
            throw new \RuntimeException('El cupón no tiene una regla de carrito válida.');
        }

        $created = $this->databaseLock->synchronized(
            'coupon-reissue:' . $idCoupon,
            function () use ($idCoupon, $idOriginalCartRule, $idEmployee, $employeeName, $sendEmail): array {
                $db = \Db::getInstance();
                $createdRuleId = 0;
                $createdCode = '';
                if (!$db->execute('START TRANSACTION')) {
                    throw new \RuntimeException('No se pudo iniciar la emisión del cupón de reemplazo.');
                }
                try {
                    $current = $this->repository->findById($idCoupon);
                    if ($current === [] || !empty($current['deleted_permanently'])) {
                        throw new \RuntimeException('El cupón original no existe o fue eliminado.');
                    }
                    $originalRule = new \CartRule($idOriginalCartRule);
                    if (!\Validate::isLoadedObject($originalRule)) {
                        throw new \RuntimeException('La regla de carrito original ya no existe.');
                    }

                    $reissues = $this->refreshReissueStates($this->reissueRepository->findByCoupon($idCoupon));
                    $hasPending = false;
                    foreach ($reissues as $reissue) {
                        if (($reissue['computed_state'] ?? '') === CouponState::AVAILABLE) {
                            $hasPending = true;
                            break;
                        }
                    }
                    $usageCount = $this->repository->usageCount($idOriginalCartRule);
                    if (!$this->reissuePolicy->canIssue($current, $usageCount, $hasPending)) {
                        if ($hasPending) {
                            throw new \RuntimeException('Ya existe un cupón de reemplazo pendiente para este cupón original.');
                        }
                        throw new \RuntimeException('Solo se puede emitir un reemplazo cuando el cupón original consta como usado.');
                    }

                    $number = $this->reissueRepository->nextNumber($idCoupon);
                    $now = date('Y-m-d H:i:s');
                    $validDays = min(3650, max(1, $this->configuration->getInt('COUPON_VALID_DAYS', 60)));
                    $dateTo = date('Y-m-d H:i:s', strtotime('+' . $validDays . ' days'));
                    $createdCode = $this->generateReplacementCode((string) $current['code'], $number);
                    $replacementRule = $this->cloneRuleForReissue(
                        $originalRule,
                        $current,
                        $number,
                        $createdCode,
                        $now,
                        $dateTo
                    );
                    if (!$replacementRule->add()) {
                        throw new \RuntimeException('No se pudo crear la nueva regla de carrito.');
                    }
                    $createdRuleId = (int) $replacementRule->id;
                    $this->copyConditionsOrFail($idOriginalCartRule, $createdRuleId);
                    if (!$this->ensureShopRestriction($createdRuleId, (int) $current['id_shop'])) {
                        throw new \RuntimeException('No se pudo limitar el cupón de reemplazo a su tienda.');
                    }

                    if (!$this->reissueRepository->insert([
                        'id_fabricssamples_coupon' => $idCoupon,
                        'id_order' => (int) $current['id_order'],
                        'id_customer' => (int) $current['id_customer'],
                        'id_shop' => (int) $current['id_shop'],
                        'id_original_cart_rule' => $idOriginalCartRule,
                        'id_source_cart_rule' => $idOriginalCartRule,
                        'id_cart_rule' => $createdRuleId,
                        'reissue_number' => $number,
                        'original_code' => pSQL((string) $current['code']),
                        'code' => pSQL($createdCode),
                        'state' => CouponState::AVAILABLE,
                        'pending_guard' => $idCoupon,
                        'email_requested' => $sendEmail ? 1 : 0,
                        'email_sent' => 0,
                        'id_employee' => max(0, $idEmployee),
                        'employee_name' => pSQL(substr(trim($employeeName), 0, 190)),
                        'date_from' => $now,
                        'date_to' => $dateTo,
                        'date_add' => $now,
                        'date_upd' => $now,
                    ])) {
                        throw new \RuntimeException('No se pudo registrar el historial de reemisión.');
                    }
                    $idReissue = (int) $db->Insert_ID();
                    if ($idReissue <= 0 || !$db->execute('COMMIT')) {
                        throw new \RuntimeException('No se pudo confirmar la emisión del cupón de reemplazo.');
                    }

                    return $this->reissueRepository->findById($idReissue);
                } catch (\Throwable $exception) {
                    $db->execute('ROLLBACK');
                    $this->removeFailedRule($createdRuleId, $createdCode);
                    throw $exception;
                }
            }
        );

        $emailSent = false;
        $emailError = false;
        if ($sendEmail) {
            $emailSent = $this->sendReissueEmail($created);
            $emailError = !$emailSent;
            if ($emailSent) {
                $this->reissueRepository->markEmailSent((int) $created['id_fabricssamples_coupon_reissue']);
                $created['email_sent'] = 1;
            }
        }

        return ['reissue' => $created, 'email_sent' => $emailSent, 'email_error' => $emailError];
    }

    /** @param list<array<string,mixed>> $reissues
     *  @return list<array<string,mixed>>
     */
    public function refreshReissueStates(array $reissues): array
    {
        foreach ($reissues as &$reissue) {
            $ruleExists = array_key_exists('cart_rule_active', $reissue) && $reissue['cart_rule_active'] !== null;
            $usageCount = max(0, (int) ($reissue['usage_count'] ?? 0));
            $dateTo = (string) ($reissue['cart_rule_date_to'] ?? $reissue['date_to'] ?? '');
            $state = $this->reissuePolicy->status(
                $ruleExists,
                $usageCount,
                !empty($reissue['cart_rule_active']),
                $dateTo
            );
            $pending = $this->reissuePolicy->isPending($state);
            $dateUsed = $usageCount > 0 ? (string) ($reissue['native_date_used'] ?? $reissue['date_used'] ?? '') : null;
            $needsUpdate = (string) ($reissue['state'] ?? '') !== $state
                || ((int) ($reissue['pending_guard'] ?? 0) > 0) !== $pending
                || ($usageCount > 0 && (string) ($reissue['date_used'] ?? '') !== (string) $dateUsed);
            if ($needsUpdate && !$this->reissueRepository->updateState(
                (int) $reissue['id_fabricssamples_coupon_reissue'],
                $state,
                $dateUsed,
                $pending
            )) {
                throw new \RuntimeException('No se pudo sincronizar el estado del historial de reemisiones.');
            }
            $reissue['computed_state'] = $state;
            $reissue['state'] = $state;
            $reissue['date_used'] = $dateUsed;
            $reissue['pending_guard'] = $pending ? (int) $reissue['id_fabricssamples_coupon'] : null;
        }
        unset($reissue);

        return $reissues;
    }

    private function cloneRuleForReissue(
        \CartRule $source,
        array $coupon,
        int $number,
        string $code,
        string $dateFrom,
        string $dateTo
    ): \CartRule {
        $rule = new \CartRule();
        foreach ([
            'priority', 'partial_use', 'minimum_amount', 'minimum_amount_tax',
            'minimum_amount_currency', 'minimum_amount_shipping', 'country_restriction',
            'carrier_restriction', 'group_restriction', 'cart_rule_restriction',
            'product_restriction', 'shop_restriction', 'free_shipping', 'reduction_percent',
            'reduction_amount', 'reduction_tax', 'reduction_currency', 'reduction_product',
            'reduction_exclude_special', 'gift_product', 'gift_product_attribute', 'highlight',
            'id_cart_rule_type',
        ] as $field) {
            if (property_exists($source, $field) && property_exists($rule, $field)) {
                $rule->{$field} = $source->{$field};
            }
        }
        $rule->id_customer = (int) ($coupon['id_customer'] ?? $source->id_customer);
        $rule->date_from = $dateFrom;
        $rule->date_to = $dateTo;
        $rule->description = sprintf(
            'fabricssamples replacement #%d for order %d (source cart rule %d)',
            $number,
            (int) ($coupon['id_order'] ?? 0),
            (int) $source->id
        );
        $rule->quantity = 1;
        $rule->quantity_per_user = 1;
        $rule->code = $code;
        $rule->active = 1;
        $sourceNames = is_array($source->name) ? $source->name : [];
        foreach (\Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $baseName = trim((string) ($sourceNames[$idLang] ?? ''));
            if ($baseName === '') {
                $baseName = $this->configuration->getString('COUPON_NAME', $idLang, 'Descuento por muestras de tejidos');
            }
            $rule->name[$idLang] = substr($baseName . ' - reemisión ' . $number, 0, 254);
        }

        return $rule;
    }

    private function copyConditionsOrFail(int $sourceId, int $destinationId): void
    {
        $db = \Db::getInstance();
        foreach ([
            'cart_rule_shop' => 'id_shop',
            'cart_rule_carrier' => 'id_carrier',
            'cart_rule_group' => 'id_group',
            'cart_rule_country' => 'id_country',
        ] as $table => $column) {
            if (!$db->execute(
                'INSERT IGNORE INTO `' . _DB_PREFIX_ . bqSQL($table) . '` (`id_cart_rule`,`' . bqSQL($column) . '`)'
                . ' SELECT ' . $destinationId . ',`' . bqSQL($column) . '` FROM `' . _DB_PREFIX_ . bqSQL($table) . '`'
                . ' WHERE id_cart_rule=' . $sourceId
            )) {
                throw new \RuntimeException('No se pudieron copiar las restricciones del cupón (' . $table . ').');
            }
        }

        if (!$db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cart_rule_combination` (`id_cart_rule_1`,`id_cart_rule_2`)'
            . ' SELECT ' . $destinationId . ',IF(id_cart_rule_1<>' . $sourceId . ',id_cart_rule_1,id_cart_rule_2)'
            . ' FROM `' . _DB_PREFIX_ . 'cart_rule_combination`'
            . ' WHERE id_cart_rule_1=' . $sourceId . ' OR id_cart_rule_2=' . $sourceId
        )) {
            throw new \RuntimeException('No se pudieron copiar las incompatibilidades del cupón.');
        }

        $groups = $db->executeS(
            'SELECT id_product_rule_group,quantity FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_group`'
            . ' WHERE id_cart_rule=' . $sourceId
        );
        if (!is_array($groups)) {
            throw new \RuntimeException('No se pudieron leer las restricciones de productos del cupón.');
        }
        foreach ($groups as $group) {
            if (!$db->insert('cart_rule_product_rule_group', [
                'id_cart_rule' => $destinationId,
                'quantity' => max(1, (int) ($group['quantity'] ?? 1)),
            ])) {
                throw new \RuntimeException('No se pudo copiar un grupo de productos del cupón.');
            }
            $destinationGroupId = (int) $db->Insert_ID();
            $rules = $db->executeS(
                'SELECT id_product_rule,type FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule`'
                . ' WHERE id_product_rule_group=' . (int) $group['id_product_rule_group']
            );
            if (!is_array($rules)) {
                throw new \RuntimeException('No se pudieron leer las reglas de productos del cupón.');
            }
            foreach ($rules as $productRule) {
                if (!$db->insert('cart_rule_product_rule', [
                    'id_product_rule_group' => $destinationGroupId,
                    'type' => pSQL((string) ($productRule['type'] ?? 'products')),
                ])) {
                    throw new \RuntimeException('No se pudo copiar una regla de productos del cupón.');
                }
                $destinationRuleId = (int) $db->Insert_ID();
                $values = $db->executeS(
                    'SELECT id_item FROM `' . _DB_PREFIX_ . 'cart_rule_product_rule_value`'
                    . ' WHERE id_product_rule=' . (int) $productRule['id_product_rule']
                );
                if (!is_array($values)) {
                    throw new \RuntimeException('No se pudieron leer los productos limitados del cupón.');
                }
                foreach ($values as $value) {
                    if (!$db->insert('cart_rule_product_rule_value', [
                        'id_product_rule' => $destinationRuleId,
                        'id_item' => (int) ($value['id_item'] ?? 0),
                    ])) {
                        throw new \RuntimeException('No se pudo copiar un producto limitado del cupón.');
                    }
                }
            }
        }
    }

    private function generateReplacementCode(string $originalCode, int $number): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', $originalCode) ?: 'MUESTRA');
        $base = substr($base, 0, 220);
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            try {
                $suffix = strtoupper(bin2hex(random_bytes(4)));
            } catch (\Throwable) {
                $suffix = strtoupper(substr(sha1(uniqid('', true)), 0, 8));
            }
            $code = $base . '-R' . max(1, $number) . '-' . $suffix;
            if (!\CartRule::getIdByCode($code)) {
                return $code;
            }
        }

        throw new \RuntimeException('No se pudo generar un código de reemplazo único.');
    }

    private function removeFailedRule(int $idCartRule, string $expectedCode): void
    {
        if ($idCartRule <= 0) {
            return;
        }
        try {
            $rule = new \CartRule($idCartRule);
            if (\Validate::isLoadedObject($rule) && (string) $rule->code === $expectedCode) {
                $rule->delete();
            }
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                'fabricssamples: no se pudo limpiar una reemisión fallida (' . $idCartRule . '): ' . $exception->getMessage(),
                3
            );
        }
    }

    private function sendReissueEmail(array $reissue): bool
    {
        try {
            $order = new \Order((int) ($reissue['id_order'] ?? 0));
            $customer = new \Customer((int) ($reissue['id_customer'] ?? 0));
            if (!\Validate::isLoadedObject($order)
                || !\Validate::isLoadedObject($customer)
                || !\Validate::isEmail($customer->email)) {
                return false;
            }
            $currency = new \Currency((int) $order->id_currency);
            $subject = $this->configuration->getString(
                'COUPON_EMAIL_SUBJECT',
                (int) $order->id_lang,
                'Tu descuento por las muestras de tejidos'
            );
            $originalCoupon = $this->repository->findById((int) ($reissue['id_fabricssamples_coupon'] ?? 0));
            $mailVariables = array_merge(
                $this->couponEmailPresentationVariables(
                    $order,
                    $currency,
                    $originalCoupon !== [] ? $originalCoupon : $reissue
                ),
                [
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{order_reference}' => $order->reference,
                    '{original_coupon_code}' => (string) ($reissue['original_code'] ?? ''),
                    '{coupon_code}' => (string) ($reissue['code'] ?? ''),
                    '{coupon_value}' => PriceFormatter::format((float) ($reissue['discount_value'] ?? 0), $currency),
                    '{date_to}' => \Tools::displayDate((string) ($reissue['date_to'] ?? '')),
                    '{reissue_number}' => (string) ((int) ($reissue['reissue_number'] ?? 0)),
                ]
            );
            return (bool) \Mail::Send(
                (int) $order->id_lang,
                'fabricssamples_coupon_reissue',
                $subject,
                $mailVariables,
                $customer->email,
                trim($customer->firstname . ' ' . $customer->lastname),
                null,
                null,
                null,
                null,
                $this->module->getLocalPath() . 'mails/'
            );
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                'fabricssamples: no se pudo enviar el cupón de reemplazo: ' . $exception->getMessage(),
                2
            );
            return false;
        }
    }

    /** @return array{sample_total:float,discount_value:float,mode:string,product_ids:list<int>} */
    private function calculate(array $samples): array
    {
        $sampleTotal = 0.0;
        $unitPrices = [];
        $productIds = [];
        foreach ($samples as $sample) {
            $quantity = max(0, (int) ($sample['quantity'] ?? 0));
            if ($quantity <= 0) {
                continue;
            }
            $lineTotal = (float) ($sample['total_price_tax_incl'] ?? 0);
            $unitPrice = (float) ($sample['unit_price_tax_incl'] ?? 0);
            if ($unitPrice <= 0.0 && $lineTotal > 0.0) {
                $unitPrice = $lineTotal / $quantity;
            }
            if ($lineTotal <= 0.0 && $unitPrice > 0.0) {
                $lineTotal = $unitPrice * $quantity;
            }
            $sampleTotal += max(0.0, $lineTotal);
            if ($unitPrice > 0.0) {
                $unitPrices[] = $unitPrice;
            }
            $idProduct = (int) ($sample['id_product'] ?? 0);
            if ($idProduct > 0) {
                $productIds[] = $idProduct;
            }
        }
        $productIds = array_values(array_unique($productIds));
        $cheapestUnit = $unitPrices !== [] ? min($unitPrices) : 0.0;
        $mostExpensiveUnit = $unitPrices !== [] ? max($unitPrices) : 0.0;
        $mode = $this->configuration->getString('COUPON_VALUE_MODE', null, 'full');
        $discountValue = \Tools::ps_round($this->valuePolicy->calculate(
            $sampleTotal,
            $mode,
            (float) $this->configuration->get('COUPON_SAMPLE_PERCENT'),
            (float) $this->configuration->get('COUPON_FIXED_AMOUNT'),
            $cheapestUnit,
            $mostExpensiveUnit
        ), 6);

        return [
            'sample_total' => max(0.0, $sampleTotal),
            'discount_value' => max(0.0, $discountValue),
            'mode' => $mode,
            'product_ids' => $productIds,
        ];
    }

    private function ensureShopRestriction(int $idCartRule, int $idShop): bool
    {
        if ((bool) \Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_rule_shop`'
            . ' WHERE id_cart_rule=' . $idCartRule . ' AND id_shop=' . $idShop
        )) {
            return true;
        }
        return \Db::getInstance()->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cart_rule_shop` (`id_cart_rule`,`id_shop`)'
            . ' VALUES (' . $idCartRule . ',' . $idShop . ')'
        );
    }

    private function addProductRestriction(int $idCartRule, array $productIds): void
    {
        if (!\Db::getInstance()->insert('cart_rule_product_rule_group', [
            'id_cart_rule' => $idCartRule,
            'quantity' => 1,
        ])) {
            throw new \RuntimeException('No se pudo crear el grupo de restricción del cupón.');
        }
        $idGroup = (int) \Db::getInstance()->Insert_ID();
        if (!\Db::getInstance()->insert('cart_rule_product_rule', [
            'id_product_rule_group' => $idGroup,
            'type' => 'products',
        ])) {
            throw new \RuntimeException('No se pudo crear la restricción de productos del cupón.');
        }
        $idRule = (int) \Db::getInstance()->Insert_ID();
        foreach ($productIds as $idProduct) {
            if (!\Db::getInstance()->insert('cart_rule_product_rule_value', [
                'id_product_rule' => $idRule,
                'id_item' => (int) $idProduct,
            ])) {
                throw new \RuntimeException('No se pudo asociar un tejido al cupón.');
            }
        }
    }

    private function generateCode(string $prefix): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix) ?: 'MUESTRA');
        $prefix = substr($prefix, 0, 20);
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            try {
                $suffix = strtoupper(bin2hex(random_bytes(5)));
            } catch (\Throwable) {
                $suffix = strtoupper(substr(sha1(uniqid('', true)), 0, 10));
            }
            $code = $prefix . '-' . $suffix;
            if (!\CartRule::getIdByCode($code)) {
                return $code;
            }
        }
        throw new \RuntimeException('No se pudo generar un código de cupón único.');
    }

    /** @return array<string,string> */
    private function couponEmailPresentationVariables(\Order $order, \Currency $currency, array $coupon): array
    {
        $idLang = (int) $order->id_lang;
        $isoCode = strtolower((string) \Language::getIsoById($idLang));
        $isSpanish = $isoCode === 'es';

        $minimumOrder = array_key_exists('minimum_order', $coupon)
            ? max(0.0, (float) $coupon['minimum_order'])
            : max(0.0, (float) $this->configuration->get('COUPON_MINIMUM_ORDER'));
        $minimumOrderText = $minimumOrder > 0.0
            ? PriceFormatter::format($minimumOrder, $currency)
            : ($isSpanish ? 'Sin compra mínima' : 'No minimum order');

        $limitedToProducts = array_key_exists('limited_to_products', $coupon)
            ? !empty($coupon['limited_to_products'])
            : $this->configuration->getBool('COUPON_LIMIT_TO_PRODUCTS');
        $restrictionText = $limitedToProducts
            ? ($isSpanish
                ? 'Solo puede aplicarse a los tejidos de los que solicitaste muestras.'
                : 'It can only be applied to the fabrics for which you requested samples.')
            : ($isSpanish
                ? 'Puede aplicarse a los productos de la tienda, respetando el resto de condiciones del cupón.'
                : 'It can be applied to store products, subject to the other coupon conditions.');

        $accentColor = $this->configuration->getString('PAGE_ACCENT_COLOR', null, '#202020');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor)) {
            $accentColor = '#202020';
        }
        $red = hexdec(substr($accentColor, 1, 2));
        $green = hexdec(substr($accentColor, 3, 2));
        $blue = hexdec(substr($accentColor, 5, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
        $accentTextColor = $brightness >= 170 ? '#111111' : '#ffffff';

        $shopName = trim((string) \Configuration::get('PS_SHOP_NAME', null, null, (int) $order->id_shop));
        if ($shopName === '') {
            $shopName = $isSpanish ? 'Tienda online' : 'Online store';
        }

        $shopUrl = rtrim((string) \Tools::getShopDomainSsl(true) . __PS_BASE_URI__, '/') . '/';

        return [
            '{shop_name}' => $shopName,
            '{shop_url}' => $shopUrl,
            '{accent_color}' => $accentColor,
            '{accent_text_color}' => $accentTextColor,
            '{minimum_order}' => $minimumOrderText,
            '{restriction_text}' => $restrictionText,
        ];
    }

    private function sendEmail(\Order $order, array $coupon, \Currency $currency): void
    {
        try {
            $customer = new \Customer((int) $order->id_customer);
            if (!\Validate::isLoadedObject($customer) || !\Validate::isEmail($customer->email)) {
                return;
            }
            $idLang = (int) $order->id_lang;
            $subject = $this->configuration->getString('COUPON_EMAIL_SUBJECT', $idLang, 'Tu descuento por las muestras de tejidos');
            $mailVariables = array_merge(
                $this->couponEmailPresentationVariables($order, $currency, $coupon),
                [
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{order_reference}' => $order->reference,
                    '{coupon_code}' => (string) $coupon['code'],
                    '{coupon_value}' => PriceFormatter::format((float) $coupon['discount_value'], $currency),
                    '{date_to}' => \Tools::displayDate((string) $coupon['date_to']),
                ]
            );
            $sent = \Mail::Send(
                $idLang,
                'fabricssamples_coupon',
                $subject,
                $mailVariables,
                $customer->email,
                trim($customer->firstname . ' ' . $customer->lastname),
                null,
                null,
                null,
                null,
                $this->module->getLocalPath() . 'mails/'
            );
            if ($sent) {
                $this->repository->markEmailSent((int) $order->id);
            }
        } catch (\Throwable $exception) {
            \PrestaShopLogger::addLog(
                'fabricssamples: no se pudo enviar el correo del cupón del pedido ' . (int) $order->id . ': ' . $exception->getMessage(),
                2,
                null,
                'Order',
                (int) $order->id,
                true
            );
        }
    }
}
