define(
    'default/cars/car-specifications-editor',
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
                    var $this = $(this)
                        , selector = $this.attr('data-target')
                        , $target

                    if (!selector) {
                        selector = $this.attr('href')
                        selector = selector && selector.replace(/.*(?=#[^\s]*$)/, '') //strip for ie7
                    }

                    $target = $(selector);

                    $target.load($(this).data('load'), function() {
                        $target.trigger('tabload');
                    });

                    $(this).removeData('load').removeAttr('data-load');
                });
            }
        }
    }
);