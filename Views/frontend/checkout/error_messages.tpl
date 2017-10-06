{extends file="parent:frontend/checkout/error_messages.tpl"}

{block name='frontend_checkout_error_messages_voucher_error' append}

	{block name="frontend_checkout_connect_error_messages"}
		{if $connectMessages}
			{foreach from=$connectMessages item=connectmessage}
				{$message = $connectmessage->message}
				{foreach from=$connectmessage->values key=key item=value}
					{$message = "%{$key}"|str_replace:$value:$message}
				{/foreach}
				{$messages[] = $message}
			{/foreach}

			{include file="frontend/_includes/messages.tpl" type="error" list=$messages}
		{/if}
	{/block}

	{* Phone missing error *}
	{block name="frontend_checkout_error_phone_missing"}
		{if $phoneMissing}
			{include file="frontend/_includes/messages.tpl" type="error" content="<a href='{url controller=account action=billing sTarget=checkout}'>{s namespace="frontend/checkout/connect" name="frontend_checkout_cart_connect_phone"}You need to leave your phone number in order to purchase these products. Click here in order to change your phone number now.{/s}</a>"}
		{/if}
	{/block}
{/block}