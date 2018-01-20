import * as $ from "jquery";
import * as filesize from "filesize";
import "./gallery.less";

interface Dimension {
    width: number;
    height: number;
}

interface Bounds {
    left: number;
    top: number;
    width: number;
    height: number;
}

class Carousel {
    
    private $element: JQuery;
    private sliding: any = null;
    private $active: JQuery;
    private $items: JQuery;
    private onSlide: Function|null = null;
    private TRANSITION_DURATION: number = 600;
    private keypressHandler: (eventObject: JQueryEventObject, ...eventData: any[]) => any;
    
    constructor(
        element: any, 
        options: any
    ) {
        this.$element    = $(element);
        this.onSlide     = options.slide;

        this.$element.carousel({
            interval: 0,
            wrap: false
        });
        
        let self = this;
        
        this.keypressHandler = (event: JQueryEventObject) => {
            if (/input|textarea/i.test(event.currentTarget.tagName)) {
                return;
            }
            switch (event.which) {
                case 37: self.prev(); break;
                case 39: self.next(); break;
                default: return;
            }

            event.preventDefault();
        };
    }
    
    public show() {
        $(document).on('keydown.bs.carousel', this.keypressHandler);
    }
    
    public hide() {
        $(document).off('keydown.bs.carousel', this.keypressHandler);
    }
    
    public destroy() {
        this.hide();
    }

    private getItemIndex(item: JQuery) {
        return this.$element.find('.item').index(item || this.$active);
    }
    
    private getItemForDirection(direction: string, active: JQuery) {
        var $items = this.$element.find('.item');
        var activeIndex = this.getItemIndex(active);
        var willWrap = (direction == 'prev' && activeIndex === 0) ||
                       (direction == 'next' && activeIndex == ($items.length - 1));
        if (willWrap) {
            return active;
        }
        var delta = direction == 'prev' ? -1 : 1;
        var itemIndex = (activeIndex + delta) % $items.length;
        return $items.eq(itemIndex);
    }

    public to(pos: number) {
        //var that        = this;
        var activeIndex = this.getItemIndex(this.$active = this.$element.find('.item.active'));
        
        var $items = this.$element.find('.item');

        if (pos > ($items.length - 1) || pos < 0) return;
        
        //if (this.sliding)       return this.$element.one('slid.bs.carousel', function () { that.to(pos) }) // yes, "slid"

        return this.slide(pos > activeIndex ? 'next' : 'prev', $items.eq(pos));
    }

    public next() {
        if (this.sliding) return;
        return this.slide('next');
    }
    
    public prev() {
        if (this.sliding) return;
        return this.slide('prev');
    }
    
    private slide(type: string, next: any = null) {
        var $active   = this.$element.find('.item.active');
        var $next     = next || this.getItemForDirection(type, $active);
        var direction: string = type == 'next' ? 'left' : 'right';
        var that      = this;

        if ($next.hasClass('active')) {
            return (this.sliding = false);
        }

        var relatedTarget = $next ? $next[0] : null;
        if (this.onSlide) {
            this.onSlide(relatedTarget, direction);
        }

        this.sliding = true;

        if ($.support.transition && this.$element.hasClass('slide')) {
            $next.addClass(type);
            $next[0].offsetWidth; // jshint ignore:line 
            $active.addClass(direction);
            $next.addClass(direction);
            $active
                .one('bsTransitionEnd', function () {
                    $next.removeClass([type, direction].join(' ')).addClass('active');
                    $active.removeClass(['active', direction].join(' '));
                    that.sliding = false;
                })
                .emulateTransitionEnd(this.TRANSITION_DURATION);
        } else {
            $active.removeClass('active');
            $next.addClass('active');
            this.sliding = false;
        }

        return this;
    }
}

export class Gallery {
    
    private MAX_INDICATORS: number = 80;
    private count: number = 0;
    private pages: number = 0;
    private pageStatus: any[] = [];
    private perPage: number = 10;
    private current: any;
    private url: string;
    
    private escHandler: (eventObject: JQueryEventObject, ...eventData: any[]) => any;
    private $e: JQuery;
    private $carousel: JQuery;
    private $inner: JQuery;
    private $indicators: JQuery;
    private $numbers: JQuery;
    private carousel: Carousel;
    private position: number;
    private indicatorRendered: boolean = false;
    
    constructor(items: any) {
        this.init(items);
    }
    
    public destroy() {
        this.carousel.destroy();
    }

    private init(options: any) {
        
        var self = this;
        
        this.current = options.current;
        this.url = options.url;
        
        this.escHandler = (event) => {
            if (event.keyCode == 27) { // esc
                self.hide();
            }
        };
        
        this.$e = $(require('./gallery.html'));
        
        var $carousel = this.$e.find('.carousel');
        this.$carousel = $carousel;
        
        this.$inner = $carousel.find('.carousel-inner');
        
        this.$e.appendTo(document.body);
        
        this.$indicators = this.$e.find('.carousel-indicators');
        this.$numbers = this.$e.find('.carousel-numbers');
        
        this.carousel = new Carousel(this.$carousel[0], {
            wrap: false,
            slide: function(relatedTarget: any) {
                var $item = $(relatedTarget);
                
                self.activateItem($item, true);
                self.fixArrows($item);
                
                var position = $item.data('position');
                
                self.position = position;
                self.refreshIndicator();
                
                self.loadSiblingPages(position);
                
                self.$indicators.find('li.active').removeClass('active');
                self.$indicators.find('li').eq(position).addClass('active');
            }
        });
        
        $carousel.find('.carousel-control.close').on('click', (event: JQueryEventObject) => {
            event.preventDefault();
            
            self.hide();
        });
        
        $carousel.on('click', '.item .details.carousel-control', (event: JQueryEventObject) => {
            if ($(event.currentTarget).attr('href') == window.location.pathname) {
                self.hide();
                event.preventDefault();
            }
        });
        
        $carousel.on('click', '.item .comments.carousel-control', (event: JQueryEventObject) => {
            let src = window.location.pathname.replace('#comments', '');
            let href = $(event.currentTarget).attr('href');
            if (href) {
                let dst = href.replace('#comments', '');
                if (src == dst) {
                    self.hide();
                    var offset = $("#comments").offset();
                    if (offset !== undefined) {
                        $('body').scrollTop(offset.top);
                    }
                    event.preventDefault();
                }
            }
        });
        
        $carousel.on('click', '.item img, .item .full.carousel-control', (event: JQueryEventObject) =>  {
            
            var $item = $(event.currentTarget).closest('.item');
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
        
        $carousel.on('click', '.carousel-indicators li', (event) => {
            event.preventDefault();
            
            var position = $(event.currentTarget).data('target');
            
            var page = self.positionPage(position);
            
            self.load(null, page, function() {
                self.rewindToPosition(position);
            });
        });
        
        $carousel.on('click', '.right.carousel-control', (event) => {
            event.preventDefault();
            
            self.carousel.next();
        });
        
        $carousel.on('click', '.left.carousel-control', (event) => {
            event.preventDefault();
            
            self.carousel.prev();
        });
        
        $(window).on('resize', () => {
            self.fixSize(self.$e.find('.item'));
        });
        self.fixSize(this.$e.find('.item'));
        
        this.load(self.current);
    }
    
    private renderIndicator() {
        if (this.count < this.MAX_INDICATORS) {
            if (!this.indicatorRendered) {
                for (var i=0; i<this.count; i++) {
                    $('<li></li>', {
                        'data-target': i,
                        appendTo: this.$indicators
                    });
                }
                
                this.indicatorRendered = true;
            }
        }
    }
    
    private loadSiblingPages(index: number) {
        var page = this.positionPage(index);
        var prevPage = page > 1 ? page - 1 : 1;
        var nextPage = page < this.pages ? page + 1 : this.pages;
        
        this.load(null, prevPage);
        this.load(null, nextPage);
    }
    
    private load(pictureId: number|null, page: number = 0, callback: Function|undefined = undefined) {
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
            
            let $activeItem: JQuery|undefined = undefined;
            
            var offset = self.perPage * (json.page - 1);
            for (let idx in json.items) {
                let item = json.items[idx];
                var position: number = offset + Number(idx);
                
                var active: boolean = self.current == item.id;
                
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
                    self.position = position;
                }
            }
            
            self.renderIndicator();
            self.refreshIndicator();
            
            if ($activeItem !== undefined) {
                $activeItem.addClass('active');
                self.activateItem($activeItem, true);
                self.fixArrows($activeItem);
                
                self.$indicators.find('li').eq($activeItem.data('position')).addClass('active');
            }
            
            self.loadSiblingPages(self.$inner.find('.item.active').data('position'));
            
            if (callback) { callback(); }
        });
    }
    
    private renderItem(item: any) {
        var $loading = $('<div class="loading-icon"><i class="fa fa-spinner fa-pulse"></i></div>');
        
        var $source = $(
            '<a class="download carousel-control" role="button">' +
                '<i class="fa fa-download"></i>' +
                '<div class="badge badge-info"></div>' +
            '</a>'
        ).attr('href', item.sourceUrl);
        
        $source.find('.badge').text(filesize(item.filesize));
        
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
        
        var $caption: JQuery = $(
            '<div class="carousel-caption">' +
                '<h3></h3>' +
                //'<p></p>' +
            '</div>'
        );
        
        $caption.find('h3').html(item.name);
        $caption.find('[data-toggle="tooltip"]').tooltip();
        
        var areas: JQuery[] = [];
        $.map(item.areas, function(area) {
            var $area: JQuery = $('<div class="area"></div>');
            $area.data('area', area.area);
            $area.tooltip({
                title: area.name,
                html: true,
                placement: function(tooptip: any, node: any) {
                    let winHeight = $(window).height();
                    let nodeOffset = $(node).offset();
                    let nodeHeight = $(node).height();
                    var winCenter = winHeight === undefined ? 0 : winHeight / 2;
                    var nodeCenter = nodeOffset === undefined || nodeHeight === undefined ? 0 : nodeOffset.top + nodeHeight / 2;
                    
                    return winCenter > nodeCenter ? 'bottom' : 'top';
                }
            });
            areas.push($area);
        });
        
        var $item = $('<div class="item loading"></div>')
            .data({
                id: item.id,
                full: item.full,
                crop: item.crop
            })
            .append(areas)
            .append($caption)
            .append($source)
            .append($comments)
            .append($details)
            .append($loading);
        
        if (item.crop) {
            $(
                '<a class="full carousel-control" role="button">' +
                    '<i class="fa fa-arrows-alt"></i>' +
                '</a>'
            ).appendTo($item);
            
        }
        
        return $item;
    }
    
    private activateItem($item: JQuery, siblings: boolean) {
        
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
            });
            
            $img.bind('load', function() {
                $img.closest('.item').removeClass('loading');
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
    }
    
    private fixArrows($item: JQuery) {
        var $left = this.$e.find('.carousel-control.left');
        var $right = this.$e.find('.carousel-control.right');
        
        var pos = $item.data('position');
        
        $left.toggle(pos > 0);
        $right.toggle(pos < this.count-1);
    }
    
    private bound(container: Dimension, content: Dimension): Dimension {
        
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
    }
    
    private boundCenter(container: Dimension, content: Dimension): Bounds {
        return {
            left: (container.width - content.width) / 2,
            top: (container.height - content.height) / 2,
            width: content.width,
            height: content.height
        };
    }
    
    private maxBounds(bounds: Dimension, maxBounds: Dimension): Dimension {
        if (bounds.height > maxBounds.height || bounds.width > maxBounds.width) {
            return maxBounds;
        }
        return bounds;
    }
    
    private areasToBounds($item: JQuery, offsetBounds: Bounds) {
        $item.find('.area').each(function() {
            var area = $(this).data('area');
            $(this).css({
                left: offsetBounds.left + area.left * offsetBounds.width,
                top: offsetBounds.top + area.top * offsetBounds.height,
                width: area.width * offsetBounds.width,
                height: area.height * offsetBounds.height 
            });
        });
    }
    
    private fixSize($items: JQuery) {
        var w = this.$inner.width() || 0;
        var h = this.$inner.height() || 0;
        
        var cSize: Dimension = {
            width: w,
            height: h
        };
        
        var self = this;
        
        $items.each(function() {
            var $item: JQuery = $(this);
            var $imgFull: JQuery = $item.find('img.full');
            var $imgCrop: JQuery = $item.find('img.crop');
            var full = $item.data('full');
            var crop: any = $item.data('crop');
            var cropMode: any = $item.data('cropMode');
            
            var bounds: Dimension;
            var offsetBounds: Bounds;
            
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
                    $imgCrop.css({
                        width: offsetBounds.width,
                        height: offsetBounds.height,
                        left: offsetBounds.left,
                        top: offsetBounds.top
                    });
                    var fullWidth = bounds.width / crop.crop.width;
                    var fullHeight = bounds.height / crop.crop.height;
                    var imgFullBounds = {
                        left: offsetBounds.left - crop.crop.left * fullWidth,
                        top: offsetBounds.top - crop.crop.top * fullHeight,
                        width: fullWidth,
                        height: fullHeight
                    };
                    $imgFull.css({
                        width: imgFullBounds.width,
                        height: imgFullBounds.height,
                        left: imgFullBounds.left,
                        top: imgFullBounds.top
                    });
                    
                    self.areasToBounds($item, imgFullBounds);
                } else {
                    bounds = self.maxBounds(self.bound(cSize, {
                        width: full.width,
                        height: full.height
                    }), {
                        width: full.width,
                        height: full.height
                    });
                    offsetBounds = self.boundCenter(cSize, bounds);
                    $imgFull.css({
                        width: offsetBounds.width,
                        height: offsetBounds.height,
                        left: offsetBounds.left,
                        top: offsetBounds.top
                    });
                    $imgCrop.css({
                        left: offsetBounds.left + crop.crop.left * bounds.width,
                        top: offsetBounds.top + crop.crop.top * bounds.height,
                        width: bounds.width * crop.crop.width,
                        height: bounds.height * crop.crop.height,
                    });
                    
                    self.areasToBounds($item, offsetBounds);
                }
                
            } else {
                if (!full) {
                    throw "Full is undefined";
                }
                bounds = self.maxBounds(self.bound(cSize, {
                    width: full.width,
                    height: full.height
                }), {
                    width: full.width,
                    height: full.height
                });
                offsetBounds = self.boundCenter(cSize, bounds);
                $imgFull.css({
                    width: offsetBounds.width,
                    height: offsetBounds.height,
                    left: offsetBounds.left,
                    top: offsetBounds.top
                });
                
                self.areasToBounds($item, offsetBounds);
            }
        });
    }
    
    private hide() {
        this.$e.hide();
        $(document.body).removeClass('gallery-shown');
        
        this.carousel.hide();
        
        $(document).off("keyup", this.escHandler);
    }
    
    private show() {
        $(document.body).addClass('gallery-shown');
        this.$e.show();
        this.fixSize(this.$e.find('.item'));
        
        $(document).on("keyup", this.escHandler);
        
        this.$e.find('a.carousel-control.right').focus();
        
        this.carousel.show();
    }
    
    private rewindToPosition(position: number) {
        var self = this;
        this.position = position;
        this.refreshIndicator();
        this.$e.find('.item').each(function(idx) {
            if ($(this).data('position') == position) {
                self.carousel.to(idx);
                return false;
            }
        });
    }
    
    private rewindToId(id: number) {
        var self = this;
        this.$carousel.find('.item').each(function(idx: number) {
            if ($(this).data().id == id) {
                self.$carousel.carousel(Number(idx));
                
                self.position = $(this).data('position');
                self.refreshIndicator();
                self.fixArrows($(this));
                
                return false;
            }
        });
    }
    
    private positionPage(index: number) {
        return Math.floor(index / this.perPage) + 1;
    }
    
    private refreshIndicator() {
        if (this.count >= this.MAX_INDICATORS) {
            this.$numbers.text(this.position + ' of ' + this.count);
        }
    }
}

