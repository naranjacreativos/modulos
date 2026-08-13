{extends file='customer/page.tpl'}
{block name='page_title'}{l s='Mis cupones de muestras' mod='fabricssamples'}{/block}
{block name='page_content'}
<div class="card">
  <div class="card-header"><strong>{l s='Cupones disponibles para canjear' mod='fabricssamples'}</strong></div>
  <div class="card-body">
    <p>{l s='Introduce el código durante el proceso de compra. Cada cupón está asignado exclusivamente a tu cuenta.' mod='fabricssamples'}</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>{l s='Pedido' mod='fabricssamples'}</th><th>{l s='Código' mod='fabricssamples'}</th><th>{l s='Descuento' mod='fabricssamples'}</th><th>{l s='Compra mínima' mod='fabricssamples'}</th><th>{l s='Caducidad' mod='fabricssamples'}</th><th>{l s='Estado' mod='fabricssamples'}</th></tr></thead><tbody>
    {foreach from=$fs_coupons item=c}
      <tr><td>{$c.order_reference|escape:'htmlall':'UTF-8'}{if !empty($c.is_reissue)}<br><small>{l s='Reemisión' mod='fabricssamples'} #{$c.reissue_number|intval}</small>{/if}</td><td><code style="font-size:1.1em">{$c.code|escape:'htmlall':'UTF-8'}</code></td><td>{$c.discount_value_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.minimum_order_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.date_to|escape:'htmlall':'UTF-8'}</td><td><span class="fabric-samples-coupon-status {$c.status_front_class|escape:'htmlall':'UTF-8'}" data-status="{$c.status|escape:'htmlall':'UTF-8'}">{$c.status_label|escape:'htmlall':'UTF-8'}</span></td></tr>
    {foreachelse}<tr><td colspan="6">{l s='Actualmente no tienes cupones de muestras disponibles.' mod='fabricssamples'}</td></tr>{/foreach}
    </tbody></table></div>
    <a class="btn btn-secondary" href="{$fs_samples_url|escape:'htmlall':'UTF-8'}">{l s='Volver a Mis muestras' mod='fabricssamples'}</a>
  </div>
</div>
{/block}
