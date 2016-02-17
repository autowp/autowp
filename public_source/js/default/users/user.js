define(
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function(options) {
                
                var self = this;
                
                $('.btn-contact').on('click', function() {
                	var $btn = $(this);
                	var value = $btn.hasClass('in-contacts');
                	
                	$.post(options.contactApiUrl, {value: value ? 0 : 1}, function(json) {
                		$btn.toggleClass('in-contacts');
                	})
                });
            }
        }
    }
);