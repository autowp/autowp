var $ = require('jquery');
var CarTypePicker = require('./car-type-picker');
var Markdown = require('markdown-edit-tabbar');
var Picker = require("latlng-picker");
require('typeahead');

module.exports = {
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
        
        $('a[data-toggle="tab"][data-load]').on('show.bs.tab', function (e) {
            var $this = $(this),
                loaded = $this.data('loaded'),
                selector = $this.attr('data-target'),
                $target;

            if (!selector) {
                selector = $this.attr('href');
                selector = selector && selector.replace(/.*(?=#[^\s]*$)/, ''); //strip for ie7
            }

            if (!loaded) {
                $target = $(selector);
    
                $target.load($(this).data('load'), function() {
                    $target.trigger('tabload');
                });
    
                $this.data('loaded', true);
            }
        });
        
        $('#meta').each(function() {
            CarTypePicker.init($(this).find('select[name=vehicle_type_id\\[\\]]'));
            CarTypePicker.init($(this).find('select[name=spec_ids\\[\\]]'));
            var $lat = $(this).find(':input[name=lat]');
            var $lng = $(this).find(':input[name=lng]');
            if ($lat.length && $lng.length) {
                new Picker({
                    lat: $lat,
                    lng: $lng
                });
            }
        });
        
        $('#name').on('tabload', function() {
        	Markdown(this);
        });
        
        $('#catalogue').on('tabload', function() {
            
            $catalogueTab = $(this);
            
            $catalogueTab.find('form.brand-item-type').each(function() {
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
                                return $(this).data('id') == id;
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
                                });
                            });
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
            
            $catalogueTab.find('form.car-add-parent').each(function() {
                var $form = $(this),
                    $input = $form.find(':text');
                
                $form.on('submit', function(e) {
                    e.preventDefault();
                });
            
                $input
                    .typeahead({ }, {
                        display: function(car) {
                            return car.name;
                        },
                        templates: {
                            suggestion: function(item) {
                                var $div = $('<div class="tt-suggestion tt-selectable"></div>')
                                    .text(item.name);
                                
                                $div.addClass(item.type);
                                
                                if (item.image) {
                                    $div.prepend($('<img />', {
                                        src: item.image
                                    }));
                                }
                                
                                return $div[0];
                            }
                        },
                        source: function(query, syncResults, asyncResults) {
                            $.getJSON($input.data('autocomplete'), {q: query}, function(cars) {
                                asyncResults(cars);
                            });
                        }
                    })
                    .on('typeahead:select', function(ev, car) {
                        $('<form method="post"></form>').attr('action', car.url).appendTo(document.body).submit();
                    });
            });
        });
        
        $('#tree').on('tabload', function() {
            var $tree = $(this).find('.tree');
            $(this).find('.btn').on('click', function() {
                $tree[$(this).hasClass('active') ? 'removeClass' : 'addClass']('stock-only');
            });
        });
        
        $('a[data-toggle="tab"][data-activate]').tab('show');
    }
};