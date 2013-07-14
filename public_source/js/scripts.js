$(function() {
    $('.navbar .online a').each(function() {
        var 
            $modal = null,
            $body = null,
            $btnRefresh = null,
            url = $(this).attr('href');
        
        function reload() {
            if (!$modal) {
                $modal = $(
                    '<div class="modal fade">\
                        <div class="modal-dialog">\
                            <div class="modal-content">\
                                <div class="modal-header">\
                                    <button type="button" data-dismiss="modal" class="close">×</button>\
                                    <h3 class="modal-title">Online</h3>\
                                </div>\
                                <div class="modal-body"></div>\
                                <div class="modal-footer">\
                                    <button class="btn btn-primary">Обновить</a>\
                                    <button data-dismiss="modal" class="btn btn-default">Закрыть</button>\
                                </div>\
                            </div>\
                        </div>\
                    </div>'
                );
                $body = $modal.find('.modal-body');
                $btnRefresh = $modal.find('.btn-primary').on('click', function(e) {
                    e.preventDefault();
                    reload();
                });
            }
            $body.empty();
            $modal.modal();
            
            $btnRefresh.button('loading');
            $.get(url, {}, function(html) {
                $body.html(html);
                $btnRefresh.button('reset');
            });
        }
        
        $(this).on('click', function(e) {
            e.preventDefault();
            reload();
        });
    });
    
    $('.inline-picture-preview').each(function() {
        var obj = {
            $element: null,
            $details: null,
            over: false,
            timer: null,
            delay: 2000,
            over: function() {
                if (this.timer)
                    clearTimeout(this.timer);
                    
                var $img = this.$element;
                var offset = $img.offset();
                this.$details
                    .css({
                        position: 'absolute',
                        left: offset.left + 'px',
                        top: (offset.top + $img.outerHeight()) + 'px'
                    })
                    .fadeIn(300);
            },
            out: function() {
                var self = this;
                this.timer = setTimeout(function() {
                    self.$details.fadeOut(300);
                }, this.delay);
            },
            init: function(element) {
                var self = this;
                
                this.$element = $(element);
                this.$details = this.$element.next().filter('.inline-picture-details');
                
                this.$element.hover(function() {
                    self.over();
                }, function() {
                    self.out();
                });
                this.$details.hover(function() {
                    self.over();
                }, function() {
                    self.out();
                });
                this.$details.css({
                    position: 'absolute',
                    left: 0,
                    top: 0
                }).hide();
            }
        }.init(this);
    });
    
    $('a.picture-hover-preview').each(function() {
        var href = $(this).attr('href');
        var element = null;
        var anchor = $(this);
        var loaded = false;
        
        var fadeOutTimer = null;
        
        function over() {
            clearInterval(fadeOutTimer);
            fadeOutTimer = null;
        }
        
        function out() {
            clearInterval(fadeOutTimer);
            fadeOutTimer = null;
            fadeOutTimer = setInterval(function() {
                element.hide();
                clearInterval(fadeOutTimer);
            }, 1500);
        }
        
        $(this).hover(function () {
            over();
            if (!element) {
                var offset = anchor.offset();
                element = $('<div style="position:absolute;width:170px">Loading ...</div>');
                element.css({
                    left: offset.left,
                    top: offset.top + anchor.height()
                });
                element.hover(over, out);
                
                $(document.body).append(element);
            }
            
            if (!loaded) {
                loaded = true;
                $.get(href, {preview: 1}, function(html) {
                    element.empty().append(html);
                    var offset = anchor.offset();
                    element.css({
                        left: offset.left,
                        top: offset.top + anchor.height()
                    });
                }, 'text');
            } else {
                element.show();
            }
        }, out);
        
    });
    
    $('.brands-switch li a').each(function(i) {
        $(this).on('click', function() {
            $(this).parent().siblings().removeClass('active');
            $(this).parent().addClass('active');
            $('#BrandGroupsContainer > ul').eq(i).show().siblings().hide();
            
            return false;
        });
    });
});

function deletePM(id, tr) {
    $.post('/account/delete-personal-message', {id: id}, function(json) {
        $(tr).empty();
        var table = tr.parentNode;
        table.removeChild(tr);
        $(table).find('tr > td').removeClass('odd').removeClass('even');
        $(table).find('tr:even > td').addClass('even');
        $(table).find('tr:odd > td').addClass('odd');
    }, 'json');
}

function moderatorAttentionCompleteBtn(id) {
    $('#ma-' + id).each(function() {
        var $block = $(this);
        $('.btn', this).on('click', function() {
            $(this).button('loading');
            $.post('/comments/complete', {id: id}, function() {
                $block.remove();
            });
        });
    });
}

function commentsBlock(options) {
    $('.remove-all-comments').on('click', function() {
        var self = this;
        $.post('/comments/delete-all', {item_id: options.itemId, type: options.type}, function(json) {
            $(self).hide();
            $('.commentsTable').remove();
        }, 'json');
    });
    
    $('.comment-remove-button').on('click', function() {
        var node = this; 
        var id = parseInt($(node).attr('id').replace('comment-remove-button-', ''));
        $.post('/comments/delete', {comment_id: id}, function(json) {
            if (json.ok) {
                $(node).parents('.message:first').fadeOut(function() {
                    $(this).remove();
                });
            } else {
                window.alert(json.message);
            }
        }, 'json');
        return false; 
    });
}

function mostIndexPage(options) {
    $('small.unit').tooltip({
        placement: 'bottom'
    });
}

function picturePage(options) {
    $("#picture_voting_bar, #voting_bar_positive, #voting_bar_vote").on('mousemove', function(e) {
        var bar = e.target;
        if ($(bar).attr("id") == "voting_bar_positive" || $(bar).attr("id") == "voting_bar_vote")
            bar = bar.parentNode;

        if ($(bar).hasClass("voted"))
            return;

        var x = e.pageX - $(bar).offset().left;
        x = Math.ceil(x / options.starWidth) * options.starWidth;
        $("#voting_bar_positive").hide();
        $("#voting_bar_vote").show().css({width: x+"px"});
        bar.hide = false;
    });
    $("#picture_voting_bar, #voting_bar_positive, #voting_bar_vote").on('mouseout', function(e) {
        var bar = e.target;
        if ($(bar).attr("id") == "voting_bar_positive" || $(bar).attr("id") == "voting_bar_vote")
            bar = bar.parentNode;

        if ($(bar).hasClass("voted"))
            return;

        this.hide = true;

        setTimeout(function() {
            if (bar.hide)
            {
                $("#voting_bar_positive").show();
                $("#voting_bar_vote").hide().css({width: "0px"});
                bar.hide = false;
            }
        }, 500);
    });
    $("#voting_bar_vote").on('click', function(e) {
        var bar = e.target.parentNode;

        if ($(bar).hasClass("voted"))
            return;

        var value = Math.ceil((e.pageX - $(bar).offset().left) / options.starWidth);

        $.post(options.voteUrl, { value: value },function(data) {
            $("#voting_bar_vote").hide().css({width: "0px"});
            $("#voting_bar_positive").animate({width: data.width+"px"}, 2000);
            $("#picture_voting_bar").addClass("voted");
            $("#picture_voting_bar, #voting_bar *").unbind();
        }, "json");
    });
    
    $('.picture-preview-medium a').on('click', function() {
        window.open($(this).attr('href'), '_blank');
        return false;
    });
}

function museumsIndexPage(options) {
    $('#google-map').each(function() {
        
        var startPosition = new google.maps.LatLng(52.48, 13.45);
        var museums = options.museums;
        
        var map = new google.maps.Map(this, {
            zoom: 2,
            center: startPosition,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });
        
        $.each(museums, function(key, museum) {
            if (museum.location) {
                var position = new google.maps.LatLng(museum.location.lat, museum.location.lng);
                var marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: museum.name
                });
                
                var info = $('<div />').append(
                    $('<p />').append($('<strong />').text(museum.name))
                );
                if (museum.desc) {
                    info.append($('<p />').text(museum.desc))
                }
                if (museum.url) {
                    info.append(
                        $('<p />').append(
                            $('<a />').text(museum.url).attr('href', museum.url)
                        )
                    );
                }
                if (museum.address) {
                    info.append($('<p />').text(museum.address))
                }
                
                var infowindow = new google.maps.InfoWindow({
                    content: info[0]
                });
                
                google.maps.event.addListener(marker, 'click', function() {
                    infowindow.open(map, marker);
                });
                
                $('#museum'+museum.id+'maplink').on('click', function() {
                    infowindow.open(map, marker);
                    map.setCenter(position);
                    map.setZoom(18);
                    $(window).scrollTop(0);
                    return false;
                });
            }
        });
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var latLng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
                var geocoder = new google.maps.Geocoder();
                geocoder.geocode({'latLng': latLng}, function(results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        var country = null;
                        $.each(results, function(i, address) {
                            $.each(address.address_components, function(j, component) {
                                $.each(component.types, function(k, type) {
                                    if (type == 'country') {
                                        country = component.long_name;
                                    }
                                    if (country) {
                                        return false;
                                    }
                                });
                                if (country) {
                                    return false;
                                }
                            });
                            if (country) {
                                return false;
                            }
                        });
                        
                        if (country) {
                            geocoder.geocode({'address': country}, function(results, status) {
                                $.each(results, function(i, address) {
                                    map.fitBounds(address.geometry.viewport);
                                    return false;
                                });
                            });
                        }
                    }
                });
                
            });
        }
    });
}

function forumTopicPage() {
    $('.permanent-link').on('click', function() {
        var offset = $(this).offset();

        var div = $(
            '<div>\
                <img src="/design/del.gif" alt="X" title="close" style="cursor:pointer;margin:0 0 5px 5px;float:right" />\
                <p>Постоянная ссылка на сообщение</p>\
                <input type="text" readonly="readonly" style="width:98%" /><br />\
             </div>'
        );

        $('input', div).val(
            'http://www.autowp.ru' + $(this).attr('href')
        );

        $('img', div)
            .hover(function() {
                $(this).attr('src', '/design/del.hover.gif')
            }, function() {
                $(this).attr('src', '/design/del.gif')
            })
            .on('click', function() {
                $(div).remove()
            });

        $(div).css({
            position: 'absolute',
            backgroundColor: 'white',
            padding: '5px',
            left: offset.left,
            top: offset.top + $(this).height(),
            border: 'border: 1px solid #cccccc',
            width: '230px'
        });

        $(document.body).append(div);
        return false;
    });
}

function popoverHandlers() {
    $('.popover-handler').each(function() {
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
                    $.get($(this).attr('href'), {}, function(html) {
                        
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
                            placement: get_popover_placement
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

function indexPage() {
    popoverHandlers();
}

function brandsPage() {
    popoverHandlers();
}

function showPMWindow(userId) {
    var form = $(
        '<div class="modal fade">\
            <div class="modal-dialog">\
                <form action="/account/send-personal-message" class="modal-content" method="post">\
                    <div class="modal-header">\
                        <a class="close">×</a>\
                        <h3 class="modal-title">Отправить личное сообщение</h3>\
                    </div>\
                    <div class="modal-body">\
                        <textarea cols="65" rows="5" name="contents" placeholder="Текст сообщения"></textarea>\
                    </div>\
                    <div class="modal-footer">\
                        <button class="btn btn-primary" data-loading-text="отправляется ..." data-complete-text="отправлено" data-send-text="отправить">отправить</button>\
                        <button class="btn btn-default cancel">отменить</button>\
                    </div>\
                </form>\
            </div>\
        </div>'
    );
    
    var $btnSend = $('.btn-primary', form).button();
    var $btnCancel = $('.cancel', form).button();
    var $textarea = $('textarea', form);
    
    form.modal({
        show: true
    });
                 
    form.on('hidden', function () {
        form.remove();
    });
    form.on('shown', function () {
        $textarea.focus();
    });
    
    
    $textarea.bind('change keyup click', function() {
        $textarea.parent().removeClass('error');
        $btnSend.text('отправить').removeClass('btn-success').prop('disabled', $(this).val().length <= 0);
    }).triggerHandler('change');
    
    $('button.cancel, a.close', form).on('click', function(e) {
        e.preventDefault();
        form.modal('hide');
    });
    
    form.submit(function(e) {
        e.preventDefault();
        
        var text = $textarea.val();
        
        if (text.length <= 0) {
            $textarea.parent().addClass('error');
        } else {
            $btnSend.button('loading');
            $btnCancel.prop("disabled", 1);
            $textarea.prop("disabled", 1);
            $.post(form.attr('action'), {user_id: userId, message: text}, function(json) {
                $textarea.val('');
                
                $btnSend.button('reset').button('complete').addClass('btn-success disabled').prop("disabled", 1);
                
                $textarea.prop("disabled", 0);
                $btnCancel.prop("disabled", 0);
            }, 'json');
        }
    });
}

function votingVotingPage() {
    $('.who-vote').each(function() {
        var 
            $a = $(this),
            $modal = null,
            $body = null,
            $btnRefresh = null,
            url = $a.attr('href');
        
        function reload() {
            if (!$modal) {
                $modal = $(
                    '<div class="modal hide fade">\
                        <div class="modal-header">\
                            <a data-dismiss="modal" class="close">×</a>\
                            <h3></h3>\
                        </div>\
                        <div class="modal-body"></div>\
                        <div class="modal-footer">\
                            <a class="btn btn-primary" href="#">Обновить</a>\
                            <a data-dismiss="modal" class="btn" href="#">Закрыть</a>\
                        </div>\
                    </div>'
                );
                $modal.find('h3').text($a.text());
                $body = $modal.find('.modal-body');
                $btnRefresh = $modal.find('.btn-primary').on('click', function(e) {
                    e.preventDefault();
                    reload();
                });
            }
            $body.empty();
            $modal.modal();
            
            $btnRefresh.button('loading');
            $.get(url, {}, function(html) {
                $body.html(html);
                $btnRefresh.button('reset');
            });
        }
        
        $(this).on('click', function(e) {
            e.preventDefault();
            reload();
        });
    });
}