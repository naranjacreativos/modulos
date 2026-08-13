<?php
function upgrade_module_2_6_4($module)
{
    foreach (['actionObjectOrderDetailAddAfter', 'actionValidateOrder', 'displayCustomerAccount', 'displayMyAccountBlock'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }
    return true;
}
