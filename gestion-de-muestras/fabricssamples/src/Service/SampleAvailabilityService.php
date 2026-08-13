<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class SampleAvailabilityService
{
    public function __construct(private ModuleConfiguration $configuration)
    {
    }

    public function isAvailable(array $config, int $idProduct): bool
    {
        if (empty($config['available'])) {
            return false;
        }

        $mode = (string) ($config['stock_mode'] ?: $this->configuration->getString('STOCK_MODE', null, 'availability'));
        if ($mode === 'independent') {
            return (int) $config['sample_stock'] > 0;
        }
        if ($mode === 'product') {
            return \StockAvailable::getQuantityAvailableByProduct($idProduct) > 0;
        }
        if ($mode === 'product_minimum') {
            return \StockAvailable::getQuantityAvailableByProduct($idProduct) >= $this->configuration->getInt('STOCK_MINIMUM', 1);
        }

        return true;
    }
}
