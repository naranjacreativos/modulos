{if $fs_show_result_count}<p class="fabric-samples-result-count">{$fs_result_count_text|replace:'%count%':$fs_total|escape:'htmlall':'UTF-8'}</p>{/if}
<div class="fabric-samples-grid">
  {foreach from=$fs_products item=p}
    <article class="fabric-sample-card" data-id-product="{$p.id_product|intval}">
      {if $fs_show_card_label}<div class="fabric-sample-label">{$fs_card_label|escape:'htmlall':'UTF-8'}</div>{/if}
      {if $fs_show_card_image}<a class="fabric-sample-image" href="{$p.url|escape:'htmlall':'UTF-8'}">
        {if $p.image}<img src="{$p.image|escape:'htmlall':'UTF-8'}" alt="{$p.name|escape:'htmlall':'UTF-8'}" loading="lazy">{else}<span class="fabric-sample-no-image">{$fs_no_image_text|escape:'htmlall':'UTF-8'}</span>{/if}
      </a>{/if}
      <div class="fabric-sample-body">
        {if $fs_show_card_name}<h2>{$p.name|escape:'htmlall':'UTF-8'}</h2>{/if}
        {if $fs_show_card_reference}<p><strong>{$fs_reference_label|escape:'htmlall':'UTF-8'}</strong> {$p.reference|escape:'htmlall':'UTF-8'}</p>{/if}
        {if $fs_show_card_category && $p.category_name}<p><strong>{$fs_category_label|escape:'htmlall':'UTF-8'}</strong> {$p.category_name|escape:'htmlall':'UTF-8'}</p>{/if}
        {if $fs_show_card_explainer && $p.card_explainer_html}<div class="fabric-sample-explainer">{$p.card_explainer_html nofilter}</div>{/if}
        {if $fs_show_card_price}<div class="fabric-sample-price">{$p.price_formatted|escape:'htmlall':'UTF-8'}</div>{/if}
        <div class="fabric-sample-actions">
          {if $fs_show_in_cart_status && $p.in_cart_quantity>0}<p class="fabric-sample-in-cart js-fs-in-cart-status">{$fs_in_cart_text|replace:'%count%':$p.in_cart_quantity|escape:'htmlall':'UTF-8'}</p>{/if}
          <button type="button" class="btn fabric-sample-button fabric-sample-add-button js-fs-add-sample" style="{$fs_add_button_style|escape:'htmlall':'UTF-8'}" data-id-product="{$p.id_product|intval}"{if !$p.can_add} disabled aria-disabled="true"{/if}>
            {if $p.can_add}{$fs_add_button_text|escape:'htmlall':'UTF-8'}{else}{$fs_limit_reached_text|escape:'htmlall':'UTF-8'}{/if}
          </button>
          <p class="fabric-sample-added-message js-fs-added-message" role="status" aria-live="polite" hidden>{$fs_added_text|escape:'htmlall':'UTF-8'}</p>
          <p class="fabric-sample-error-message js-fs-error-message" role="alert" hidden></p>
          {if $fs_allow_remove && $p.id_customization>0}<button type="button" class="fabric-sample-remove-button js-fs-page-remove" data-id-customization="{$p.id_customization|intval}">{$fs_remove_sample_text|escape:'htmlall':'UTF-8'}</button>{/if}
          {if $fs_show_card_product_link}<a class="btn fabric-sample-button fabric-sample-product-link-button" role="button" style="{$fs_product_link_button_style|escape:'htmlall':'UTF-8'}" href="{$p.url|escape:'htmlall':'UTF-8'}">{if $fs_product_link_image}<img class="fabric-sample-product-link-image" src="{$fs_product_link_image|escape:'htmlall':'UTF-8'}" alt="{$fs_product_link_text|escape:'htmlall':'UTF-8'}" loading="lazy">{/if}<span>{$fs_product_link_text|escape:'htmlall':'UTF-8'}</span></a>{/if}
        </div>
      </div>
    </article>
  {foreachelse}
    <div class="alert alert-info fabric-samples-empty">
      {if $fs_configured_total > 0}{$fs_empty_filtered_text|escape:'htmlall':'UTF-8'}{else}{$fs_empty_config_text|escape:'htmlall':'UTF-8'}{/if}
    </div>
  {/foreach}
</div>

{if $fs_pages>1}<nav class="fabric-samples-pagination" aria-label="Paginación">{foreach from=$fs_pagination_window item=page_number}{if $page_number>0}<a class="js-fs-page-link{if $fs_page==$page_number} active{/if}" href="{$fs_form_url|escape:'htmlall':'UTF-8'}{$fs_url_separator|escape:'htmlall':'UTF-8'}page={$page_number|intval}&amp;q={$fs_q|urlencode}&amp;id_category={$fs_id_category|intval}&amp;order={$fs_order|escape:'url'}&amp;per_page={$fs_per_page|intval}">{$page_number|intval}</a>{else}<span class="fabric-samples-page-gap" aria-hidden="true">…</span>{/if}{/foreach}</nav>{/if}
