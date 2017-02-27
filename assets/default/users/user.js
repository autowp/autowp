define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                $('.btn-contact').on('click', function() {
                    var $btn = $(this);
                    var value = $btn.hasClass('in-contacts');
                    
                    $.ajax({
                        url: options.contactApiUrl,
                        method: value ? 'DELETE' : 'PUT',
                        success: function(data, textStatus, jqXHR) {
                            switch (jqXHR.status) {
                                case 204:
                                    $btn.removeClass('in-contacts');
                                    break;
                                case 200:
                                    $btn.addClass('in-contacts');
                                    break;
                            }
                        }
                    });
                });
            }
        };
    }
);