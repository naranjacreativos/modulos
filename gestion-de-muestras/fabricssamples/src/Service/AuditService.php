<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Service;

use NaranjaCreativos\FabricSamples\Repository\AuditRepository;

final class AuditService
{
    public function __construct(private AuditRepository $repository)
    {
    }

    public function log(string $action, string $entityType, string|int $entityId, array $oldValue = [], array $newValue = [], string $note = '', ?int $idShop = null): bool
    {
        $context = \Context::getContext();
        $employee = $context->employee ?? null;
        $employeeName = '';
        $idEmployee = 0;
        if ($employee instanceof \Employee && \Validate::isLoadedObject($employee)) {
            $idEmployee = (int) $employee->id;
            $employeeName = trim((string) $employee->firstname . ' ' . (string) $employee->lastname);
        }
        $ip = class_exists('Tools') ? (string) \Tools::getRemoteAddr() : '';

        return $this->repository->insert([
            'id_shop' => $idShop ?? (int) ($context->shop->id ?? 0),
            'id_employee' => $idEmployee,
            'employee_name' => $employeeName,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'note' => $note,
            'ip_address' => $ip,
        ]);
    }
}
