{extends file='customer/page.tpl'}
{block name='page_title'}{l s='Mis muestras' mod='fabricssamples'}{/block}
{block name='page_content'}
<div class="card mb-3">
  <div class="card-body">
    <strong>{l s='Cupones de muestras disponibles:' mod='fabricssamples'} {$fs_available_coupon_count|intval}</strong>
    <a class="btn btn-primary btn-sm" href="{$fs_my_coupons_url|escape:'htmlall':'UTF-8'}">{l s='Ver mis cupones' mod='fabricssamples'}</a>
  </div>
</div>

<div class="table-responsive"><table class="table"><thead><tr><th>{l s='Imagen' mod='fabricssamples'}</th><th>{l s='Pedido' mod='fabricssamples'}</th><th>{l s='Fecha' mod='fabricssamples'}</th><th>{l s='Muestra' mod='fabricssamples'}</th><th>{l s='Referencia' mod='fabricssamples'}</th><th>{l s='Total' mod='fabricssamples'}</th><th></th></tr></thead><tbody>
{foreach from=$fs_samples item=s}<tr><td>{if $s.image_url}<img src="{$s.image_url|escape:'htmlall':'UTF-8'}" alt="{$s.product_name|escape:'htmlall':'UTF-8'}" style="width:64px;height:64px;object-fit:cover">{/if}</td><td>{$s.order_reference|escape:'htmlall':'UTF-8'}</td><td>{$s.date_add|escape:'htmlall':'UTF-8'}</td><td><strong>{$s.product_name|escape:'htmlall':'UTF-8'}</strong></td><td>{$s.product_reference|escape:'htmlall':'UTF-8'}</td><td>{$s.total_price_formatted|escape:'htmlall':'UTF-8'}</td><td>{if $s.product_url}<a class="btn btn-sm btn-primary" href="{$s.product_url|escape:'htmlall':'UTF-8'}">{l s='Comprar tejido' mod='fabricssamples'}</a>{/if}</td></tr>{foreachelse}<tr><td colspan="7">{l s='Todavía no has pedido muestras.' mod='fabricssamples'}</td></tr>{/foreach}
</tbody></table></div>

<div class="card mt-4">
  <div class="card-header"><strong>{l s='Mis cupones de muestras' mod='fabricssamples'}</strong></div>
  <div class="card-body">
    <p>{l s='Puedes copiar el código y aplicarlo durante el proceso de compra.' mod='fabricssamples'}</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>{l s='Pedido' mod='fabricssamples'}</th><th>{l s='Código' mod='fabricssamples'}</th><th>{l s='Descuento' mod='fabricssamples'}</th><th>{l s='Compra mínima' mod='fabricssamples'}</th><th>{l s='Caducidad' mod='fabricssamples'}</th><th>{l s='Estado' mod='fabricssamples'}</th></tr></thead><tbody>
    {foreach from=$fs_coupons item=c}
      <tr><td>{$c.order_reference|escape:'htmlall':'UTF-8'}</td><td><code style="font-size:1.1em;font-weight:700">{$c.code|escape:'htmlall':'UTF-8'}</code></td><td>{$c.discount_value_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.minimum_order_formatted|escape:'htmlall':'UTF-8'}</td><td>{$c.date_to|escape:'htmlall':'UTF-8'}</td><td><span class="fabric-samples-coupon-status {$c.status_front_class|escape:'htmlall':'UTF-8'}" data-status="{$c.status|escape:'htmlall':'UTF-8'}">{$c.status_label|escape:'htmlall':'UTF-8'}</span></td></tr>
    {foreachelse}<tr><td colspan="6">{l s='Actualmente no tienes cupones de muestras.' mod='fabricssamples'}</td></tr>{/foreach}
    </tbody></table></div>
  </div>
</div>
{/block}
