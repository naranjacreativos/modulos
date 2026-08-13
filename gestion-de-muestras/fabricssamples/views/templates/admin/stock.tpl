<div class="panel fs-operations-header">
  <div class="panel-heading"><i class="icon-cubes"></i> {l s='Administración del stock de muestras' mod='fabricssamples'}</div>
  <p>{l s='Gestiona únicamente los productos configurados con stock independiente. Los ajustes quedan registrados con empleado, motivo y cantidades anterior/posterior.' mod='fabricssamples'}</p>
  <a class="btn btn-default" href="{$fs_stock_action|escape:'htmlall':'UTF-8'}&exportFabricSamplesStock=1"><i class="icon-download"></i> {l s='Exportar stock actual CSV' mod='fabricssamples'}</a>
  <a class="btn btn-default" href="{$fs_stock_action|escape:'htmlall':'UTF-8'}&exportFabricSamplesStockMovements=1"><i class="icon-download"></i> {l s='Exportar movimientos CSV' mod='fabricssamples'}</a>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-filter"></i> {l s='Stock disponible' mod='fabricssamples'} <span class="badge">{$fs_stock_total|intval}</span></div>
  <form method="get" class="form-inline fs-operation-filter">
    <input type="hidden" name="controller" value="AdminFabricSamplesStock">
    <input type="hidden" name="token" value="{$smarty.get.token|escape:'htmlall':'UTF-8'}">
    <input class="form-control" name="stock_search" value="{$fs_stock_search|escape:'htmlall':'UTF-8'}" placeholder="{l s='ID, nombre o referencia' mod='fabricssamples'}">
    <select class="form-control" name="stock_filter">
      <option value="">{l s='Todos' mod='fabricssamples'}</option>
      <option value="low"{if $fs_stock_filter=='low'} selected{/if}>{l s='Stock bajo' mod='fabricssamples'} (≤ {$fs_low_threshold|intval})</option>
      <option value="out"{if $fs_stock_filter=='out'} selected{/if}>{l s='Agotados' mod='fabricssamples'}</option>
    </select>
    <select class="form-control" name="limit">{foreach from=[20,50,100,200] item=n}<option value="{$n}"{if $fs_stock_limit==$n} selected{/if}>{$n}</option>{/foreach}</select>
    <button class="btn btn-default" type="submit"><i class="icon-search"></i> {l s='Filtrar' mod='fabricssamples'}</button>
  </form>

  <form method="post" action="{$fs_stock_action|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="page" value="{$fs_stock_page|intval}">
    <input type="hidden" name="limit" value="{$fs_stock_limit|intval}">
    <input type="hidden" name="stock_search" value="{$fs_stock_search|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="stock_filter" value="{$fs_stock_filter|escape:'htmlall':'UTF-8'}">
    <div class="table-responsive-row clearfix">
      <table class="table fs-operation-table">
        <thead><tr>
          <th></th><th>ID</th><th>{l s='Producto' mod='fabricssamples'}</th><th>{l s='Referencia' mod='fabricssamples'}</th>
          <th>{l s='Existencias' mod='fabricssamples'}</th><th>{l s='Reservado en carritos' mod='fabricssamples'}</th><th>{l s='Disponible tras reservas' mod='fabricssamples'}</th><th>{l s='Consumo neto' mod='fabricssamples'}</th><th>{l s='Ajuste manual' mod='fabricssamples'}</th>
        </tr></thead>
        <tbody>
        {foreach from=$fs_stock_rows item=row}
          <tr class="{if $row.sample_stock<=0}danger{elseif $row.sample_stock<=$fs_low_threshold}warning{/if}">
            <td><input type="checkbox" name="stockBox[]" value="{$row.id_product|intval}"></td>
            <td>{$row.id_product|intval}</td>
            <td><strong>{$row.name|escape:'htmlall':'UTF-8'}</strong>{if !$row.active || !$row.available}<br><span class="label label-default">{l s='Muestra inactiva/no disponible' mod='fabricssamples'}</span>{/if}</td>
            <td>{$row.reference|escape:'htmlall':'UTF-8'}</td>
            <td><span class="fs-stock-number">{$row.sample_stock|intval}</span></td>
            <td>{$row.reserved|intval}</td><td>{$row.available_after_reservations|intval}</td><td>{$row.net_consumed|intval}<br><small class="text-muted">{l s='Consumido' mod='fabricssamples'} {$row.consumed|intval} / {l s='restaurado' mod='fabricssamples'} {$row.restored|intval}</small></td>
            <td>
              <div class="fs-inline-adjustment">
                <input class="form-control fixed-width-sm" type="number" name="stock_delta[{$row.id_product|intval}]" value="1" aria-label="{l s='Variación' mod='fabricssamples'}">
                <input class="form-control" type="text" name="stock_reason[{$row.id_product|intval}]" placeholder="{l s='Motivo obligatorio' mod='fabricssamples'}">
                <button class="btn btn-primary" name="submitFabricSamplesStockAdjustment" value="{$row.id_product|intval}" type="submit"><i class="icon-plus-minus"></i> {l s='Aplicar' mod='fabricssamples'}</button>
              </div>
            </td>
          </tr>
        {foreachelse}
          <tr><td colspan="9" class="text-center text-muted">{l s='No hay productos con stock independiente que coincidan con el filtro.' mod='fabricssamples'}</td></tr>
        {/foreach}
        </tbody>
      </table>
    </div>
    <div class="well fs-bulk-box">
      <strong>{l s='Ajuste masivo sobre los productos seleccionados' mod='fabricssamples'}</strong>
      <input class="form-control fixed-width-sm" type="number" name="bulk_stock_delta" value="1">
      <input class="form-control" type="text" name="bulk_stock_reason" placeholder="{l s='Motivo obligatorio' mod='fabricssamples'}">
      <button class="btn btn-warning" name="submitFabricSamplesBulkStockAdjustment" value="1" type="submit" onclick="return confirm('{l s='¿Aplicar este ajuste de stock a todos los productos seleccionados?' mod='fabricssamples' js=1}');"><i class="icon-cubes"></i> {l s='Aplicar ajuste masivo' mod='fabricssamples'}</button>
    </div>
  </form>
  {if $fs_stock_pages>1}<ul class="pagination">{section name=i start=1 loop=$fs_stock_pages+1}<li class="{if $fs_stock_page==$smarty.section.i.index}active{/if}"><a href="{$fs_stock_action|escape:'htmlall':'UTF-8'}&page={$smarty.section.i.index|intval}&limit={$fs_stock_limit|intval}&stock_search={$fs_stock_search|urlencode}&stock_filter={$fs_stock_filter|urlencode}">{$smarty.section.i.index|intval}</a></li>{/section}</ul>{/if}
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-history"></i> {l s='Últimos movimientos' mod='fabricssamples'}</div>
  <div class="table-responsive-row clearfix"><table class="table"><thead><tr><th>{l s='Fecha' mod='fabricssamples'}</th><th>{l s='Producto' mod='fabricssamples'}</th><th>{l s='Tipo' mod='fabricssamples'}</th><th>{l s='Variación' mod='fabricssamples'}</th><th>{l s='Antes → después' mod='fabricssamples'}</th><th>{l s='Pedido' mod='fabricssamples'}</th><th>{l s='Empleado' mod='fabricssamples'}</th><th>{l s='Motivo' mod='fabricssamples'}</th></tr></thead><tbody>
  {foreach from=$fs_stock_movements item=m}<tr><td>{$m.date_add|escape:'htmlall':'UTF-8'}</td><td>#{$m.id_product|intval} {$m.product_name|escape:'htmlall':'UTF-8'}</td><td>{$m.movement_type|escape:'htmlall':'UTF-8'}</td><td>{if $m.quantity_delta>0}+{/if}{$m.quantity_delta|intval}</td><td>{$m.quantity_before|intval} → {$m.quantity_after|intval}</td><td>{if $m.id_order}#{$m.id_order|intval}{else}—{/if}</td><td>{$m.employee_firstname|escape:'htmlall':'UTF-8'} {$m.employee_lastname|escape:'htmlall':'UTF-8'}</td><td>{$m.note|escape:'htmlall':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="8" class="text-center text-muted">{l s='Todavía no hay movimientos.' mod='fabricssamples'}</td></tr>{/foreach}
  </tbody></table></div>
</div>
