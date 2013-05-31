{* Include the stylesheet if we're dealing with an bepado product *}
{block name="frontend_index_header_css_screen"}
	{if $bepadoProduct}
		<link rel="stylesheet" href="{link file='frontend/_resources/styles/bepado.css'}" />
	{/if}
{/block}

{block name='frontend_detail_buy_button'}
	{if $bepadoProduct}
		{* Include the basket button *}
		<div class="bepado-detail-product">
			{$smarty.block.parent}

			<strong class="bepado-detail-product-headline">Marktplatz Artikel von {*$bepadoShop->name*}Libri.de Internet GmbH</strong>
			<p class="bepado-detail-product-desc">
				Die Versandkosten für diesen Artikel ...
			</p>
		</div>
	{else}
		{$smarty.block.parent}
	{/if}
{/block}