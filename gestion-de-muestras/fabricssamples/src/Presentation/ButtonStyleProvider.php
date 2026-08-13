<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Presentation;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class ButtonStyleProvider
{
    private ModuleConfiguration $configuration;

    public function __construct(ModuleConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function style(string $profile): string
    {
        $profile = strtoupper(trim($profile));
        $defaults = ModuleConfiguration::defaults();

        $background = $this->value($profile . '_BG', $defaults, '#3d3d3d');
        $textColor = $this->value($profile . '_TEXT_COLOR', $defaults, '#ffffff');
        $borderColor = $this->value($profile . '_BORDER_COLOR', $defaults, $background);
        $borderWidth = $this->intValue($profile . '_BORDER_WIDTH', $defaults, 1, 0, 20);
        $radius = $this->intValue($profile . '_RADIUS', $defaults, 0, 0, 100);
        $fontSize = $this->intValue($profile . '_FONT_SIZE', $defaults, 16, 8, 60);
        $fontWeight = $this->intValue($profile . '_FONT_WEIGHT', $defaults, 600, 300, 900);
        $paddingY = $this->intValue($profile . '_PADDING_Y', $defaults, 12, 0, 60);
        $paddingX = $this->intValue($profile . '_PADDING_X', $defaults, 18, 0, 100);
        $marginTop = $this->intValue($profile . '_MARGIN_TOP', $defaults, 0, 0, 100);
        $marginBottom = $this->intValue($profile . '_MARGIN_BOTTOM', $defaults, 0, 0, 100);
        $widthMode = $this->value($profile . '_WIDTH', $defaults, 'auto');
        $widthPx = $this->intValue($profile . '_WIDTH_PX', $defaults, 0, 0, 1200);
        $heightPx = $this->intValue($profile . '_HEIGHT_PX', $defaults, 0, 0, 300);
        if ($heightPx > 0) {
            $heightPx = max(16, $heightPx);
        }
        $width = $widthPx > 0 ? $widthPx . 'px' : ($widthMode === 'full' ? '100%' : 'auto');
        $height = $heightPx > 0 ? $heightPx . 'px' : 'auto';
        $effectivePaddingY = $heightPx > 0 ? 0 : $paddingY;

        $styles = [
            'display:inline-flex !important',
            'align-items:center !important',
            'justify-content:center !important',
            'box-sizing:border-box !important',
            'background-color:' . $background . ' !important',
            'color:' . $textColor . ' !important',
            'border:' . $borderWidth . 'px solid ' . $borderColor . ' !important',
            'border-radius:' . $radius . 'px !important',
            'font-size:' . $fontSize . 'px !important',
            'font-weight:' . $fontWeight . ' !important',
            'padding:' . $effectivePaddingY . 'px ' . $paddingX . 'px !important',
            'margin-top:' . $marginTop . 'px !important',
            'margin-bottom:' . $marginBottom . 'px !important',
            'width:' . $width . ' !important',
            'height:' . $height . ' !important',
            'max-width:100% !important',
            'line-height:1.25 !important',
            'text-align:center !important',
            'white-space:normal !important',
            'text-decoration:none !important',
            'cursor:pointer !important',
        ];
        if ($heightPx > 0) {
            // Themes and the public stylesheet may impose their own minimum height.
            // Pin all dimensions so this value is the final rendered height.
            $styles[] = 'min-height:' . $height . ' !important';
            $styles[] = 'max-height:' . $height . ' !important';
        }

        return implode(';', $styles) . ';';
    }

    private function value(string $key, array $defaults, string $fallback): string
    {
        return $this->configuration->getString($key, null, (string) ($defaults[$key] ?? $fallback));
    }

    private function intValue(string $key, array $defaults, int $fallback, int $min, int $max): int
    {
        $value = $this->configuration->getInt($key, (int) ($defaults[$key] ?? $fallback));
        return min($max, max($min, $value));
    }
}
