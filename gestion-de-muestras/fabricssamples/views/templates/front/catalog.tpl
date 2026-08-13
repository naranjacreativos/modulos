{extends file='page.tpl'}
{block name='page_title'}{$fs_meta_title|default:$fs_page_title|escape:'htmlall':'UTF-8'}{/block}
{block name='head_seo_description'}{$fs_meta_description|escape:'htmlall':'UTF-8'}{/block}
{block name='page_content'}
<section class="fabric-samples-catalog js-fs-catalog" data-ajax-enabled="{if $fs_ajax_filters}1{else}0{/if}" data-in-cart-template="{$fs_in_cart_text|escape:'htmlall':'UTF-8'}" data-remove-text="{$fs_remove_sample_text|escape:'htmlall':'UTF-8'}" data-add-text="{$fs_add_button_text|escape:'htmlall':'UTF-8'}" style="--fs-accent:{$fs_page_accent_color|escape:'htmlall':'UTF-8'};--fs-background:{$fs_page_background_color|escape:'htmlall':'UTF-8'};--fs-columns-desktop:{$fs_page_columns_desktop|intval};--fs-columns-tablet:{$fs_page_columns_tablet|intval};--fs-columns-mobile:{$fs_page_columns_mobile|intval};">
  {if $fs_page_custom_css}<style id="fabric-samples-custom-css">{$fs_page_custom_css nofilter}</style>{/if}
  <div class="fabric-samples-hero">
    <h1>{$fs_page_title|escape:'htmlall':'UTF-8'}</h1>
    <div class="fabric-samples-intro">{$fs_page_intro_html nofilter}</div>
    {if $fs_page_warning}<div class="fabric-samples-warning"><strong>{$fs_important_label|escape:'htmlall':'UTF-8'}</strong> {$fs_page_warning nofilter}</div>{/if}
  </div>

  <form method="get" action="{$fs_form_url|escape:'htmlall':'UTF-8'}" class="fabric-samples-filters js-fs-filter-form">
    <input type="text" name="q" value="{$fs_q|escape:'htmlall':'UTF-8'}" placeholder="{$fs_filter_search_placeholder|escape:'htmlall':'UTF-8'}" aria-label="{$fs_filter_search_placeholder|escape:'htmlall':'UTF-8'}">
    <select name="id_category" aria-label="{$fs_filter_all_categories|escape:'htmlall':'UTF-8'}"><option value="0">{$fs_filter_all_categories|escape:'htmlall':'UTF-8'}</option>{foreach from=$fs_categories item=c}<option value="{$c.id_category|intval}"{if $fs_id_category==$c.id_category} selected{/if}>{$c.name|escape:'htmlall':'UTF-8'}</option>{/foreach}</select>
    <select name="order" aria-label="{$fs_filter_button_text|escape:'htmlall':'UTF-8'}">
      <option value="name_asc"{if $fs_order=='name_asc'} selected{/if}>{$fs_filter_order_name_asc|escape:'htmlall':'UTF-8'}</option>
      <option value="name_desc"{if $fs_order=='name_desc'} selected{/if}>{$fs_filter_order_name_desc|escape:'htmlall':'UTF-8'}</option>
      <option value="price_asc"{if $fs_order=='price_asc'} selected{/if}>{$fs_filter_order_price_asc|escape:'htmlall':'UTF-8'}</option>
      <option value="price_desc"{if $fs_order=='price_desc'} selected{/if}>{$fs_filter_order_price_desc|escape:'htmlall':'UTF-8'}</option>
      <option value="newest"{if $fs_order=='newest'} selected{/if}>{$fs_filter_order_newest|escape:'htmlall':'UTF-8'}</option>
      <option value="popular"{if $fs_order=='popular'} selected{/if}>{$fs_filter_order_popular|escape:'htmlall':'UTF-8'}</option>
    </select>
    <label class="fabric-samples-per-page"><span>{$fs_per_page_text|escape:'htmlall':'UTF-8'}</span><select name="per_page">{foreach from=$fs_per_page_options item=option}<option value="{$option|intval}"{if $fs_per_page==$option} selected{/if}>{$option|intval}</option>{/foreach}</select></label>
    <button class="btn fabric-sample-button fabric-sample-filter-button" style="{$fs_filter_button_style|escape:'htmlall':'UTF-8'}" type="submit">{$fs_filter_button_text|escape:'htmlall':'UTF-8'}</button>
  </form>

  <div class="js-fs-catalog-results" aria-live="polite">
    {include file='module:fabricssamples/views/templates/front/_catalog_results.tpl'}
  </div>
</section>
{/block}
