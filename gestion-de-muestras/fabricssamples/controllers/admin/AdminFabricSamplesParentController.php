<?php
class AdminFabricSamplesParentController extends ModuleAdminController
{
    public function initContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminFabricSamples'));
    }
}
