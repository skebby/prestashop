
<div class="text-center">
	<button type="button" id="test_btn" class="btn btn-primary skt-testconnection">CHECK CREDIT</button>
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


</script>

<script>
$(document).ready(function(){
	$("#test_btn").on('click', function(){
		window.Skebby.Client.checkCredit('{$token}');
	});
});

</script>