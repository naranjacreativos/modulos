<?php

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;
use NaranjaCreativos\FabricSamples\Security\AjaxRateLimiter;

class FabricssamplesAjaxModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function postProcess()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $this->assertPostRequest();
            $this->assertValidToken();
            $this->assertRateLimit();
            $action = (string) Tools::getValue('sample_action', 'add');

            if ($action === 'add') {
                $this->processAdd();
                return;
            }
            if ($action === 'remove') {
                $idCustomization = (int) Tools::getValue('id_customization');
                $this->assertCustomizationOwned($idCustomization);
                $this->module->removeSampleFromCart($idCustomization);
                $this->renderSuccess();
                return;
            }
            if ($action === 'update_quantity') {
                $idCustomization = (int) Tools::getValue('id_customization');
                $this->assertCustomizationOwned($idCustomization);
                $quantity = $this->module->updateSampleQuantity(
                    $idCustomization,
                    (string) Tools::getValue('direction')
                );
                $this->renderSuccess(['quantity' => $quantity]);
                return;
            }
            if ($action === 'bulk') {
                $this->processBulkAdd();
                return;
            }

            throw new PrestaShopException($this->module->l('Acción no válida.'));
        } catch (Throwable $exception) {
            $reference = bin2hex(random_bytes(6));
            PrestaShopLogger::addLog('fabricssamples ajax [' . $reference . ']: ' . $exception->getMessage(), 2);
            if (http_response_code() < 400) {
                http_response_code(400);
            }
            $this->ajaxRender(json_encode([
                'success' => false,
                'message' => sprintf($this->module->l('No se pudo completar la operación. Referencia: %s.'), $reference),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function assertPostRequest(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            throw new PrestaShopException('Método HTTP no permitido.');
        }
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
            http_response_code(400);
            throw new PrestaShopException('Cabecera AJAX no válida.');
        }
    }

    private function assertValidToken(): void
    {
        if (!Tools::isSubmit('token') || !hash_equals(Tools::getToken(false), (string) Tools::getValue('token'))) {
            http_response_code(403);
            throw new PrestaShopException($this->module->l('Token de seguridad no válido.'));
        }
    }

    private function assertRateLimit(): void
    {
        $configuration = new ModuleConfiguration();
        $perMinute = min(120, max(10, $configuration->getInt('AJAX_RATE_LIMIT_PER_MINUTE', 30)));
        $key = implode('|', [
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($this->context->cart->id ?? 0),
            (string) ($this->context->customer->id ?? 0),
        ]);
        try {
            (new AjaxRateLimiter())->assertAllowed($key, $perMinute, min(12, max(5, (int) ceil($perMinute / 6))));
        } catch (RuntimeException $exception) {
            http_response_code(429);
            throw $exception;
        }
    }

    private function processAdd(): void
    {
        $quantity = (int) Tools::getValue('quantity', 1);
        if ($quantity < 1 || $quantity > 100) {
            throw new PrestaShopException($this->module->l('Cantidad no válida.'));
        }
        $idCustomization = $this->module->addSampleToCart(
            (int) Tools::getValue('id_product'),
            (int) Tools::getValue('id_product_attribute', 0),
            $quantity
        );

        $line = $this->module->getCartSampleByCustomization($idCustomization, (int) ($this->context->cart->id ?? 0));
        $this->renderSuccess([
            'id_customization' => $idCustomization,
            'quantity' => (int) ($line['quantity'] ?? 1),
        ]);
    }

    private function processBulkAdd(): void
    {
        $ids = Tools::getValue('products', []);
        if (!is_array($ids) || $ids === []) {
            throw new PrestaShopException($this->module->l('Seleccione al menos una muestra.'));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) > 50) {
            throw new PrestaShopException($this->module->l('No se pueden procesar más de 50 muestras en una sola solicitud.'));
        }

        $added = 0;
        foreach ($ids as $idProduct) {
            $this->module->addSampleToCart($idProduct, 0, 1);
            ++$added;
        }

        $this->renderSuccess(['added' => $added]);
    }

    private function assertCustomizationOwned(int $idCustomization): void
    {
        $idCart = (int) ($this->context->cart->id ?? 0);
        if ($idCustomization <= 0 || $idCart <= 0
            || $this->module->getCartSampleByCustomization($idCustomization, $idCart) === []) {
            throw new PrestaShopException($this->module->l('La línea de muestra no pertenece al carrito actual.'));
        }
    }

    private function renderSuccess(array $extra = []): void
    {
        $this->ajaxRender(json_encode(array_merge([
            'success' => true,
            'cart_count' => Validate::isLoadedObject($this->context->cart) ? (int) $this->context->cart->nbProducts() : 0,
            'sample_count' => $this->module->getCartSampleTotal((int) ($this->context->cart->id ?? 0)),
            'cart_url' => $this->context->link->getPageLink('cart', true, null, ['action' => 'show']),
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
