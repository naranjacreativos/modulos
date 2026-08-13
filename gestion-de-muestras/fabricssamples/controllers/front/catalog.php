<?php
class FabricssamplesCatalogModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        Tools::redirect($this->module->getSamplesControllerUrl($_GET));
    }
}
