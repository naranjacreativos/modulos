<div class="panel fs-operations-header">
  <div class="panel-heading"><i class="icon-shield"></i> {l s='Límites y excepciones' mod='fabricssamples'}</div>
  <p>{l s='Los estados de pedido que cuentan se seleccionan en Productos y configuración → Límites. Aquí puedes crear excepciones por cliente o grupo, reiniciar contadores y revisar bloqueos.' mod='fabricssamples'}</p>
  <a class="btn btn-default" href="{$fs_limit_action|escape:'htmlall':'UTF-8'}&exportFabricSamplesLimitEvents=1"><i class="icon-download"></i> {l s='Exportar historial de límites CSV' mod='fabricssamples'}</a>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-bar-chart"></i> {l s='Consumo acumulado por cliente' mod='fabricssamples'}</div>
  <form method="get" class="form-inline fs-operation-filter">
    <input type="hidden" name="controller" value="AdminFabricSamplesLimits">
    <input type="hidden" name="token" value="{$smarty.get.token|escape:'htmlall':'UTF-8'}">
    <label>{l s='ID de cliente' mod='fabricssamples'}</label>
    <input class="form-control" type="number" min="1" name="usage_customer_id" value="{$fs_limit_usage_customer_id|intval}" required>
    <button class="btn btn-default" type="submit"><i class="icon-search"></i> {l s='Consultar consumo' mod='fabricssamples'}</button>
  </form>
  {if $fs_limit_usage}
    <div class="alert alert-info">
      <strong>#{$fs_limit_usage.id_customer|intval} {$fs_limit_usage.customer_name|escape:'htmlall':'UTF-8'}</strong> · {$fs_limit_usage.email|escape:'htmlall':'UTF-8'}<br>
      {l s='Periodo consultado desde' mod='fabricssamples'} {$fs_limit_usage.date_from|escape:'htmlall':'UTF-8'}
      {if $fs_limit_usage.reset_date} · {l s='Reinicio aplicado desde' mod='fabricssamples'} {$fs_limit_usage.reset_date|escape:'htmlall':'UTF-8'}{/if}<br>
      {l s='Origen de límites' mod='fabricssamples'}: {$fs_limit_usage.resolution.source_type|escape:'htmlall':'UTF-8'}{if $fs_limit_usage.resolution.source_id} #{$fs_limit_usage.resolution.source_id|intval}{/if}.
      {if $fs_limit_usage.resolution.exempt}<span class="label label-success">{l s='Cliente exento' mod='fabricssamples'}</span>{else}{l s='Máximo total' mod='fabricssamples'}: {$fs_limit_usage.resolution.max_total|intval}; {l s='máximo por referencia' mod='fabricssamples'}: {$fs_limit_usage.resolution.max_product|intval}; {l s='periodo' mod='fabricssamples'}: {$fs_limit_usage.resolution.period_days|intval} {l s='días' mod='fabricssamples'}.{/if}<br>
      {l s='Estados contabilizados' mod='fabricssamples'}: {foreach from=$fs_limit_usage.states item=state name=usage_states}{$state|escape:'htmlall':'UTF-8'}{if !$smarty.foreach.usage_states.last}, {/if}{/foreach}
    </div>
    <p class="lead">{l s='Total contabilizado' mod='fabricssamples'}: <strong>{$fs_limit_usage.total|intval}</strong></p>
    <div class="table-responsive-row clearfix"><table class="table"><thead><tr><th>{l s='Producto' mod='fabricssamples'}</th><th>{l s='Referencia' mod='fabricssamples'}</th><th>{l s='Muestras' mod='fabricssamples'}</th><th>{l s='Pedidos' mod='fabricssamples'}</th><th>{l s='Último pedido' mod='fabricssamples'}</th></tr></thead><tbody>
      {foreach from=$fs_limit_usage.products item=p}<tr><td>#{$p.id_product|intval} {$p.product_name|escape:'htmlall':'UTF-8'}</td><td>{$p.product_reference|escape:'htmlall':'UTF-8'}</td><td>{$p.quantity|intval}</td><td>{$p.order_count|intval}</td><td>{$p.last_order_date|escape:'htmlall':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="5" class="text-center text-muted">{l s='No hay muestras contabilizadas en el periodo y estados seleccionados.' mod='fabricssamples'}</td></tr>{/foreach}
    </tbody></table></div>
  {/if}
</div>

<div class="row">
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-heading"><i class="icon-user"></i> {if $fs_limit_edit}{l s='Editar excepción' mod='fabricssamples'}{else}{l s='Nueva excepción' mod='fabricssamples'}{/if}</div>
      {assign var=fs_limit_target_type value=$fs_limit_edit.target_type|default:'customer'}
      {assign var=fs_limit_mode value=$fs_limit_edit.mode|default:'custom'}
      <form method="post" action="{$fs_limit_action|escape:'htmlall':'UTF-8'}" class="form-horizontal">
        <div class="form-group"><label class="control-label col-lg-4">{l s='Aplicar a' mod='fabricssamples'}</label><div class="col-lg-8"><select class="form-control" name="target_type"><option value="customer"{if $fs_limit_target_type=='customer'} selected{/if}>{l s='Cliente' mod='fabricssamples'}</option><option value="group"{if $fs_limit_target_type=='group'} selected{/if}>{l s='Grupo de clientes' mod='fabricssamples'}</option></select></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='ID de cliente o grupo' mod='fabricssamples'}</label><div class="col-lg-8"><input class="form-control" type="number" min="1" name="target_id" value="{$fs_limit_edit.target_id|default:''|escape:'htmlall':'UTF-8'}" required><p class="help-block"><a href="{$fs_customer_link|escape:'htmlall':'UTF-8'}" target="_blank">{l s='Abrir clientes' mod='fabricssamples'}</a> · <a href="{$fs_group_link|escape:'htmlall':'UTF-8'}" target="_blank">{l s='Abrir grupos' mod='fabricssamples'}</a></p></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Tipo de excepción' mod='fabricssamples'}</label><div class="col-lg-8"><select class="form-control" name="exception_mode"><option value="exempt"{if $fs_limit_mode=='exempt'} selected{/if}>{l s='Sin límites históricos' mod='fabricssamples'}</option><option value="custom"{if $fs_limit_mode=='custom'} selected{/if}>{l s='Límites personalizados' mod='fabricssamples'}</option></select></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Máximo total en periodo' mod='fabricssamples'}</label><div class="col-lg-8"><input class="form-control" type="number" min="0" name="max_total_period" value="{$fs_limit_edit.max_total_period|default:0|intval}"><p class="help-block">{l s='0 conserva el valor general.' mod='fabricssamples'}</p></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Máximo por referencia' mod='fabricssamples'}</label><div class="col-lg-8"><input class="form-control" type="number" min="0" name="max_product_period" value="{$fs_limit_edit.max_product_period|default:0|intval}"></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Periodo personalizado' mod='fabricssamples'}</label><div class="col-lg-8"><input class="form-control" type="number" min="0" max="3650" name="period_days" value="{$fs_limit_edit.period_days|default:0|intval}" suffix="días"><p class="help-block">{l s='0 conserva el periodo general.' mod='fabricssamples'}</p></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Activa' mod='fabricssamples'}</label><div class="col-lg-8"><label><input type="checkbox" name="exception_active" value="1"{if !$fs_limit_edit || $fs_limit_edit.active} checked{/if}> {l s='Aplicar esta excepción' mod='fabricssamples'}</label></div></div>
        <div class="form-group"><label class="control-label col-lg-4">{l s='Nota interna' mod='fabricssamples'}</label><div class="col-lg-8"><textarea class="form-control" name="exception_note" rows="3">{$fs_limit_edit.note|default:''|escape:'htmlall':'UTF-8'}</textarea></div></div>
        <div class="panel-footer"><button class="btn btn-primary pull-right" name="submitFabricSamplesLimitException" value="1" type="submit"><i class="process-icon-save"></i> {l s='Guardar excepción' mod='fabricssamples'}</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-heading"><i class="icon-refresh"></i> {l s='Reiniciar contador de un cliente' mod='fabricssamples'}</div>
      <p>{l s='Los pedidos anteriores a este momento dejarán de contar para sus límites históricos. No se eliminan pedidos ni datos fiscales.' mod='fabricssamples'}</p>
      <form method="post" action="{$fs_limit_action|escape:'htmlall':'UTF-8'}">
        <div class="form-group"><label>{l s='ID de cliente' mod='fabricssamples'}</label><input class="form-control" type="number" min="1" name="reset_customer_id" required></div>
        <div class="form-group"><label>{l s='Motivo' mod='fabricssamples'}</label><input class="form-control" name="reset_note" required></div>
        <button class="btn btn-warning" name="submitFabricSamplesLimitReset" value="1" type="submit" onclick="return confirm('{l s='¿Reiniciar el contador histórico de este cliente desde ahora?' mod='fabricssamples' js=1}');"><i class="icon-refresh"></i> {l s='Reiniciar contador' mod='fabricssamples'}</button>
      </form>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-list"></i> {l s='Excepciones configuradas' mod='fabricssamples'} <span class="badge">{$fs_limit_total|intval}</span></div>
  <form method="get" class="form-inline"><input type="hidden" name="controller" value="AdminFabricSamplesLimits"><input type="hidden" name="token" value="{$smarty.get.token|escape:'htmlall':'UTF-8'}"><input class="form-control" name="limit_search" value="{$fs_limit_search|escape:'htmlall':'UTF-8'}" placeholder="{l s='ID, cliente, email o grupo' mod='fabricssamples'}"><button class="btn btn-default" type="submit"><i class="icon-search"></i> {l s='Buscar' mod='fabricssamples'}</button></form>
  <div class="table-responsive-row clearfix"><table class="table"><thead><tr><th>{l s='Destino' mod='fabricssamples'}</th><th>{l s='Modo' mod='fabricssamples'}</th><th>{l s='Máximo total' mod='fabricssamples'}</th><th>{l s='Máximo por referencia' mod='fabricssamples'}</th><th>{l s='Periodo' mod='fabricssamples'}</th><th>{l s='Activa' mod='fabricssamples'}</th><th>{l s='Nota' mod='fabricssamples'}</th><th></th></tr></thead><tbody>
  {foreach from=$fs_limit_exceptions item=e}<tr><td><strong>{if $e.target_type=='customer'}{l s='Cliente' mod='fabricssamples'} #{$e.target_id|intval}{else}{l s='Grupo' mod='fabricssamples'} #{$e.target_id|intval}{/if}</strong><br>{if $e.target_type=='customer'}{$e.firstname|escape:'htmlall':'UTF-8'} {$e.lastname|escape:'htmlall':'UTF-8'} · {$e.email|escape:'htmlall':'UTF-8'}{else}{$e.group_name|escape:'htmlall':'UTF-8'}{/if}</td><td>{if $e.mode=='exempt'}<span class="label label-success">{l s='Exento' mod='fabricssamples'}</span>{else}<span class="label label-info">{l s='Personalizado' mod='fabricssamples'}</span>{/if}</td><td>{$e.max_total_period|intval}</td><td>{$e.max_product_period|intval}</td><td>{$e.period_days|intval}</td><td>{if $e.active}<i class="icon-check text-success"></i>{else}<i class="icon-times text-danger"></i>{/if}</td><td>{$e.note|escape:'htmlall':'UTF-8'}</td><td><a class="btn btn-default" href="{$fs_limit_action|escape:'htmlall':'UTF-8'}&edit_exception={$e.id_fabricssamples_limit_exception|intval}"><i class="icon-pencil"></i></a> <form method="post" action="{$fs_limit_action|escape:'htmlall':'UTF-8'}" style="display:inline" onsubmit="return confirm('{l s='¿Eliminar esta excepción?' mod='fabricssamples' js=1}');"><button class="btn btn-danger" type="submit" name="delete_limit_exception" value="{$e.id_fabricssamples_limit_exception|intval}"><i class="icon-trash"></i></button></form></td></tr>{foreachelse}<tr><td colspan="8" class="text-center text-muted">{l s='No hay excepciones configuradas.' mod='fabricssamples'}</td></tr>{/foreach}
  </tbody></table></div>
  {if $fs_limit_pages>1}<ul class="pagination">{section name=i start=1 loop=$fs_limit_pages+1}<li class="{if $fs_limit_page==$smarty.section.i.index}active{/if}"><a href="{$fs_limit_action|escape:'htmlall':'UTF-8'}&page={$smarty.section.i.index|intval}&limit_search={$fs_limit_search|urlencode}">{$smarty.section.i.index|intval}</a></li>{/section}</ul>{/if}
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-history"></i> {l s='Últimos eventos de límites' mod='fabricssamples'}</div>
  <div class="table-responsive-row clearfix"><table class="table"><thead><tr><th>{l s='Fecha' mod='fabricssamples'}</th><th>{l s='Tipo' mod='fabricssamples'}</th><th>{l s='Cliente' mod='fabricssamples'}</th><th>{l s='Producto' mod='fabricssamples'}</th><th>{l s='Límite' mod='fabricssamples'}</th><th>{l s='Observado' mod='fabricssamples'}</th><th>{l s='Origen' mod='fabricssamples'}</th><th>{l s='Mensaje' mod='fabricssamples'}</th></tr></thead><tbody>
  {foreach from=$fs_limit_events item=e}<tr><td>{$e.date_add|escape:'htmlall':'UTF-8'}</td><td>{$e.event_type|escape:'htmlall':'UTF-8'}{if $e.limit_code}<br><small>{$e.limit_code|escape:'htmlall':'UTF-8'}</small>{/if}</td><td>{if $e.id_customer}#{$e.id_customer|intval} {$e.firstname|escape:'htmlall':'UTF-8'} {$e.lastname|escape:'htmlall':'UTF-8'}{else}{l s='Invitado' mod='fabricssamples'}{/if}</td><td>{if $e.id_product}#{$e.id_product|intval} {$e.product_name|escape:'htmlall':'UTF-8'}{else}—{/if}</td><td>{$e.limit_value|intval}</td><td>{$e.observed_value|intval}</td><td>{$e.source_type|escape:'htmlall':'UTF-8'} {if $e.source_id}#{$e.source_id|intval}{/if}</td><td>{$e.message|escape:'htmlall':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="8" class="text-center text-muted">{l s='Todavía no hay eventos de límites.' mod='fabricssamples'}</td></tr>{/foreach}
  </tbody></table></div>
</div>
