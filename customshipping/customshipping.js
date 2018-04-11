$(document).ready(function(){
	$('#carrier_form p:last').after('<p style="display:none;"><label for="custom_shipping_price">Custom shipping price: </label><input class="cs_input_field" type="text" name="custom_shipping_price" id="custom_shipping_price"><a href="#" class="btn btn-default" id="custom_shipping_price_set"><i class="icon-check"></i> Update</p>');

	var display_custom_price_field = function(e) {
		console.log($('#delivery_option').val());
		if ($('#delivery_option').val() == customshipping_carrier_id + ',') {
		console.log("ifbranch");
			$('#shipping_price').parent('p').hide();
			$('#custom_shipping_price').val($('#shipping_price').text());
			$('#custom_shipping_price').parent('p').show();
			$('#free_shipping').parent('p').hide();
		} else {
		console.log("elsebranch");
			$('#shipping_price').parent('p').show();
			$('#custom_shipping_price').parent('p').hide();
			$('#free_shipping').parent('p').show();
		}
	};

	$('#delivery_option').bind('change', display_custom_price_field);

	setTimeout(function() {
		if ($('#carriers_part:visible').length)
			return display_custom_price_field();
		setTimeout(arguments.callee, 300)
	}, 300);

	$('#custom_shipping_price_set').bind('click', function(e) {
		e.preventDefault();
		$.ajax({
			type: 'POST',
			url: customshipping_ajax_url,
			dataType: 'json',
			data: {
				'ajax': true,
				'token': customshipping_token,
				'id_cart': id_cart,
				'id_customer': id_customer,
				'value': $('#custom_shipping_price').val(),
			},
			success: function(res) {
				updateDeliveryOption();
			},
		});
		return false;
	});
});
