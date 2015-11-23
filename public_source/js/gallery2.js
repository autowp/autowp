define(
    'gallery2',
    ['jquery'],
    function($) {
        
        
        var Carousel = function (element, options) {
            this.$element    = $(element)
            this.options     = options
            this.sliding     = null
            this.$active     = null
            this.$items      = null
            this.onSlide     = options.slide;

            this.$element.on('keydown.bs.carousel', $.proxy(this.keydown, this))
        }
        
        Carousel.TRANSITION_DURATION = 600

        $.extend(Carousel.prototype, {
            keydown: function (e) {
                if (/input|textarea/i.test(e.target.tagName)) return
                switch (e.which) {
                  case 37: this.prev(); break
                  case 39: this.next(); break
                  default: return
                }

                e.preventDefault()
            },
            getItemIndex: function (item) {
                return this.$element.find('.item').index(item || this.$active)
            },
            getItemForDirection: function (direction, active) {
                var $items = this.$element.find('.item');
                var activeIndex = this.getItemIndex(active)
                var willWrap = (direction == 'prev' && activeIndex === 0)
                            || (direction == 'next' && activeIndex == ($items.length - 1))
                if (willWrap) return active
                var delta = direction == 'prev' ? -1 : 1
                var itemIndex = (activeIndex + delta) % $items.length
                return $items.eq(itemIndex)
            },
            to: function (pos) {
                var that        = this
                var activeIndex = this.getItemIndex(this.$active = this.$element.find('.item.active'));
                
                var $items = this.$element.find('.item');

                if (pos > ($items.length - 1) || pos < 0) return;
                
                //if (this.sliding)       return this.$element.one('slid.bs.carousel', function () { that.to(pos) }) // yes, "slid"

                return this.slide(pos > activeIndex ? 'next' : 'prev', $items.eq(pos))
            },
            next: function () {
                if (this.sliding) return
                return this.slide('next')
            },
            prev: function () {
                if (this.sliding) return
                return this.slide('prev')
            },
            slide: function (type, next) {
                var $active   = this.$element.find('.item.active')
                var $next     = next || this.getItemForDirection(type, $active)
                var direction = type == 'next' ? 'left' : 'right'
                var that      = this

                if ($next.hasClass('active')) return (this.sliding = false)

                var relatedTarget = $next[0]
                this.onSlide(relatedTarget, direction);

                this.sliding = true

                if ($.support.transition && this.$element.hasClass('slide')) {
                  $next.addClass(type)
                  $next[0].offsetWidth // force reflow
                  $active.addClass(direction)
                  $next.addClass(direction)
                  $active
                    .one('bsTransitionEnd', function () {
                      $next.removeClass([type, direction].join(' ')).addClass('active')
                      $active.removeClass(['active', direction].join(' '))
                      that.sliding = false
                    })
                    .emulateTransitionEnd(Carousel.TRANSITION_DURATION)
                } else {
                  $active.removeClass('active')
                  $next.addClass('active')
                  this.sliding = false
                }

                return this
            }
        });
        
        
        var markup = 
            '<div class="gallery" tabindex="0">' +
                '<div class="carousel slide">' +
                    '<ol class="carousel-indicators"></ol>' +
                    '<div class="carousel-inner"></div>' +
                    '<a class="left carousel-control" href="#" role="button">' +
                        '<span class="glyphicon glyphicon-chevron-left"></span>' +
                    '</a>' +
                    '<a class="right carousel-control" href="#" role="button">' +
                        '<span class="glyphicon glyphicon-chevron-right"></span>' +
                    '</a>' +
                    '<a class="close carousel-control" href="#" role="button">' +
                        '<span class="glyphicon glyphicon-remove"></span>' +
                    '</a>' +
                '</div>' +
            '</div>';
        
        
        var Gallery = function(items) {
            this.init(items);
        }
        
        Gallery.prototype = {
            init: function(options) {
                
                var self = this;
                
                this.count = 0;
                this.pages = 0;
                this.current = options.current;
                this.url = options.url;
                this.pageStatus = [];
                this.perPage = 10;
                
                
                this.escHandler = function(e) {
                    if (e.keyCode == 27) { // esc
                        self.hide();
                    }
                };
                
                this.$e = $(markup);
                
                var $carousel = this.$e.find('.carousel');
                this.$carousel = $carousel;
                
                this.$inner = $carousel.find('.carousel-inner');
                
                this.$e.appendTo(document.body);
                
                this.$indicators = this.$e.find('.carousel-indicators');
                
                this.carousel = new Carousel(this.$carousel[0], {
                    wrap: false,
                    slide: function(relatedTarget) {
                        var $item = $(relatedTarget);
                        
                        self.activateItem($item, true);
                        self.fixArrows($item);
                        
                        var position = $item.data('position');
                        
                        self.loadSiblingPages(position);
                        
                        self.$indicators.find('li.active').removeClass('active');
                        self.$indicators.find('li').eq(position).addClass('active');
                    }
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
                
                $carousel.on('click', '.carousel-indicators li', function(e) {
                    e.preventDefault();
                    
                    var position = $(this).data('target');
                    
                    var page = self.positionPage(position);
                    
                    self.load(null, page, function() {
                        self.rewindToPosition(position);
                    });
                });
                
                $carousel.on('click', '.right.carousel-control', function(e) {
                    e.preventDefault();
                    
                    self.carousel.next();
                });
                
                $carousel.on('click', '.left.carousel-control', function(e) {
                    e.preventDefault();
                    
                    self.carousel.prev();
                });
                
                $(window).on('resize', function() {
                    self.fixSize(self.$e.find('.item'));
                });
                self.fixSize(this.$e.find('.item'));
                
                this.load(self.current);
            },
            renderIndicator: function() {
                if (!this.indicatorRendered) {
                    for (var i=0; i<this.count; i++) {
                        $('<li></li>', {
                            'data-target': i,
                            appendTo: this.$indicators
                        });
                    }
                    
                    this.indicatorRendered = true;
                }
            },
            loadSiblingPages: function(index) {
                var page = this.positionPage(index);
                var prevPage = page > 1 ? page - 1 : 1;
                var nextPage = page < this.pages ? page + 1 : this.pages;
                
                this.load(null, prevPage);
                this.load(null, nextPage);
            },
            load: function(pictureId, page, callback) {
                var self = this;
                if (page) {
                    var loaded = false;
                    var status = self.pageStatus[page];
                    if (status == 'loading' || status == 'loaded') {
                        loaded = true;
                    }
                    if (loaded) {
                        if (callback) { callback(); }
                        return;
                    }
                    
                    self.pageStatus[page] = 'loading';
                }
                $.getJSON(this.url, {pictureId: pictureId, page: page}, function(json) {
                    self.count = json.count;
                    self.pages = json.pages;
                    
                    if (!self.pageStatus[self.pages]) {
                        self.pageStatus[self.pages] = null;
                    }
                    self.pageStatus[json.page] = 'loaded';
                    
                    var $activeItem = null;
                    
                    var offset = self.perPage * (json.page - 1);
                    $.each(json.items, function(idx, item) {
                        var position = offset + idx;
                        
                        var active = self.current == item.id;
                        
                        var $item = self.renderItem(item);
                        $item.data('position', position);
                        $item.attr('data-position', position);
                        
                        var $before = null;
                        self.$inner.find('.item').each(function() {
                            var iPos = $(this).data('position');
                            if (position < iPos) {
                                $before = $(this);
                                return false;
                            }
                        });
                        
                        if ($before) {
                            $item.insertBefore($before);
                        } else {
                            $item.appendTo(self.$inner);
                        }
                        
                        if (active) {
                            $activeItem = $item;
                        }
                    });
                    
                    self.renderIndicator();
                    
                    if ($activeItem) {
                        $activeItem.addClass('active');
                        self.activateItem($activeItem, true);
                        self.fixArrows($activeItem);
                        
                        self.$indicators.find('li').eq($activeItem.data('position')).addClass('active');
                    }
                    
                    self.loadSiblingPages(self.$inner.find('.item.active').data('position'));
                    
                    if (callback) { callback(); }
                });
            },
            renderItem: function(item) {
                var $loading = $('<div class="loading-icon"><span class="glyphicon glyphicon-repeat"></span></div>');
                
                var $source = $(
                    '<a class="download carousel-control" role="button">' +
                        '<span class="glyphicon glyphicon-download"></span>' +
                        '<div class="badge badge-info"></div>' +
                    '</a>'
                ).attr('href', item.sourceUrl);
                
                $source.find('.badge').text(item.filesize);
                
                var $details = $(
                    '<a class="details carousel-control" role="button">' +
                        '<span class="glyphicon glyphicon-picture"></span>' +
                    '</a>'
                ).attr('href', item.url);
                
                var $comments = $(
                    '<a class="comments carousel-control" role="button">' +
                        '<span class="glyphicon glyphicon-comment"></span>' +
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
                    .append($loading);
                
                if (item.crop) {
                    $(
                        '<a class="full carousel-control" role="button">' +
                            '<span class="glyphicon glyphicon-fullscreen"></span>' +
                        '</a>'
                    ).appendTo($item);
                    
                }
                
                return $item;
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
                        var $img = $('<img />', {
                            src: crop.src,
                            alt: '',
                            'class': 'crop'
                        });
                        $item.prepend($img);
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
                
                var pos = $item.data('position');
                
                $left.toggle(pos > 0);
                $right.toggle(pos < this.count-1);
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
                }
            },
            boundCenter: function(container, content) {
                return {
                    left: (container.width - content.width) / 2,
                    top: (container.height - content.height) / 2,
                    width: content.width,
                    height: content.height
                }
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
                    
                    if (crop) {
                        
                        if (cropMode) {
                            var bounds = self.maxBounds(self.bound(cSize, {
                                width: crop.width,
                                height: crop.height
                            }), {
                                width: crop.width,
                                height: crop.height
                            });
                            
                            var offsetBounds = self.boundCenter(cSize, bounds);
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
                            var bounds = self.maxBounds(self.bound(cSize, {
                                width: full.width,
                                height: full.height
                            }), {
                                width: full.width,
                                height: full.height
                            });
                            var offsetBounds = self.boundCenter(cSize, bounds);
                            $imgFull.css(offsetBounds);
                            $imgCrop.css({
                                left: offsetBounds.left + crop.crop.left * bounds.width,
                                top: offsetBounds.top + crop.crop.top * bounds.height,
                                width: bounds.width * crop.crop.width,
                                height: bounds.height * crop.crop.height,
                            });
                        }
                        
                    } else {
                        var bounds = self.maxBounds(self.bound(cSize, {
                            width: full.width,
                            height: full.height
                        }), {
                            width: full.width,
                            height: full.height
                        });
                        var offsetBounds = self.boundCenter(cSize, bounds);
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
            rewindToPosition: function(position) {
                var self = this;
                this.$e.find('.item').each(function(idx) {
                    if ($(this).data('position') == position) {
                        self.carousel.to(idx)
                        return false;
                    }
                });
            },
            rewindToId: function(id) {
                var self = this;
                this.$carousel.find('.item').each(function(idx) {
                    if ($(this).data('id') == id) {
                        self.$carousel.carousel(idx)
                        
                        return false;
                    }
                });
            },
            positionPage: function(index) {
                return Math.floor(index / this.perPage) + 1;
            }
        };
        
        return Gallery;
    }
);