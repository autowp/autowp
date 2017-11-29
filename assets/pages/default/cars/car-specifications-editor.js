define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                
                $('.spec-editor-form .subform-header').hover(function() {
                    $(this).addClass('hover');
                    $('.' + $(this).attr('id')).addClass('hover');
                }, function() {
                    $(this).removeClass('hover');
                    $('.' + $(this).attr('id')).removeClass('hover');
                });
                
                $('a[data-toggle="tab"][data-load]').on('show.bs.tab', function (e) {
                    var $this = $(this),
                          selector = $this.attr('data-target'),
                          $target;

                    if (!selector) {
                        selector = $this.attr('href');
                        selector = selector && selector.replace(/.*(?=#[^\s]*$)/, ''); //strip for ie7
                    }

                    $target = $(selector);

                    $target.load($(this).data('load'), function() {
                        $target.trigger('tabload');
                    });

                    $(this).removeData('load').removeAttr('data-load');
                });
                
                $('#inherit-car-engine').on('click', function(e) {
                    e.preventDefault();
                    
                    var self = this;
                    
                    $.ajax({
                        method: 'PUT',
                        url: '/api/item/' + $(this).data('id'),
                        data: JSON.stringify({
                            engine_id: 'inherited'
                        }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        success: function() {
                            console.log('inherit');
                            window.location.href = '/cars/car-specifications-editor/item_id/' + $(self).data('id') + '/tab/engine';
                        }
                    }).always(function() {
                    	console.log('inherit');
                        window.location.href = '/cars/car-specifications-editor/item_id/' + $(self).data('id') + '/tab/engine';
                    });
                });
                
                $('#dont-inherit-car-engine, #cancel-car-engine').on('click', function(e) {
                    e.preventDefault();
                    
                    var self = this;
                    
                    $.ajax({
                        method: 'PUT',
                        url: '/api/item/' + $(this).data('id'),
                        data: JSON.stringify({
                            engine_id: ''
                        }),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                    }).always(function() {
                    	console.log('inherit');
                        window.location.href = '/cars/car-specifications-editor/item_id/' + $(self).data('id') + '/tab/engine';
                    });
                });
            }
        };
    }
);