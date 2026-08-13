<div class="panel fs-diagnostic-header">
  <div class="panel-heading"><i class="icon-stethoscope"></i> {l s='Diagnóstico de muestras de tejidos' mod='fabricssamples'}</div>
  <div class="row">
    <div class="col-lg-8">
      <p>{l s='Comprueba el servidor, la base de datos, los hooks, las rutas y la integridad de los datos del módulo.' mod='fabricssamples'}</p>
      <p class="text-muted">{l s='Las reparaciones no eliminan pedidos válidos. La limpieza de huérfanos solo borra registros cuyo carrito o pedido nativo ya no existe.' mod='fabricssamples'}</p>
    </div>
    <div class="col-lg-4 text-right">
      <a class="btn btn-default" href="{$fs_config_link|escape:'htmlall':'UTF-8'}"><i class="icon-cogs"></i> {l s='Configuración' mod='fabricssamples'}</a>
      <a class="btn btn-default" href="{$fs_coupon_link|escape:'htmlall':'UTF-8'}"><i class="icon-ticket"></i> {l s='Cupones' mod='fabricssamples'}</a>
    </div>
  </div>
</div>

<div class="row fs-diagnostic-summary">
  <div class="col-sm-3"><div class="fs-summary-card fs-summary-ok"><strong>{$fs_diagnostic_report.summary.ok|intval}</strong><span>{l s='Correctos' mod='fabricssamples'}</span></div></div>
  <div class="col-sm-3"><div class="fs-summary-card fs-summary-warning"><strong>{$fs_diagnostic_report.summary.warning|intval}</strong><span>{l s='Avisos' mod='fabricssamples'}</span></div></div>
  <div class="col-sm-3"><div class="fs-summary-card fs-summary-error"><strong>{$fs_diagnostic_report.summary.error|intval}</strong><span>{l s='Errores' mod='fabricssamples'}</span></div></div>
  <div class="col-sm-3"><div class="fs-summary-card fs-summary-info"><strong>{$fs_diagnostic_report.summary.info|intval}</strong><span>{l s='Informativos' mod='fabricssamples'}</span></div></div>
</div>

<div class="panel fs-diagnostic-actions">
  <div class="panel-heading"><i class="icon-wrench"></i> {l s='Herramientas de reparación' mod='fabricssamples'}</div>
  <form method="post" action="{$fs_diagnostic_action|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="submitFabricSamplesDiagnosticRepair" value="1">
    <input type="hidden" name="diagnostic_reset_confirmation" value="">
    <input type="hidden" name="diagnostic_history_confirmation" value="">
    <div class="btn-group-wrapper">
      <button class="btn btn-default" name="diagnostic_action" value="schema" type="submit"><i class="icon-database"></i> {l s='Reparar esquema' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="hooks" type="submit"><i class="icon-link"></i> {l s='Reparar hooks' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="tabs" type="submit"><i class="icon-list"></i> {l s='Reparar pestañas' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="routes" type="submit"><i class="icon-random"></i> {l s='Regenerar rutas' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="carts" type="submit"><i class="icon-shopping-cart"></i> {l s='Reparar carritos' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="history" type="submit"><i class="icon-history"></i> {l s='Reconstruir históricos' mod='fabricssamples'}</button>
      <button class="btn btn-warning" name="diagnostic_action" value="history_ignore" type="submit" onclick="if (!confirm('{l s='Estas líneas dejarán de aparecer como aviso porque se marcarán como no pertenecientes al módulo. NO se borrarán pedidos, facturas ni líneas nativas. ¿Desea continuar?' mod='fabricssamples' js=1}')) { return false; } this.form.elements['diagnostic_history_confirmation'].value='IGNORE_UNRESOLVED_HISTORY'; return true;"><i class="icon-eye-close"></i> {l s='Descartar pendientes' mod='fabricssamples'}</button>
      {if $fs_diagnostic_report.history_ignored|intval > 0}<button class="btn btn-default" name="diagnostic_action" value="history_restore" type="submit"><i class="icon-undo"></i> {l s='Volver a revisar descartados' mod='fabricssamples'}</button>{/if}
      <button class="btn btn-default" name="diagnostic_action" value="stock" type="submit"><i class="icon-cubes"></i> {l s='Sincronizar stock' mod='fabricssamples'}</button>
      <button class="btn btn-default" name="diagnostic_action" value="coupons" type="submit"><i class="icon-ticket"></i> {l s='Sincronizar cupones' mod='fabricssamples'}</button>
      <button class="btn btn-warning" name="diagnostic_action" value="orphans" type="submit" onclick="return confirm('{l s='¿Limpiar registros huérfanos cuyo carrito o pedido ya no existe?' mod='fabricssamples' js=1}');"><i class="icon-trash"></i> {l s='Limpiar huérfanos' mod='fabricssamples'}</button>
      <button class="btn btn-primary" name="diagnostic_action" value="all" type="submit" onclick="return confirm('{l s='¿Ejecutar todas las reparaciones? Puede tardar en tiendas con muchos pedidos.' mod='fabricssamples' js=1}');"><i class="icon-magic"></i> {l s='Reparar todo' mod='fabricssamples'}</button>
      <button class="btn btn-danger" name="diagnostic_action" value="reset" type="submit" onclick="if (!confirm('{l s='ATENCIÓN: se creará primero una copia descargable y, solo si finaliza correctamente, se borrarán TODOS los datos del módulo en todas las tiendas: configuración, muestras activadas, líneas de carrito, históricos, stock, movimientos, cupones, supresiones, conversiones e imágenes. El módulo quedará como recién instalado. Esta operación no se puede deshacer sin restaurar la copia. ¿Desea continuar?' mod='fabricssamples' js=1}')) { return false; } this.form.elements['diagnostic_reset_confirmation'].value='DELETE_ALL_FABRIC_SAMPLES_DATA'; return true;"><i class="icon-refresh"></i> {l s='Reinicializar el módulo' mod='fabricssamples'}</button>
    </div>
    <p class="help-block fs-reset-warning"><i class="icon-warning-sign"></i> {l s='La reinicialización se cancela si no puede crear y verificar una copia cifrada completa. La retención se configura en Privacidad y seguridad.' mod='fabricssamples'}</p>
  </form>
</div>

<div class="panel fs-diagnostic-section">
  <div class="panel-heading"><i class="icon-download"></i> {l s='Copias cifradas y restauración' mod='fabricssamples'}</div>
  <p class="help-block">{l s='Cada copia nueva se cifra con la clave de la tienda y se valida mediante descifrado completo antes de publicarse. Conserve la clave de la tienda: si cambia, las copias anteriores no podrán descifrarse.' mod='fabricssamples'}</p>
  {if $fs_reset_backups|@count > 0}
    <div class="table-responsive-row clearfix"><table class="table fs-diagnostic-table"><thead><tr><th>{l s='Archivo' mod='fabricssamples'}</th><th>{l s='Fecha' mod='fabricssamples'}</th><th>{l s='Tamaño' mod='fabricssamples'}</th><th>{l s='Acción' mod='fabricssamples'}</th></tr></thead><tbody>
    {foreach from=$fs_reset_backups item=backup}<tr><td><code>{$backup.filename|escape:'htmlall':'UTF-8'}</code> {if $backup.encrypted}<span class="label label-success">{l s='Cifrada' mod='fabricssamples'}</span>{else}<span class="label label-warning">{l s='Legada' mod='fabricssamples'}</span>{/if}</td><td>{$backup.created_at|escape:'htmlall':'UTF-8'}</td><td>{$backup.size_formatted|escape:'htmlall':'UTF-8'}</td><td><a class="btn btn-default" href="{$fs_diagnostic_action|escape:'htmlall':'UTF-8'}&amp;downloadFabricSamplesBackup={$backup.filename|urlencode}"><i class="icon-download"></i> {l s='Descargar' mod='fabricssamples'}</a> {if $backup.encrypted}<form method="post" action="{$fs_diagnostic_action|escape:'htmlall':'UTF-8'}" style="display:inline" onsubmit="if (!confirm('{l s='Se creará una copia cifrada del estado actual, se reinicializará el módulo y se restaurarán los datos seleccionados. ¿Desea continuar?' mod='fabricssamples' js=1}')) { return false; } this.elements['restore_backup_confirmation'].value='RESTORE_FABRIC_SAMPLES_BACKUP'; return true;"><input type="hidden" name="restore_backup_confirmation" value=""><button class="btn btn-warning" type="submit" name="restoreFabricSamplesBackup" value="{$backup.filename|escape:'htmlall':'UTF-8'}"><i class="icon-undo"></i> {l s='Restaurar' mod='fabricssamples'}</button></form>{/if}</td></tr>{/foreach}
    </tbody></table></div>
  {else}
    <p class="text-muted">{l s='Todavía no se ha generado ninguna copia de reinicialización.' mod='fabricssamples'}</p>
  {/if}
</div>

{if $fs_diagnostic_report.history_pending|@count > 0}
<div class="panel fs-diagnostic-section fs-history-pending-panel">
  <div class="panel-heading"><i class="icon-warning-sign"></i> {l s='Líneas históricas pendientes de clasificar' mod='fabricssamples'}</div>
  <div class="alert alert-warning">
    {l s='Primero pulse Reconstruir históricos. Si después siguen apareciendo líneas que no fueron creadas por este módulo, use Descartar pendientes. Descartar solo elimina el aviso: no borra ni modifica pedidos, facturas o líneas de pedido.' mod='fabricssamples'}
  </div>
  <div class="table-responsive-row clearfix">
    <table class="table fs-diagnostic-table">
      <thead>
        <tr>
          <th>{l s='Pedido' mod='fabricssamples'}</th>
          <th>{l s='Línea' mod='fabricssamples'}</th>
          <th>{l s='Producto' mod='fabricssamples'}</th>
          <th>{l s='Nombre detectado' mod='fabricssamples'}</th>
          <th>{l s='Clasificación' mod='fabricssamples'}</th>
          <th>{l s='Fecha' mod='fabricssamples'}</th>
        </tr>
      </thead>
      <tbody>
      {foreach from=$fs_diagnostic_report.history_pending item=row}
        <tr>
          <td>#{$row.id_order|intval}{if $row.order_reference} · {$row.order_reference|escape:'htmlall':'UTF-8'}{/if}</td>
          <td>#{$row.id_order_detail|intval}</td>
          <td>#{$row.product_id|intval}{if $row.product_reference} · {$row.product_reference|escape:'htmlall':'UTF-8'}{/if}</td>
          <td>{$row.product_name|escape:'htmlall':'UTF-8'}</td>
          <td>{if $row.repairable}<span class="label label-warning">{l s='Reparable' mod='fabricssamples'}</span>{else}<span class="label label-default">{l s='Revisar' mod='fabricssamples'}</span>{/if} {$row.classification|escape:'htmlall':'UTF-8'}</td>
          <td>{$row.date_add|escape:'htmlall':'UTF-8'}</td>
        </tr>
      {/foreach}
      </tbody>
    </table>
  </div>
  {if $fs_diagnostic_report.history_pending|@count >= 100}<p class="help-block">{l s='Se muestran las primeras 100 líneas pendientes.' mod='fabricssamples'}</p>{/if}
</div>
{/if}

{foreach from=$fs_diagnostic_report.sections key=section_key item=section}
  <div class="panel fs-diagnostic-section">
    <div class="panel-heading">{$section.title|escape:'htmlall':'UTF-8'}</div>
    <div class="table-responsive-row clearfix">
      <table class="table fs-diagnostic-table">
        <thead>
          <tr>
            <th>{l s='Comprobación' mod='fabricssamples'}</th>
            <th>{l s='Estado' mod='fabricssamples'}</th>
            <th>{l s='Valor' mod='fabricssamples'}</th>
            <th>{l s='Detalle' mod='fabricssamples'}</th>
          </tr>
        </thead>
        <tbody>
        {foreach from=$section.items item=item}
          <tr>
            <td><strong>{$item.label|escape:'htmlall':'UTF-8'}</strong></td>
            <td><span class="fs-diagnostic-status fs-status-{$item.status|escape:'htmlall':'UTF-8'}">{$item.status|escape:'htmlall':'UTF-8'}</span></td>
            <td>{$item.value|escape:'htmlall':'UTF-8'}</td>
            <td>{$item.details|escape:'htmlall':'UTF-8'}</td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    </div>
  </div>
{/foreach}

<div class="alert alert-info">
  {l s='Informe generado:' mod='fabricssamples'} {$fs_diagnostic_report.generated_at|escape:'htmlall':'UTF-8'}.
  {if $fs_shop_id > 0}{l s='Tienda analizada:' mod='fabricssamples'} {$fs_shop_id|intval}.{else}{l s='Se está analizando el contexto multitienda actual.' mod='fabricssamples'}{/if}
</div>
