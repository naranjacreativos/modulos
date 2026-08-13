<section class="fabric-samples-order card mt-3">
  <div class="card-header"><strong>{l s='Muestras incluidas en el pedido' mod='fabricssamples'}</strong></div>
  <div class="card-body">
    <div class="table-responsive"><table class="table"><thead><tr><th>{l s='Imagen' mod='fabricssamples'}</th><th>{l s='Tejido' mod='fabricssamples'}</th><th>{l s='Referencia' mod='fabricssamples'}</th><th>{l s='Tamaño' mod='fabricssamples'}</th><th>{l s='Cantidad' mod='fabricssamples'}</th><th>{l s='Precio' mod='fabricssamples'}</th><th>{l s='Total' mod='fabricssamples'}</th><th>{l s='Estado' mod='fabricssamples'}</th></tr></thead><tbody>
    {foreach from=$fs_order_samples item=s}<tr><td>{if $s.image_url}<img src="{$s.image_url|escape:'htmlall':'UTF-8'}" alt="{$s.product_name|escape:'htmlall':'UTF-8'}" style="width:64px;height:64px;object-fit:cover">{/if}</td><td>{if $s.product_url}<a href="{$s.product_url|escape:'htmlall':'UTF-8'}">{/if}{$s.product_name|escape:'htmlall':'UTF-8'}{if $s.product_url}</a>{/if}</td><td>{$s.product_reference|escape:'htmlall':'UTF-8'}</td><td>{$s.size_text|escape:'htmlall':'UTF-8'}</td><td>{$s.quantity|intval}</td><td>{$s.unit_price_formatted|escape:'htmlall':'UTF-8'}</td><td>{$s.total_price_formatted|escape:'htmlall':'UTF-8'}</td><td>{$s.preparation_status|escape:'htmlall':'UTF-8'}</td></tr>{/foreach}
    </tbody></table></div>
    {if $fs_order_coupon}
      <div class="alert alert-success mt-3">
        <strong>{l s='Cupón por las muestras:' mod='fabricssamples'}</strong>
        <code>{$fs_order_coupon.code|escape:'htmlall':'UTF-8'}</code>
        — {$fs_order_coupon.discount_value_formatted|escape:'htmlall':'UTF-8'}
        — {l s='válido hasta' mod='fabricssamples'} {$fs_order_coupon.date_to|escape:'htmlall':'UTF-8'}
        {if $fs_order_coupon.minimum_order > 0}<br>{l s='Compra mínima:' mod='fabricssamples'} {$fs_order_coupon.minimum_order_formatted|escape:'htmlall':'UTF-8'}{/if}
      </div>
    {/if}
  </div>
</section>
