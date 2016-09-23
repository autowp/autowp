define(
    'brand-popover',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
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
                                    
                                    function get_popover_placement(pop, dom_el) {
                                        var width = window.innerWidth;
                                        if (width<500) return 'bottom';
                                        var left_pos = $(dom_el).offset().left;
                                        if (width - left_pos > 400) return 'right';
                                        return 'left';
                                    }
                                    
                                    $(self).popover({
                                        trigger: 'manual',
                                        content: html,
                                        html: true,
                                        placement: 'bottom', //get_popover_placement
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