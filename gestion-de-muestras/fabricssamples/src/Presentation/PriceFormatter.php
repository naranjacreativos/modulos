<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Presentation;

final class PriceFormatter
{
    /**
     * @param array{iso_code?: string}|int|string|\Currency|null $currency
     */
    public static function format(float $price, $currency = null, ?\Context $context = null): string
    {
        $context = $context ?: \Context::getContext();
        $currency = $currency ?: $context->currency;

        if (is_int($currency) || (is_string($currency) && ctype_digit($currency))) {
            $currency = \Currency::getCurrencyInstance((int) $currency);
        }

        if (is_array($currency)) {
            $isoCode = (string) ($currency['iso_code'] ?? '');
        } elseif (is_object($currency)) {
            $isoCode = (string) ($currency->iso_code ?? '');
        } else {
            $isoCode = '';
        }

        if ($isoCode === '') {
            $isoCode = (string) $context->currency->iso_code;
        }

        return \Tools::getContextLocale($context)->formatPrice($price, $isoCode);
    }
}
