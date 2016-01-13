define(
    'moder/cars/car',
    ['jquery', 'tinymce', 'bootstrap', 'typeahead'],
    function($) {
        return {
            init: function(options) {
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
                    valid_elements : "a[href|title],p,br",
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
                            $li.children('.fa-plus-circle').on('click', function() {
                                $li.addClass('active');
                            });
                            $li.children('.fa-minus-circle').on('click', function() {
                                $li.removeClass('active');
                            });
                        });
                    });
                });
                
                $('#catalogue').on('tabload', function() {
                    
                    $catalogueTab = $(this);
                    
                    $catalogueTab.find('form.brand-car-type').each(function() {
                        var $form = $(this),
                            $select = $form.find('select');
                        $select.on('change', function() {
                            $select.prop('disabled', true);
                            $.post($form.attr('action'), {type: $select.val()}, function(json) {
                                if (json.ok) {
                                    $select.prop('disabled', false);
                                } else {
                                    alert(json.messages);
                                }
                            }, 'json');
                        });
                    });
                    
                    $catalogueTab.find('form.car-parent-catname').on('submit', function(e) {
                        e.preventDefault();
                        
                        var $form = $(this),
                            $input = $form.find(':text'),
                            data = $form.serializeArray();

                        $input.prop('disabled', true);
                        $.post($form.attr('action'), data, function(json) {
                            if (json.ok) {
                                $input.filter('[name=name]').val(json.name);
                                $input.filter('[name=catname]').val(json.catname);
                                
                                $.map(json.urls, function(urls, id) {
                                    $catalogueTab.find('tr').filter(function() {
                                        return $(this).data('id') == id
                                    }).find('.urls').each(function() {
                                        var $cell = $(this).empty();
                                        $.map(urls, function(url) {
                                            $cell.append([
                                                $('<a />', {
                                                    href: url,
                                                    text: url
                                                }),
                                                '<br />'
                                            ]);
                                        })
                                    })
                                });
                            } else {
                                alert(json.messages.join("\n"));
                            }
                            $input.prop('disabled', false);
                        }, 'json');
                    });
                    
                    $catalogueTab.find('form.car-parent-type').each(function() {
                        var $form = $(this),
                            $select = $form.find('select');
                        $select.on('change', function() {
                            $select.prop('disabled', true);
                            $.post($form.attr('action'), {type: $select.val()}, function(json) {
                                if (json.ok) {
                                    $select.prop('disabled', false);
                                } else {
                                    alert(json.messages);
                                }
                            }, 'json');
                        });
                    });
                    
                    var self = this;
                    
                    $catalogueTab.find('form.car-add-parent').each(function() {
                        var map = {},
                            $form = $(this),
                            $input = $form.find(':text');
                        
                        $form.on('submit', function(e) {
                            e.preventDefault();
                        })
                    
                        $input
                            .typeahead({
                                minLength: 1,
                                items: 20,
                                showHintOnFocus: true,
                                source: function(query, process) {
                                    $.getJSON($input.data('autocomplete'), {q: query}, function(cars) {
                                        var lines = [];
                                        map = {};
                                        $.map(cars, function(car) {
                                            lines.push(car.name);
                                            map[car.name] = car.id;
                                        });
                                        process(lines);
                                    });
                                },
                                matcher: function(item) {
                                    return true;
                                },
                                updater: function(item) {
                                    if (map[item]) {
                                        var id = map[item];
                                        
                                        $.post($form.attr('action'), {parent_id: id}, function(json) {
                                            window.location = json.url;
                                        });
                                    }
                                    
                                    return item;
                                },
                                highlighter: function (item) {
                                    var groupPattern = 'Group: ',
                                        isGroup = false;
                                    if (item.substring(0, groupPattern.length) == groupPattern) {
                                        item = item.substring(groupPattern.length);
                                        isGroup = true;
                                    }
                                    var query = this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
                                    return (isGroup ? '<i class="fa fa-folder-o"></i> ' : '') + item.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
                                        return '<strong>' + match + '</strong>';
                                    });
                                },
                                sorter: function(items) {
                                    return items;
                                }
                            })
                            .on('keyup', function(e) {
                                if (e.which == 13) {
                                    var value = $(this).val();
                                    if (map[value]) {
                                        var id = map[value];
                                        
                                        self.postAddParent(id);
                                    }
                                }
                            });
                    });
                });
                
                $('#tree').on('tabload', function() {
                    var $tree = $(this).find('.tree');
                    $(this).find('.btn').on('click', function() {
                        $tree[$(this).hasClass('active') ? 'removeClass' : 'addClass']('stock-only');
                    })
                });
                
                $('a[data-toggle="tab"][data-activate]').tab('show');
            }
        }
    }
);