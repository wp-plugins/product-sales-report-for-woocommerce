jQuery(document).ready(function($) {
	$('#hm_sbp_field_report_time').change(function() {
			$('.hm_sbp_custom_time').toggle(this.value == 'custom');
	});
	$('#hm_sbp_field_report_time').change();
});