define(
    'moder/cars/car',
    ['jquery', 'tinymce', 'bootstrap'],
    function($) {
        return {
            init: function(options) {
                $('textarea#description').tinymce({
                    // Location of TinyMCE script
                    script_url : '/tiny_mce/tinymce.min.js',

                    plugins: [
                        "link"
                    ],

                    // General options
                    theme: "modern",

                    menubar: false,
                    toolbar: "undo redo link unlink",
                    resize: true,

                    // Example content CSS (should be your site CSS)
                    //content_css : "/css/styles.css",

                    object_resizing : false,
                    convert_fonts_to_spans : true,
                    force_p_newlines : true,
                    //remove_trailing_nbsp : true,
                    //trim_span_elements : true,
                    valid_elements : "a[href|title],p",
                    language: "en"
                });

                $('textarea#html').tinymce({
                    // Location of TinyMCE script
                    script_url : '/tiny_mce/tinymce.min.js',

                    plugins: [
                        "link advlist lists"
                    ],

                    // General options
                    theme: "modern",

                    menubar: false,
                    toolbar: "undo redo link unlink bullist numlist",
                    resize: true,

                    // Example content CSS (should be your site CSS)
                    //content_css : "/css/styles.css",

                    object_resizing : false,
                    convert_fonts_to_spans : true,
                    force_p_newlines : true,
                    //remove_trailing_nbsp : true,
                    //trim_span_elements : true,
                    valid_elements : "a[href|title],p,ul,li,ol",
                    language: "en"
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
                $('#categories').on('tabload', function() {
                    $(this).find('#car-categories').each(function() {
                        var $form = $(this);

                        $form.on('submit', function(e) {
                            e.preventDefault();

                            $form.find(':submit').button('loading');
                            $.post($form.attr('action'), $form.serializeArray(), function() {
                                $form.find(':submit').button('reset');
                            }, 'json');
                        });

                        $(this).find('.checkbox-tree > li').each(function() {
                            var $li = $(this);
                            $li.children('.glyphicon-plus-sign').on('click', function() {
                                $li.addClass('active');
                            });
                            $li.children('.glyphicon-minus-sign').on('click', function() {
                                $li.removeClass('active');
                            });
                        });
                    });
                });
                $('.subscription').each(function() {
                    var
                        $block = $(this),
                        $subscribed = $block.find('.subscribed'),
                        $notSubscribed = $block.find('.not-subscribed');

                    $subscribed.on('click', function(e) {
                        e.preventDefault();
                        $subscribed.button('loading');
                        $.post($(this).attr('href'), function() {
                            $subscribed.button('reset');
                            $subscribed.hide();
                            $notSubscribed.show();
                        });
                    });

                    $notSubscribed.on('click', function(e) {
                        e.preventDefault();
                        $notSubscribed.button('loading');
                        $.post($(this).attr('href'), function() {
                            $notSubscribed.button('reset');
                            $notSubscribed.hide();
                            $subscribed.show();
                        });
                    });
                });
                $('form.generation').each(function() {
                    var $form = $(this),
                        $select = $form.find('select');
                    $select.on('change', function() {
                        $select.prop('disabled', true);
                        $.post($form.attr('action'), {generation_id: $select.val()}, function(json) {
                            if (json.ok) {
                                $select.prop('disabled', false);
                            }
                        }, 'json');
                    });
                });
                $('form.model-car-type').each(function() {
                    var $form = $(this),
                        $select = $form.find('select');
                    $select.on('change', function() {
                        $select.prop('disabled', true);
                        $.post($form.attr('action'), {type: $select.val()}, function(json) {
                            if (json.ok) {
                                $select.prop('disabled', false);
                            }
                        }, 'json');
                    });
                });
            }
        }
    }
);