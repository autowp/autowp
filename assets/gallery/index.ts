import * as $ from 'jquery';
import * as filesize from 'filesize';
import './gallery.scss';
import Util from 'bootstrap/js/src/util';

console.log(Util);

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

/**
 * ------------------------------------------------------------------------
 * Constants
 * ------------------------------------------------------------------------
 */

const NAME = 'carousel';
const EVENT_KEY = '.bs.carousel';
const ARROW_LEFT_KEYCODE = 37; // KeyboardEvent.which value for left arrow key
const ARROW_RIGHT_KEYCODE = 39; // KeyboardEvent.which value for right arrow key

const Default = {
    keyboard: true,
    slide: false
};

const DefaultType = {
    keyboard: 'boolean',
    slide: '(boolean|string)'
};

const Direction = {
    NEXT: 'next',
    PREV: 'prev',
    LEFT: 'left',
    RIGHT: 'right'
};

const Event = {
    SLIDE: `slide${EVENT_KEY}`,
    SLID: `slid${EVENT_KEY}`,
    KEYDOWN: `keydown${EVENT_KEY}`
};

const ClassName = {
    CAROUSEL: 'carousel',
    ACTIVE: 'active',
    SLIDE: 'slide',
    RIGHT: 'carousel-item-right',
    LEFT: 'carousel-item-left',
    NEXT: 'carousel-item-next',
    PREV: 'carousel-item-prev',
    ITEM: 'carousel-item'
};

const Selector = {
    ACTIVE: '.active',
    ACTIVE_ITEM: '.active.carousel-item',
    ITEM: '.carousel-item',
    NEXT_PREV: '.carousel-item-next, .carousel-item-prev'
};

const MILLISECONDS_MULTIPLIER = 1000

function getTransitionDurationFromElement(element: any) {
    if (!element) {
        return 0;
    }

    // Get transition-duration of the element
    let transitionDuration = $(element).css('transition-duration');
    const floatTransitionDuration = parseFloat(transitionDuration);

    // Return 0 if element or transition duration is not found
    if (!floatTransitionDuration) {
        return 0;
    }

    // If multiple durations are defined, take the first
    transitionDuration = transitionDuration.split(',')[0];

    return parseFloat(transitionDuration) * MILLISECONDS_MULTIPLIER;
}

/**
 * ------------------------------------------------------------------------
 * Class Definition
 * ------------------------------------------------------------------------
 */

class Carousel {
    private _items: any = null;
    private _activeElement: any = null;

    private _isSliding: boolean = false;

    private _config: any;

    private _element: any;

    private _onSlide: Function;

    constructor(element: any, config: any, onSlide: any) {
        this._config = this._getConfig(config);
        this._element = $(element)[0];
        this._onSlide = onSlide;

        this._addEventListeners();
    }

    // Getters

    static get Default() {
        return Default;
    }

    // Public

    next() {
        if (!this._isSliding) {
            this._slide(Direction.NEXT);
        }
    }

    nextWhenVisible() {
        // Don't call next when the page isn't visible
        // or the carousel or its parent isn't visible
        if (
            !document.hidden &&
            ($(this._element).is(':visible') &&
                $(this._element).css('visibility') !== 'hidden')
        ) {
            this.next();
        }
    }

    prev() {
        if (!this._isSliding) {
            this._slide(Direction.PREV);
        }
    }

    to(index: any) {
        this._activeElement = $(this._element).find(Selector.ACTIVE_ITEM)[0];

        const activeIndex = this._getItemIndex(this._activeElement);

        if (index > this._items.length - 1 || index < 0) {
            return;
        }

        if (this._isSliding) {
            $(this._element).one(Event.SLID, () => this.to(index));
            return;
        }

        if (activeIndex === index) {
            return;
        }

        const direction = index > activeIndex ? Direction.NEXT : Direction.PREV;

        this._slide(direction, this._items[index]);
    }

    dispose() {
        $(this._element).off(EVENT_KEY);

        this._items = null;
        this._config = null;
        this._element = null;
        this._activeElement = null;
    }

    // Private

    _getConfig(config: any) {
        config = {
            ...Default,
            ...config
        };
        Util.typeCheckConfig(NAME, config, DefaultType);
        return config;
    }

    _addEventListeners() {
        if (this._config.keyboard) {
            $(this._element).on(Event.KEYDOWN, event => this._keydown(event));
        }
    }

    _keydown(event: any) {
        if (/input|textarea/i.test(event.target.tagName)) {
            return;
        }

        switch (event.which) {
            case ARROW_LEFT_KEYCODE:
                event.preventDefault();
                this.prev();
                break;
            case ARROW_RIGHT_KEYCODE:
                event.preventDefault();
                this.next();
                break;
            default:
        }
    }

    _getItemIndex(element: any) {
        this._items = $.makeArray(
            $(element)
                .parent()
                .find(Selector.ITEM)
        );
        return this._items.indexOf(element);
    }

    _getItemByDirection(direction: string, activeElement: any) {
        const isNextDirection = direction === Direction.NEXT;
        const isPrevDirection = direction === Direction.PREV;
        const activeIndex = this._getItemIndex(activeElement);
        const lastItemIndex = this._items.length - 1;
        const isGoingToWrap =
            (isPrevDirection && activeIndex === 0) ||
            (isNextDirection && activeIndex === lastItemIndex);

        if (isGoingToWrap) {
            return activeElement;
        }

        const delta = direction === Direction.PREV ? -1 : 1;
        const itemIndex = (activeIndex + delta) % this._items.length;

        return itemIndex === -1
            ? this._items[this._items.length - 1]
            : this._items[itemIndex];
    }

    _triggerSlideEvent(relatedTarget: any, eventDirectionName: string) {
        const targetIndex = this._getItemIndex(relatedTarget);
        const fromIndex = this._getItemIndex(
            $(this._element).find(Selector.ACTIVE_ITEM)[0]
        );
        const slideEvent = $.Event(Event.SLIDE, {
            relatedTarget,
            direction: eventDirectionName,
            from: fromIndex,
            to: targetIndex
        });

        $(this._element).trigger(slideEvent);

        return slideEvent;
    }

    _slide(direction: string, element?: any) {
        const activeElement = $(this._element).find(Selector.ACTIVE_ITEM)[0];
        const activeElementIndex = this._getItemIndex(activeElement);
        const nextElement =
            element ||
            (activeElement &&
                this._getItemByDirection(direction, activeElement));
        const nextElementIndex = this._getItemIndex(nextElement);

        let directionalClassName: string;
        let orderClassName: string;
        let eventDirectionName: string;

        if (direction === Direction.NEXT) {
            directionalClassName = ClassName.LEFT;
            orderClassName = ClassName.NEXT;
            eventDirectionName = Direction.LEFT;
        } else {
            directionalClassName = ClassName.RIGHT;
            orderClassName = ClassName.PREV;
            eventDirectionName = Direction.RIGHT;
        }

        if (nextElement && $(nextElement).hasClass(ClassName.ACTIVE)) {
            this._isSliding = false;
            return;
        }

        const slideEvent = this._triggerSlideEvent(
            nextElement,
            eventDirectionName
        );
        if (slideEvent.isDefaultPrevented()) {
            return;
        }

        if (!activeElement || !nextElement) {
            // Some weirdness is happening, so we bail
            return;
        }

        if (this._onSlide) {
            this._onSlide(nextElement, direction);
        }

        this._isSliding = true;

        const slidEvent = $.Event(Event.SLID, {
            relatedTarget: nextElement,
            direction: eventDirectionName,
            from: activeElementIndex,
            to: nextElementIndex
        });

        if (
            Util.supportsTransitionEnd() &&
            $(this._element).hasClass(ClassName.SLIDE)
        ) {
            $(nextElement).addClass(orderClassName);

            Util.reflow(nextElement);

            $(activeElement).addClass(directionalClassName);
            $(nextElement).addClass(directionalClassName);

            const transitionDuration = getTransitionDurationFromElement(
                activeElement
            );

            $(activeElement)
                .one(Util.TRANSITION_END, () => {
                    $(nextElement)
                        .removeClass(
                            `${directionalClassName} ${orderClassName}`
                        )
                        .addClass(ClassName.ACTIVE);

                    $(activeElement).removeClass(
                        `${
                            ClassName.ACTIVE
                        } ${orderClassName} ${directionalClassName}`
                    );

                    this._isSliding = false;

                    setTimeout(() => $(this._element).trigger(slidEvent), 0);
                })
                .emulateTransitionEnd(transitionDuration);
        } else {
            $(activeElement).removeClass(ClassName.ACTIVE);
            $(nextElement).addClass(ClassName.ACTIVE);

            this._isSliding = false;
            $(this._element).trigger(slidEvent);
        }
    }
}

export class Gallery {
    private MAX_INDICATORS: number = 30;
    private count: number = 0;
    private pages: number = 0;
    private pageStatus: any[] = [];
    private perPage: number = 10;
    private current: any;
    private url: string;

    private escHandler: (
        eventObject: JQueryEventObject,
        ...eventData: any[]
    ) => any;
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
        this.carousel.dispose();
    }

    private init(options: any) {
        var self = this;

        this.current = options.current;
        this.url = options.url;

        this.escHandler = event => {
            if (event.keyCode == 27) {
                // esc
                //self.hide();
            }
        };

        this.$e = $(require('./gallery.html'));

        this.$carousel = this.$e.find('.carousel');

        this.$inner = this.$carousel.find('.carousel-inner');

        this.$e.appendTo(document.body);

        this.$indicators = this.$e.find('.carousel-indicators');
        this.$numbers = this.$e.find('.carousel-numbers');

        this.carousel = new Carousel(this.$carousel[0], {}, (relatedTarget: any) => {
            var $item = $(relatedTarget);

            this.activateItem($item, true);
            this.fixArrows($item);

            var position = $item.data('position');

            this.position = position;
            this.refreshIndicator();

            this.loadSiblingPages(position);

            this.$indicators.find('li.active').removeClass('active');
            this.$indicators
                .find('li')
                .eq(position)
                .addClass('active');
        }
        );

        this.$carousel
            .find('.carousel-control-close')
            .on('click', (event: JQueryEventObject) => {
                event.preventDefault();

                self.hide();
            });

        this.$carousel.on(
            'click',
            '.item .details.carousel-control',
            (event: JQueryEventObject) => {
                if (
                    $(event.currentTarget).attr('href') ==
                    window.location.pathname
                ) {
                    self.hide();
                    event.preventDefault();
                }
            }
        );

        this.$carousel.on(
            'click',
            '.item .comments.carousel-control',
            (event: JQueryEventObject) => {
                let src = window.location.pathname.replace('#comments', '');
                let href = $(event.currentTarget).attr('href');
                if (href) {
                    let dst = href.replace('#comments', '');
                    if (src == dst) {
                        self.hide();
                        var offset = $('#comments').offset();
                        if (offset !== undefined) {
                            $('body').scrollTop(offset.top);
                        }
                        event.preventDefault();
                    }
                }
            }
        );

        this.$carousel.on(
            'click',
            '.item img, .item .carousel-control-full',
            (event: JQueryEventObject) => {
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
            }
        );

        this.$carousel.on('click', '.carousel-indicators li', event => {
            event.preventDefault();

            var position = $(event.currentTarget).data('target');

            var page = self.positionPage(position);

            self.load(null, page, function() {
                self.rewindToPosition(position);
            });
        });

        this.$carousel.on('click', '.carousel-control-next', event => {
            event.preventDefault();

            self.carousel.next();
        });

        this.$carousel.on('click', '.carousel-control-prev', event => {
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
                for (var i = 0; i < this.count; i++) {
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

    private load(
        pictureId: number | null,
        page: number = 0,
        callback: Function | undefined = undefined
    ) {
        var self = this;
        if (page) {
            var loaded = false;
            var status = self.pageStatus[page];
            if (status == 'loading' || status == 'loaded') {
                loaded = true;
            }
            if (loaded) {
                if (callback) {
                    callback();
                }
                return;
            }

            self.pageStatus[page] = 'loading';
        }
        $.getJSON(this.url, { pictureId: pictureId, page: page }, function(
            json
        ) {
            self.count = json.count;
            self.pages = json.pages;

            if (!self.pageStatus[self.pages]) {
                self.pageStatus[self.pages] = null;
            }
            self.pageStatus[json.page] = 'loaded';

            let $activeItem: JQuery | undefined = undefined;

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

                self.$indicators
                    .find('li')
                    .eq($activeItem.data('position'))
                    .addClass('active');
            }

            self.loadSiblingPages(
                self.$inner.find('.item.active').data('position')
            );

            if (callback) {
                callback();
            }
        });
    }

    private renderItem(item: any) {
        var $loading = $(
            '<div class="loading-icon"><i class="fa fa-spinner fa-pulse"></i></div>'
        );

        var $source = $(
            '<a class="download carousel-control" role="button">' +
                '<i class="fa fa-download"></i>' +
                '<div class="badge badge-pill badge-info"></div>' +
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
            var $badge = $('<div class="badge badge-pill badge-info"></div>');
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
                    var nodeCenter =
                        nodeOffset === undefined || nodeHeight === undefined
                            ? 0
                            : nodeOffset.top + nodeHeight / 2;

                    return winCenter > nodeCenter ? 'bottom' : 'top';
                }
            });
            areas.push($area);
        });

        var $item = $('<div class="carousel-item item loading"></div>')
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
            $('<span class="carousel-control-full"><i class="fa fa-arrows-alt"></i></span>').appendTo($item);
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
                    class: 'crop'
                });
                $item.prepend($imgCrop);
            }

            var $img = $('<img />', {
                src: full.src,
                alt: '',
                class: 'full'
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
        var $left = this.$e.find('.carousel-control-prev');
        var $right = this.$e.find('.carousel-control-next');

        var pos = $item.data('position');

        $left.toggle(pos > 0);
        $right.toggle(pos < this.count - 1);
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
        if (
            bounds.height > maxBounds.height ||
            bounds.width > maxBounds.width
        ) {
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
                    bounds = self.maxBounds(
                        self.bound(cSize, {
                            width: crop.width,
                            height: crop.height
                        }),
                        {
                            width: crop.width,
                            height: crop.height
                        }
                    );

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
                    bounds = self.maxBounds(
                        self.bound(cSize, {
                            width: full.width,
                            height: full.height
                        }),
                        {
                            width: full.width,
                            height: full.height
                        }
                    );
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
                        height: bounds.height * crop.crop.height
                    });

                    self.areasToBounds($item, offsetBounds);
                }
            } else {
                if (!full) {
                    throw 'Full is undefined';
                }
                bounds = self.maxBounds(
                    self.bound(cSize, {
                        width: full.width,
                        height: full.height
                    }),
                    {
                        width: full.width,
                        height: full.height
                    }
                );
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

        // this.carousel.hide();

        $(document).off('keyup', this.escHandler);
    }

    private show() {
        $(document.body).addClass('gallery-shown');
        this.$e.show();
        this.fixSize(this.$e.find('.item'));

        $(document).on('keyup', this.escHandler);

        this.$e.find('a.carousel-control-next').focus();

        //this.carousel.show();
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
            this.$numbers.text((this.position+1) + ' of ' + this.count);
        }
    }
}
