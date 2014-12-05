
<div class="text-center">
	<div class="btn-group">
		<button type="button" id="credit_btn" class="btn btn-default skt-testconnection">CHECK CREDIT</button>
		<button type="button" id="test_btn" class="btn btn-default skt-testconnection">TEST ORDER SMS</button>
	</div>
</div>

<br/>
<div id="credit-response" class="alert alert-dismissible">
</div>

<script type="text/javascript">

window.Skebby = window.Skebby || {};
window.Skebby.Client = window.Skebby.Client || {};


window.Skebby.Client.checkCredit = function(token) {
	$.getJSON('/modules/skebby/checkcredit.php?token=' + token).then(function(data) {
		if (data && data.status && data.status === 'success') {
			console.debug(data);
			var creditText = '€ ' + data.credit_left;
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
			alert('success');
		} else {
			alert('fail');
		}
	});
};


</script>

<script>
$(document).ready(function(){
	$("#credit_btn").on('click', function(){
		window.Skebby.Client.checkCredit('{$token}');
	});
	$("#test_btn").on('click', function(){
		window.Skebby.Client.testOrderSMS('{$token}');
	});
});

</script>