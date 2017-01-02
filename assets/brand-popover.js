define(
    ['jquery'],
    function($) {
        return {
            apply: function(selector) {
                $(selector).each(function() {
                    var self = this,
                        loaded = false,
                        over = false;
                    
                    $(this)
                        .on('click', function(e) {
                            e.preventDefault();
                        })
                        .hover(function() {
                            over = true;
                            
                            if (loaded) {
                                $(this).popover('show');
                            } else {
                                $.get($(this).data('href'), {}, function(html) {
                                    
                                    $(self).popover({
                                        trigger: 'manual',
                                        content: html,
                                        html: true,
                                        placement: 'bottom',
                                    });
                                    loaded = true;
                                    if (over) {
                                        $(self).popover('show');
                                    }
                                });
                            }
                        }, function() {
                            over = false;
                            if (loaded) {
                                $(this).popover('hide');
                            }
                        });
                });
            }
        };
    }
);