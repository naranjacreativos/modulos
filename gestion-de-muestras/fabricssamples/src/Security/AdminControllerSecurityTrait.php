<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Security;

trait AdminControllerSecurityTrait
{
    private function guardAdminAction(string $permission, bool $requirePost = true): bool
    {
        if (!$this->access($permission)) {
            $this->errors[] = $this->module->l('No tiene permiso para realizar esta operación.');
            return false;
        }

        if ($requirePost && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->errors[] = $this->module->l('Esta operación solo admite solicitudes POST.');
            return false;
        }

        $submittedToken = (string) \Tools::getValue('token', '');
        $expectedToken = (string) \Tools::getAdminTokenLite($this->controller_name);
        if ($submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
            $this->errors[] = $this->module->l('El token de seguridad no es válido. Recargue la página e inténtelo de nuevo.');
            return false;
        }

        return true;
    }
}
