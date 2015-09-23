jQuery(function(){
	// HOMES DIV
	jQuery('.our-homes').bind('inview', function (event, visible) {		
		if (visible == true) {
			jQuery(this).addClass("inview");
		} else {
			jQuery(this).removeClass("inview");
		}
	});
	
	//LIFESTYLE DIV
	jQuery('.our-lifestyle').bind('inview', function (event, visible) {
			if (visible == true) {
				jQuery(this).addClass("inview");
			} else {
				jQuery(this).removeClass("inview");
			}
	});

	jQuery('.overlay').click(function() {
		jQuery('.overlay').css('pointer-events','none');
	});
});