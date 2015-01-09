
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
		window.Skebby.Client.checkCredit('{$token}');
	});
	$("#test_btn").on('click', function(){
		window.Skebby.Client.testOrderSMS('{$token}');
	});
	$("#testshipment_btn").on('click', function(){
		window.Skebby.Client.testShipmentSMS('{$token}');
	});
});

</script>