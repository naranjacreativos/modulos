<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Presentation;

final class SampleNameFormatter
{
    public function __construct(private \Module $module)
    {
    }

    public function format(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $this->module->l('Muestra');
        }
        if (preg_match('/^muestra\s*[-–—:]?/iu', $name)) {
            return $name;
        }

        return $this->module->l('Muestra') . ' - ' . $name;
    }
}
