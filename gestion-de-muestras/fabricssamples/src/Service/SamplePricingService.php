<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class SamplePricingService
{
    /** @var array<int,float> */
    private array $taxRateCache = [];
    private ?\Address $taxAddress = null;

    public function __construct(
        private \Context $context,
        private ModuleConfiguration $configuration
    ) {
    }

    public function getPrice(array $config, bool $taxIncluded): float
    {
        $priceTaxExcl = !empty($config['use_default_price'])
            ? $this->configuration->getFloat('DEFAULT_PRICE', 0.0)
            : (float) ($config['sample_price'] ?? 0.0);

        if (!$taxIncluded) {
            return $priceTaxExcl;
        }

        return $priceTaxExcl * (1 + ($this->getTaxRate($config) / 100));
    }

    public function getTaxRate(array $config): float
    {
        $taxMode = (string) ($config['tax_mode'] ?? 'inherit');
        if ($taxMode === 'specific' && (int) ($config['id_tax_rules_group'] ?? 0) > 0) {
            $idTaxRulesGroup = (int) $config['id_tax_rules_group'];
        } elseif ($this->configuration->getString('TAX_MODE', null, 'inherit') === 'global') {
            $idTaxRulesGroup = $this->configuration->getInt('GLOBAL_TAX_RULES_GROUP');
        } elseif ((int) ($config['inherited_tax_rules_group'] ?? 0) > 0) {
            $idTaxRulesGroup = (int) $config['inherited_tax_rules_group'];
        } else {
            $idTaxRulesGroup = (int) \Product::getIdTaxRulesGroupByIdProduct((int) $config['id_product'], $this->context);
        }

        if ($idTaxRulesGroup <= 0) {
            return 0.0;
        }

        if (array_key_exists($idTaxRulesGroup, $this->taxRateCache)) {
            return $this->taxRateCache[$idTaxRulesGroup];
        }

        return $this->taxRateCache[$idTaxRulesGroup] = (float) \TaxManagerFactory::getManager(
            $this->taxAddress(),
            $idTaxRulesGroup
        )
            ->getTaxCalculator()
            ->getTotalRate();
    }

    private function taxAddress(): \Address
    {
        if ($this->taxAddress instanceof \Address) {
            return $this->taxAddress;
        }
        $idAddress = isset($this->context->cart) ? (int) $this->context->cart->id_address_delivery : 0;
        $address = new \Address($idAddress);
        if (!\Validate::isLoadedObject($address)) {
            $address = new \Address();
            $address->id_country = (int) $this->context->country->id;
            $address->id_state = 0;
            $address->postcode = '0';
        }

        return $this->taxAddress = $address;
    }
}
