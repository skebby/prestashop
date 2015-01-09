{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
* @author PrestaShop SA <contact@prestashop.com>
* @copyright  2007-2015 PrestaShop SA
* @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*}

<div class="text-center">
	<div class="btn-group">
		<button type="button" id="credit_btn" class="btn btn-default ">{l s='CHECK REMAINING CREDIT' mod='skebby'}</button>
		<button type="button" id="test_btn" class="btn btn-default ">{l s='TEST NEW ORDER SMS' mod='skebby'}</button>
		<button type="button" id="testshipment_btn" class="btn btn-default ">{l s='TEST ORDER SHIPMENT SMS' mod='skebby'}</button>
	</div>
	<br/>
	<br/>
	<div class="alert alert-warning">{l s='Sending a test SMS will be deducted from your SMS credits.' mod='skebby'}</div>
</div>

<br/>
<div id="credit-response" class="alert alert-dismissible">
</div>

<script>

window.Skebby = window.Skebby || {};
window.Skebby.Client = window.Skebby.Client || {};
window.Skebby.Client.defaultCurrency = '€';

window.Skebby.Client.checkCredit = function(token) {
	$.getJSON('/modules/skebby/checkcredit.php?token=' + token).then(function(data) {
		if (data && data.status && data.status === 'success') {
			var creditText = window.Skebby.Client.defaultCurrency + ' ' + data.credit_left;
			$('#credit-response').html(creditText).removeClass('alert-danger').addClass('alert-success').show();
		} else {
			var failedText = data.message;
			$('#credit-response').html(failedText).removeClass('alert-success').addClass('alert-danger').show();
		}
	});
};


window.Skebby.Client.testOrderSMS = function(token) {
	$.getJSON('/modules/skebby/testordermessage.php?token=' + token).then(function(data) {
		if (data && data.status && data.status === 'success') {
			alert("{l s='SMS successfully sent.' mod='skebby' js=1}");
		} else {
			alert("{l s='SMS send failed.' mod='skebby' js=1}");
		}
	});
};

window.Skebby.Client.testShipmentSMS = function(token) {
	$.getJSON('/modules/skebby/testshipmentnotification.php?token=' + token).then(function(data) {
		if (data && data.status && data.status === 'success') {
			alert("{l s='SMS successfully sent.' mod='skebby' js=1}");
		} else {
			alert("{l s='SMS send failed.' mod='skebby' js=1}");
		}
	});
};

$(document).ready(function(){
	$("#credit_btn").on('click', function(){
		window.Skebby.Client.checkCredit('{$token|escape:'stringval'}');
	});
	$("#test_btn").on('click', function(){
		window.Skebby.Client.testOrderSMS('{$token|escape:'stringval'}');
	});
	$("#testshipment_btn").on('click', function(){
		window.Skebby.Client.testShipmentSMS('{$token|escape:'stringval'}');
	});
});

</script>