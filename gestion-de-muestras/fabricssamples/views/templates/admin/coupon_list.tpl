<div class="panel fs-coupon-admin-panel">
  {if isset($fs_coupon_feature_enabled) && !$fs_coupon_feature_enabled}<div class="alert alert-warning">{l s='La generación de cupones está desactivada. Actívela en Productos y configuración > Cupones.' mod='fabricssamples'}</div>{/if}
  <div class="panel-heading"><i class="icon-ticket"></i> {l s='Cupones de muestras' mod='fabricssamples'}
    {if !empty($fs_config_link)}<span class="panel-heading-action"><a class="list-toolbar-btn" href="{$fs_config_link|escape:'htmlall':'UTF-8'}" title="{l s='Volver a Productos y configuración' mod='fabricssamples'}"><i class="process-icon-back"></i></a></span>{/if}
  </div>
  <div class="row" style="margin-bottom:15px">
    <div class="col-sm-2"><strong>{l s='Total' mod='fabricssamples'}:</strong> {$fs_coupon_stats.total|intval}</div>
    <div class="col-sm-2"><strong>{l s='Pendientes' mod='fabricssamples'}:</strong> {$fs_coupon_stats.pending|intval}</div>
    <div class="col-sm-2"><strong>{l s='Usados' mod='fabricssamples'}:</strong> {$fs_coupon_stats.used|intval}</div>
    <div class="col-sm-2"><strong>{l s='Caducados' mod='fabricssamples'}:</strong> {$fs_coupon_stats.expired|intval}</div>
    <div class="col-sm-4 text-right"><form method="post" action="{$fs_repair_link|default:$fs_admin_link|escape:'htmlall':'UTF-8'}"><button class="btn btn-default" name="repairFabricSamplesHistory"><i class="icon-refresh"></i> {l s='Reparar histórico y generar cupones' mod='fabricssamples'}</button></form></div>
  </div>
  <form method="post" action="{$fs_admin_link|escape:'htmlall':'UTF-8'}">
    <div class="table-responsive"><table class="table">
      <thead><tr><th style="width:32px"><input type="checkbox" class="js-fs-check-all-coupons"></th>{if !empty($fs_show_shop_column)}<th>{l s='Tienda' mod='fabricssamples'}</th>{/if}<th>{l s='Cliente' mod='fabricssamples'}</th><th>{l s='Correo' mod='fabricssamples'}</th><th>{l s='Pedido' mod='fabricssamples'}</th><th>{l s='Código' mod='fabricssamples'}</th><th>{l s='Descuento' mod='fabricssamples'}</th><th>{l s='Compra mínima' mod='fabricssamples'}</th><th>{l s='Creado' mod='fabricssamples'}</th><th>{l s='Caduca' mod='fabricssamples'}</th><th>{l s='Estado' mod='fabricssamples'}</th><th>{l s='Acciones' mod='fabricssamples'}</th></tr></thead>
      <tbody>
      {foreach from=$fs_admin_coupons item=c}
        <tr>
          <td><input type="checkbox" name="couponBox[]" value="{$c.id_fabricssamples_coupon|intval}" class="js-fs-coupon-checkbox"></td>
          {if !empty($fs_show_shop_column)}<td>{$c.shop_name|default:'-'|escape:'htmlall':'UTF-8'}</td>{/if}
          <td>{if !empty($c.customer_link)}<a href="{$c.customer_link|escape:'htmlall':'UTF-8'}">{/if}{$c.firstname|escape:'htmlall':'UTF-8'} {$c.lastname|escape:'htmlall':'UTF-8'}{if !empty($c.customer_link)}</a>{/if}</td>
          <td>{$c.email|escape:'htmlall':'UTF-8'}</td>
          <td>{if !empty($c.order_link)}<a href="{$c.order_link|escape:'htmlall':'UTF-8'}">{/if}{$c.order_reference|escape:'htmlall':'UTF-8'}{if !empty($c.order_link)}</a>{/if}</td>
          <td>{if !empty($c.cart_rule_link)}<a href="{$c.cart_rule_link|escape:'htmlall':'UTF-8'}">{/if}<code>{$c.code|escape:'htmlall':'UTF-8'}</code>{if !empty($c.cart_rule_link)}</a>{/if}</td>
          <td>{$c.discount_value_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.minimum_order_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.date_add|escape:'htmlall':'UTF-8'}</td><td>{$c.date_to|escape:'htmlall':'UTF-8'}</td>
          <td><span class="label {$c.status_admin_class|escape:'htmlall':'UTF-8'}" data-status="{$c.status|escape:'htmlall':'UTF-8'}">{$c.status_label|escape:'htmlall':'UTF-8'}</span></td>
          <td>
            <a class="btn btn-default btn-xs" href="{$c.edit_link|escape:'htmlall':'UTF-8'}"><i class="icon-pencil"></i> {l s='Editar' mod='fabricssamples'}</a>
            {if !empty($c.can_issue_replacement)}
              <button type="submit" name="issueReplacementFabricSamplesCoupon" value="{$c.id_fabricssamples_coupon|intval}" class="btn btn-success btn-xs" onclick="return confirm('{l s='¿Emitir un código nuevo para este cliente? El cupón usado y sus pedidos permanecerán intactos, y solo podrá existir un reemplazo pendiente.' mod='fabricssamples' js=1}');"><i class="icon-plus"></i> {l s='Emitir nuevo cupón' mod='fabricssamples'}</button>
              <label class="control-label" style="display:block;margin-top:6px;font-weight:normal"><input type="checkbox" name="sendReplacementEmail[{$c.id_fabricssamples_coupon|intval}]" value="1" checked="checked"> {l s='Enviar por correo' mod='fabricssamples'}</label>
            {elseif !empty($c.has_pending_reissue)}
              <span class="text-muted"><i class="icon-lock"></i> {l s='Ya existe un reemplazo pendiente' mod='fabricssamples'}</span>
            {/if}
            <button type="submit" name="deleteFabricSamplesCoupon" value="{$c.id_fabricssamples_coupon|intval}" class="btn btn-danger btn-xs" onclick="return confirm('{l s='¿Eliminar permanentemente este cupón? No volverá a generarse para este pedido.' mod='fabricssamples' js=1}');"><i class="icon-trash"></i> {l s='Eliminar permanentemente' mod='fabricssamples'}</button>
          </td>
        </tr>
        {if !empty($c.reissues)}
          <tr class="active">
            <td colspan="{if !empty($fs_show_shop_column)}12{else}11{/if}" style="padding-left:36px">
              <strong><i class="icon-history"></i> {l s='Historial de reemisiones' mod='fabricssamples'}</strong>
              <div class="table-responsive" style="margin-top:8px"><table class="table table-condensed" style="margin-bottom:0;background:#fff">
                <thead><tr><th>{l s='Cupón original' mod='fabricssamples'}</th><th>{l s='N.º de reemisión' mod='fabricssamples'}</th><th>{l s='Código reemplazante' mod='fabricssamples'}</th><th>{l s='Estado' mod='fabricssamples'}</th><th>{l s='Fecha' mod='fabricssamples'}</th><th>{l s='Empleado' mod='fabricssamples'}</th><th>{l s='Correo' mod='fabricssamples'}</th></tr></thead>
                <tbody>{foreach from=$c.reissues item=r}<tr>
                  <td><code>{$r.original_code|escape:'htmlall':'UTF-8'}</code></td>
                  <td>#{$r.reissue_number|intval}</td>
                  <td>{if !empty($r.cart_rule_link)}<a href="{$r.cart_rule_link|escape:'htmlall':'UTF-8'}">{/if}<code>{$r.code|escape:'htmlall':'UTF-8'}</code>{if !empty($r.cart_rule_link)}</a>{/if}</td>
                  <td><span class="label {$r.status_admin_class|escape:'htmlall':'UTF-8'}">{$r.status_label|escape:'htmlall':'UTF-8'}</span></td>
                  <td>{$r.date_add|escape:'htmlall':'UTF-8'}</td>
                  <td>{if !empty($r.employee_name)}{$r.employee_name|escape:'htmlall':'UTF-8'}{else}#{$r.id_employee|intval}{/if}</td>
                  <td>{if !empty($r.email_sent)}<span class="label label-success">{l s='Enviado' mod='fabricssamples'}</span>{elseif !empty($r.email_requested)}<span class="label label-warning">{l s='Falló' mod='fabricssamples'}</span>{else}<span class="label label-default">{l s='No solicitado' mod='fabricssamples'}</span>{/if}</td>
                </tr>{/foreach}</tbody>
              </table></div>
            </td>
          </tr>
        {/if}
      {foreachelse}<tr><td colspan="{if !empty($fs_show_shop_column)}12{else}11{/if}">{l s='No hay cupones registrados todavía.' mod='fabricssamples'}</td></tr>{/foreach}
      </tbody>
    </table></div>
    {if !empty($fs_admin_coupons)}<button type="submit" name="bulkDeleteFabricSamplesCoupons" class="btn btn-danger" onclick="return confirm('{l s='¿Eliminar permanentemente los cupones seleccionados? No volverán a generarse para esos pedidos.' mod='fabricssamples' js=1}');"><i class="icon-trash"></i> {l s='Eliminar permanentemente los seleccionados' mod='fabricssamples'}</button>{/if}
  </form>
</div>
{literal}<script>document.addEventListener('DOMContentLoaded',function(){var all=document.querySelector('.js-fs-check-all-coupons');if(all){all.addEventListener('change',function(){document.querySelectorAll('.js-fs-coupon-checkbox').forEach(function(el){el.checked=all.checked;});});}});</script>{/literal}
