define(
    ['jquery'],
    function($) {
        
        var Gallery = function(items) {
            this.init(items);
        };
        
        Gallery.prototype = {
            init: function(options) {
                
                var self = this;
                
                var items = options.items;
                
                this.escHandler = function(e) {
                    if (e.keyCode == 27) { // esc
                        self.hide();
                    }
                };
                
                var id = 'picture-carousel';
                
                var markup = 
                    '<div class="gallery" tabindex="0">' +
                        '<div class="carousel slide">' +
                            '<ol class="carousel-indicators"></ol>' +
                            '<div class="carousel-inner"></div>' +
                            '<a class="left carousel-control" href="#'+id+'" role="button" data-slide="prev">' +
                                '<i class="fa fa-chevron-left glyphicon glyphicon-chevron-left"></i>' +
                            '</a>' +
                            '<a class="right carousel-control" href="#'+id+'" role="button" data-slide="next">' +
                                '<i class="fa fa-chevron-right glyphicon glyphicon-chevron-right"></i>' +
                            '</a>' +
                            '<a class="close carousel-control" href="#" role="button">' +
                                '<i class="fa fa-times"></i>' +
                            '</a>' +
                        '</div>' +
                    '</div>';
                
                this.$e = $(markup);
                
                var $carousel = this.$e.find('.carousel');
                this.$carousel = $carousel;
                
                $carousel.attr('id', id);
                
                var $indicators = $carousel.find('.carousel-indicators');
                this.$inner = $carousel.find('.carousel-inner');
                
                var $activeItem = null;
                
                $.each(items, function(idx, item) {
                    
                    var active = options.current == item.id;
                    
                    $('<li></li>', {
                        'data-target': '#' + id,
                        'data-slide-to': idx,
                        'class': active ? "active" : '',
                        appendTo: $indicators
                    });
                    
                    var $loading = $('<div class="loading-icon"><i class="fa fa-spinner fa-pulse"></i></div>');
                    
                    var $source = $(
                        '<a class="download carousel-control" role="button">' +
                            '<i class="fa fa-download"></i>' +
                            '<div class="badge badge-info"></div>' +
                        '</a>'
                    ).attr('href', item.sourceUrl);
                    
                    $source.find('.badge').text(item.filesize);
                    
                    var $details = $(
                        '<a class="details carousel-control" role="button">' +
                            '<i class="fa fa-picture-o"></i>' +
                        '</a>'
                    ).attr('href', item.url);
                    
                    var $comments = $(
                        '<a class="comments carousel-control" role="button">' +
                            '<i class="fa fa-comment"></i>' +
                        '</a>'
                    ).attr('href', item.url + '#comments');
                    
                    if (item.messages) {
                        var $badge = $('<div class="badge badge-info"></div>');
                        if (item.newMessages > 0) {
                            $badge.text(item.messages - item.newMessages);
                            $badge.append(
                                $('<span />', {
                                    text: '+' + item.newMessages
                                })
                            );
                        } else {
                            $badge.text(item.messages);
                        }
                        $comments.append($badge);
                    }
                    
                    var $caption = $(
                        '<div class="carousel-caption">' +
                            '<h3></h3>' +
                            //'<p></p>' +
                        '</div>'
                    );
                    
                    $caption.find('h3').text(item.name);
                    
                    var $item = $('<div class="item loading"></div>')
                        .data({
                            id: item.id,
                            full: item.full,
                            crop: item.crop
                        })
                        .append($caption)
                        .append($source)
                        .append($comments)
                        .append($details)
                        .append($loading)
                        .appendTo(self.$inner);
                    
                    if (item.crop) {
                        $(
                            '<a class="full carousel-control" role="button">' +
                                '<i class="fa fa-arrows-alt"></i>' +
                            '</a>'
                        ).appendTo($item);
                        
                    }
                    
                    if (active) {
                        $activeItem = $item;
                    }
                });
                
                if ($activeItem) {
                    $activeItem.addClass('active');
                    self.activateItem($activeItem, true);
                    self.fixArrows($activeItem);
                }
                
                this.$e.appendTo(document.body);
                $carousel.carousel({
                    interval: 0,
                    wrap: false
                });
                
                $carousel.on('slide.bs.carousel', function (e) {
                    var $item = $(e.relatedTarget);
                    
                    self.activateItem($item, true);
                    self.fixArrows($item);
                });
                
                $carousel.find('.carousel-control.close').on('click', function(e) {
                    e.preventDefault();
                    
                    self.hide();
                });
                
                $carousel.on('click', '.item .details.carousel-control', function(e) {
                    if (this.href == window.location.href) {
                        self.hide();
                        e.preventDefault();
                    }
                });
                
                $carousel.on('click', '.item .comments.carousel-control', function(e) {
                    if (this.href.replace('#comments', '') == window.location.href.replace('#comments', '')) {
                        self.hide();
                        $('body').scrollTop($("#comments").offset().top);
                        e.preventDefault();
                    }
                });
                
                $carousel.on('click', '.item img, .item .full.carousel-control', function() {
                    
                    var $item = $(this).closest('.item');
                    var crop = $item.data('crop');
                    if (crop) {
                        var cropMode = !$item.data('cropMode');
                        $item.data('cropMode', cropMode);
                        if (cropMode) {
                            $item.addClass('crop');
                        } else {
                            $item.removeClass('crop');
                        }
                        self.fixSize($item);
                    }
                });
                
                $(window).on('resize', function() {
                    self.fixSize(self.$e.find('.item'));
                });
                self.fixSize(this.$e.find('.item'));
            },
            activateItem: function($item, siblings) {
                if (!$item.data('activated')) {
                
                    $item.data('activated', true);
                    
                    var crop = $item.data('crop');
                    var full = $item.data('full');
                    
                    var cropMode = !!crop;
                    
                    $item.data('cropMode', cropMode);
                    
                    if (cropMode) {
                        $item.addClass('crop');
                        var $imgCrop = $('<img />', {
                            src: crop.src,
                            alt: '',
                            'class': 'crop'
                        });
                        $item.prepend($imgCrop);
                    }
                    
                    var $img = $('<img />', {
                        src: full.src,
                        alt: '',
                        'class': 'full'
                    }).on('load', function() {
                        $(this).closest('.item').removeClass('loading');
                    });
                    $item.prepend($img);
                    
                    
                }
                
                if (siblings) {
                    var $prev = $item.prev('.item');
                    if ($prev.length) {
                        this.activateItem($prev, false);
                    }
                    var $next = $item.next('.item');
                    if ($next.length) {
                        this.activateItem($next, false);
                    }
                }
                
                this.fixSize($item);
            },
            fixArrows: function($item) {
                var $left = this.$e.find('.carousel-control.left');
                var $right = this.$e.find('.carousel-control.right');
                
                var $prev = $item.prev('.item');
                if ($prev.length) {
                    $left.show();
                } else {
                    $left.hide();
                }
                var $next = $item.next('.item');
                if ($next.length) {
                    $right.show();
                } else {
                    $right.hide();
                }
            },
            bound: function(container, content) {
                
                var containerRatio = container.width / container.height;
                var contentRatio = content.width / content.height;
                
                var width, height;
                if (contentRatio > containerRatio) {
                    width = container.width;
                    height = width / contentRatio;
                } else {
                    height = container.height;
                    width = height * contentRatio;
                }
                
                return {
                    width: width,
                    height: height
                };
            },
            boundCenter: function(container, content) {
                return {
                    left: (container.width - content.width) / 2,
                    top: (container.height - content.height) / 2,
                    width: content.width,
                    height: content.height
                };
            },
            maxBounds: function(bounds, maxBounds) {
                if (bounds.height > maxBounds.height || bounds.width > maxBounds.width) {
                    return maxBounds;
                }
                return bounds;
            },
            fixSize: function($items) {
                var w = this.$inner.width();
                var h = this.$inner.height();
                
                var cSize = {
                    width: w,
                    height: h
                };
                
                var r = w/h;
                
                var self = this;
                
                $items.each(function() {
                    var $item = $(this);
                    var $imgFull = $item.find('img.full');
                    var $imgCrop = $item.find('img.crop');
                    var full = $item.data('full');
                    var crop = $item.data('crop');
                    var cropMode = $item.data('cropMode');
                    
                    var bounds;
                    var offsetBounds;
                    
                    if (crop) {

                        if (cropMode) {
                            bounds = self.maxBounds(self.bound(cSize, {
                                width: crop.width,
                                height: crop.height
                            }), {
                                width: crop.width,
                                height: crop.height
                            });
                            
                            offsetBounds = self.boundCenter(cSize, bounds);
                            $imgCrop.css(offsetBounds);
                            var fullWidth = bounds.width / crop.crop.width;
                            var fullHeight = bounds.height / crop.crop.height;
                            $imgFull.css({
                                left: offsetBounds.left - crop.crop.left * fullWidth,
                                top: offsetBounds.top - crop.crop.top * fullHeight,
                                width: fullWidth,
                                height: fullHeight
                            });
                        } else {
                            bounds = self.maxBounds(self.bound(cSize, {
                                width: full.width,
                                height: full.height
                            }), {
                                width: full.width,
                                height: full.height
                            });
                            offsetBounds = self.boundCenter(cSize, bounds);
                            $imgFull.css(offsetBounds);
                            $imgCrop.css({
                                left: offsetBounds.left + crop.crop.left * bounds.width,
                                top: offsetBounds.top + crop.crop.top * bounds.height,
                                width: bounds.width * crop.crop.width,
                                height: bounds.height * crop.crop.height
                            });
                        }
                        
                    } else {
                        bounds = self.maxBounds(self.bound(cSize, {
                            width: full.width,
                            height: full.height
                        }), {
                            width: full.width,
                            height: full.height
                        });
                        offsetBounds = self.boundCenter(cSize, bounds);
                        $imgFull.css(offsetBounds);
                    }
                });
            },
            hide: function() {
                this.$e.hide();
                $(document.body).removeClass('gallery-shown');
                
                $(document).off('keyup', this.escHandler);
            },
            show: function() {
                $(document.body).addClass('gallery-shown');
                this.$e.show();
                this.fixSize(this.$e.find('.item'));
                
                $(document).on('keyup', this.escHandler);
                
                this.$e.find('a.carousel-control.right').focus();
            },
            rewindToId: function(id) {
                var self = this;
                this.$carousel.find('.item').each(function(idx) {
                    if ($(this).data('id') == id) {
                        self.$carousel.carousel(idx);
                        
                        return false;
                    }
                });
                
            }
        };
        
        return Gallery;
    }
);