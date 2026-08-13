<?php

use NaranjaCreativos\FabricSamples\Service\ImageSnapshotService;

class FabricssamplesSnapshotModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $guestAllowed = true;

    public function initContent(): void
    {
        $filename = basename((string) Tools::getValue('file'));
        $service = new ImageSnapshotService($this->module);
        $path = $service->resolve($filename);
        if ($path === '' || !$this->isAuthorized($service, $filename)) {
            http_response_code(404);
            exit;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="snapshot.jpg"');
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function isAuthorized(ImageSnapshotService $service, string $filename): bool
    {
        if ($service->validSignature(
            $filename,
            (int) Tools::getValue('expires'),
            (string) Tools::getValue('signature')
        )) {
            return true;
        }
        $idCustomer = (int) ($this->context->customer->id ?? 0);
        if ($idCustomer <= 0) {
            return false;
        }
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'fabricssamples_order`'
            . " WHERE id_customer=" . $idCustomer
            . " AND image_snapshot='" . pSQL('private/orders/' . $filename) . "'"
        );
    }
}
