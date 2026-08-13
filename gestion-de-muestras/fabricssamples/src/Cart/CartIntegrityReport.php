<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Cart;

final class CartIntegrityReport
{
    /** @var list<string> */
    private array $errors = [];
    private int $repaired = 0;
    private int $removed = 0;

    public function addRepair(): void
    {
        ++$this->repaired;
    }

    public function addRemoval(): void
    {
        ++$this->removed;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function repairedCount(): int
    {
        return $this->repaired;
    }

    public function removedCount(): int
    {
        return $this->removed;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
