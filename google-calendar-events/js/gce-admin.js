/**
 * Admin JavaScript functions
 *
 * @package   GCE
 * @author    Phil Derksen <pderksen@gmail.com>, Nick Young <mycorpweb@gmail.com>
 * @license   GPL-2.0+
 * @copyright 2014 Phil Derksen
 */

(function ($) {
	"use strict";
	$(function () {
		
		// Show the hidden text box if custom date is selected  (Events per Page)
		$('body').on('change', 'select[id*=gce_events_per_page]', function() {

			// Hide everything before showing what we want
			$('.gce_per_page_num_wrap').hide();
			
			if( $(this).val() == 'days' || $(this).val() == 'events' ) {
				$('.gce_per_page_num_wrap').show();
			}
		});
		
		$('body').on('change', 'select[id*=gce_display_mode]', function() {

			if( $(this).val() == 'date-range' ) {
				$('.gce-display-option').hide();
				$('.gce-custom-range').show();
			} else {
				$('.gce-display-option').show();
				$('.gce-custom-range').hide();
			}
		});
		
		
		// Add jQuery date picker to our custom date fields
		// We have to do it this way because the widget will break after clicking "Save" and this method fixes this problem
		// REF: http://stackoverflow.com/a/10433307/3578774
		$('body').on('focus', 'input[id*=gce_feed_range_start]', function(){
			$(this).datepicker();
		});
		
		$('body').on('focus', 'input[id*=gce_feed_range_end]', function(){
			$(this).datepicker();
		});
	
	});
}(jQuery));


