<div class="panel">
  <div class="panel-heading"><i class="icon-pencil"></i> {l s='Editar cupón de muestras' mod='fabricssamples'}</div>
  <form method="post" action="{$fs_edit_action|escape:'htmlall':'UTF-8'}" class="form-horizontal">
    <input type="hidden" name="id_fabricssamples_coupon" value="{$fs_edit_coupon.id_fabricssamples_coupon|intval}">
    <div class="form-group"><label class="control-label col-lg-3">{l s='Pedido' mod='fabricssamples'}</label><div class="col-lg-5"><p class="form-control-static">#{$fs_edit_coupon.id_order|intval}</p></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Código' mod='fabricssamples'}</label><div class="col-lg-5"><input type="text" name="coupon_code" value="{$fs_edit_coupon.code|escape:'htmlall':'UTF-8'}" maxlength="254" required></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Importe del descuento' mod='fabricssamples'}</label><div class="col-lg-3"><input type="number" step="0.01" min="0.01" name="discount_value" value="{$fs_edit_coupon.discount_value|floatval}" required></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Compra mínima' mod='fabricssamples'}</label><div class="col-lg-3"><input type="number" step="0.01" min="0" name="minimum_order" value="{$fs_edit_coupon.minimum_order|floatval}" required></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Fecha de inicio' mod='fabricssamples'}</label><div class="col-lg-4"><input type="datetime-local" name="date_from" value="{$fs_edit_date_from|escape:'htmlall':'UTF-8'}" required></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Fecha de caducidad' mod='fabricssamples'}</label><div class="col-lg-4"><input type="datetime-local" name="date_to" value="{$fs_edit_date_to|escape:'htmlall':'UTF-8'}" required></div></div>
    <div class="form-group"><label class="control-label col-lg-3">{l s='Activo' mod='fabricssamples'}</label><div class="col-lg-4"><select name="coupon_active" class="form-control"><option value="1"{if $fs_edit_rule_active} selected{/if}>{l s='Sí' mod='fabricssamples'}</option><option value="0"{if !$fs_edit_rule_active} selected{/if}>{l s='No' mod='fabricssamples'}</option></select></div></div>
    <div class="panel-footer"><a class="btn btn-default" href="{$fs_edit_cancel|escape:'htmlall':'UTF-8'}"><i class="process-icon-cancel"></i> {l s='Cancelar' mod='fabricssamples'}</a><button type="submit" name="submitEditFabricSamplesCoupon" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Guardar' mod='fabricssamples'}</button></div>
  </form>
</div>
