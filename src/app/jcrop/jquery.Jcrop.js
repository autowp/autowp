/**
 * jquery.Jcrop.js v0.9.12
 * jQuery Image Cropping Plugin - released under MIT License
 * Author: Kelly Hallman <khallman@gmail.com>
 * http://github.com/tapmodo/Jcrop
 * Copyright (c) 2008-2013 Tapmodo Interactive LLC {{{
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * }}}
 */

import $ from 'jquery';

import {hasTouchSupport} from './touch';

// Global Defaults {{{
const defaults = {
  // Styling Options
  addClass: null,
  aspectRatio: 0,
  bgOpacity: 0.6,
  borderOpacity: 0.4,
  boundary: 2,

  boxHeight: 0,
  boxWidth: 0,
  createBorders: ['n', 's', 'e', 'w'],
  createDragbars: ['n', 's', 'e', 'w'],
  createHandles: ['n', 's', 'e', 'w', 'nw', 'ne', 'se', 'sw'],

  handleOpacity: 0.5,
  handleSize: null,
  maxSize: [0, 0],

  minSelect: [0, 0],
  minSize: [0, 0],
  // Callbacks / Event Handlers
  onSelect: function () {},

  touchSupport: null,
};

function Jcrop(obj, opt) {
  let $img;
  let xscale;
  let yscale;

  let options = $.extend({}, defaults);
  const _ua = navigator.userAgent.toLowerCase(),
    is_msie = /msie/.test(_ua);

  let $hdl_holder = $('<div />').width('100%').height('100%').css('zIndex', 320),
    $img_holder = $('<div />').width('100%').height('100%').css({
      overflow: 'hidden',
      position: 'absolute',
      zIndex: 310,
    });

  // Initialization {{{
  // Sanitize some options {{{
  if (typeof obj !== 'object') {
    obj = $(obj)[0];
  }
  if (typeof opt !== 'object') {
    opt = {};
  }
  // }}}
  setOptions(opt);
  // Initialize some jQuery objects {{{
  // The values are SET on the image(s) for the interface
  // If the original image has any of these set, they will be reset
  // However, if you destroy() the Jcrop instance the original image's
  // character in the DOM will be as you left it.
  const img_css = {
    border: 'none',
    left: 0,
    margin: 0,
    padding: 0,
    position: 'absolute',
    top: 0,
    visibility: 'visible',
  };

  let $origimg = $(obj);

  if (obj.tagName !== 'IMG') {
    throw new Error('Only img is supported');
  }
  // Fix size of crop image.
  // Necessary when crop image is within a hidden element when page is loaded.
  if ($origimg[0].width !== 0 && $origimg[0].height !== 0) {
    // Obtain dimensions from contained img element.
    $origimg.width($origimg[0].width);
    $origimg.height($origimg[0].height);
  } else {
    // Obtain dimensions from temporary image in case the original is not loaded yet (e.g. IE 7.0).
    const tempImage = new Image();
    tempImage.src = $origimg[0].src;
    $origimg.width(tempImage.width);
    $origimg.height(tempImage.height);
  }

  $img = $origimg.clone().removeAttr('id').css(img_css).show();

  $img.width($origimg.width());
  $img.height($origimg.height());
  $origimg.after($img).hide();

  presize($img, options.boxWidth, options.boxHeight);

  let boundx = $img.width(),
    boundy = $img.height(),
    $div = $('<div />')
      .width(boundx)
      .height(boundy)
      .addClass(cssClass('holder'))
      .css({
        backgroundColor: 'black',
        position: 'relative',
      })
      .insertAfter($origimg)
      .append($img);

  if (options.addClass) {
    $div.addClass(options.addClass);
  }

  const bound = options.boundary;
  const $trk = newTracker()
    .width(boundx + bound * 2)
    .height(boundy + bound * 2)
    .css({
      left: px(-bound),
      position: 'absolute',
      top: px(-bound),
      zIndex: 290,
    })
    .on('mousedown', newSelection);

  let $img2 = $('<div />'),
    $sel = $('<div />')
      .css({
        position: 'absolute',
        zIndex: 600,
      })
      .insertBefore($img)
      .append($img_holder, $hdl_holder);

  function detectSupport() {
    if (options.touchSupport === true || options.touchSupport === false) return options.touchSupport;
    else return hasTouchSupport();
  }

  // Touch Module {{{
  const Touch = (function () {
    return {
      cfilter: function (e) {
        e.pageX = e.originalEvent.changedTouches[0].pageX;
        e.pageY = e.originalEvent.changedTouches[0].pageY;
        return e;
      },
      createDragger: function (ord) {
        return function (e) {
          if (options.disabled) {
            return false;
          }
          docOffset = getPos($img);
          btndown = true;
          startDragMode(ord, mouseAbs(Touch.cfilter(e)), true);
          e.stopPropagation();
          e.preventDefault();
          return false;
        };
      },
      isSupported: hasTouchSupport,
      newSelection: function (e) {
        return newSelection(Touch.cfilter(e));
      },
      support: detectSupport(),
    };
  })();

  // Selection Module {{{
  const Selection = (function () {
    let awake,
      borders = {},
      dragbar = {},
      handle = {},
      hdep = 370;

    // Private Methods
    function insertBorder(type) {
      const jq = $('<div />')
        .css({
          opacity: options.borderOpacity,
          position: 'absolute',
        })
        .addClass(cssClass(type));
      $img_holder.append(jq);
      return jq;
    }

    function dragDiv(ord, zi) {
      const jq = $('<div />')
        .on('mousedown', createDragger(ord))
        .css({
          cursor: ord + '-resize',
          position: 'absolute',
          zIndex: zi,
        })
        .addClass('ord-' + ord);

      if (Touch.support) {
        jq.on('touchstart.jcrop', Touch.createDragger(ord));
      }

      $hdl_holder.append(jq);
      return jq;
    }

    function insertHandle(ord) {
      const div = dragDiv(ord, hdep++)
          .css({
            opacity: options.handleOpacity,
          })
          .addClass(cssClass('handle')),
        hs = options.handleSize;

      if (hs) {
        div.width(hs).height(hs);
      }

      return div;
    }

    function insertDragbar(ord) {
      return dragDiv(ord, hdep++).addClass('jcrop-dragbar');
    }

    function createDragbars(li) {
      for (let i = 0; i < li.length; i++) {
        dragbar[li[i]] = insertDragbar(li[i]);
      }
    }

    function createBorders(li) {
      let cl;
      for (let i = 0; i < li.length; i++) {
        switch (li[i]) {
          case 'e':
            cl = 'vline right';
            break;
          case 'n':
            cl = 'hline';
            break;
          case 's':
            cl = 'hline bottom';
            break;
          case 'w':
            cl = 'vline';
            break;
        }
        borders[li[i]] = insertBorder(cl);
      }
    }

    function createHandles(li) {
      for (let i = 0; i < li.length; i++) {
        handle[li[i]] = insertHandle(li[i]);
      }
    }

    function moveto(x, y) {
      $img2.css({
        left: px(-x),
        top: px(-y),
      });
      $sel.css({
        left: px(x),
        top: px(y),
      });
    }

    function resize(w, h) {
      $sel.width(Math.round(w)).height(Math.round(h));
    }

    function refresh() {
      const c = Coords.getFixed();

      Coords.setPressed([c.x, c.y]);
      Coords.setCurrent([c.x2, c.y2]);

      updateVisible();
    }

    // Internal Methods
    function updateVisible(select) {
      if (awake) {
        return update(select);
      }
    }

    function update(select) {
      const c = Coords.getFixed();

      resize(c.w, c.h);
      moveto(c.x, c.y);

      awake || show();

      if (select) {
        options.onSelect.call(api, unscale(c));
      }
    }

    function setBgOpacity(opacity, force) {
      if (!awake && !force) return;
      $img.css('opacity', opacity);
    }

    function show() {
      $sel.show();

      setBgOpacity(bgopacity, true);

      awake = true;
    }

    function release() {
      disableHandles();
      $sel.hide();

      setBgOpacity(1);

      awake = false;
    }

    function enableHandles() {
      $hdl_holder.show();
      return true;
    }

    function disableHandles() {
      $hdl_holder.hide();
    }

    function animMode(v) {
      if (v) {
        disableHandles();
      } else {
        enableHandles();
      }
    }

    function done() {
      animMode(false);
      refresh();
    }

    // Insert draggable elements {{{
    // Insert border divs for outline

    if (Array.isArray(options.createDragbars)) createDragbars(options.createDragbars);

    if (Array.isArray(options.createHandles)) createHandles(options.createHandles);

    if (Array.isArray(options.createBorders)) createBorders(options.createBorders);

    // This is a hack for iOS5 to support drag/move touch functionality
    $(document).on('touchstart.jcrop-ios', function (e) {
      if ($(e.currentTarget).hasClass('jcrop-tracker')) e.stopPropagation();
    });

    const $track = newTracker().on('mousedown', createDragger('move')).css({
      cursor: 'move',
      position: 'absolute',
      zIndex: 360,
    });

    if (Touch.support) {
      $track.on('touchstart.jcrop', Touch.createDragger('move'));
    }

    $img_holder.append($track);
    disableHandles();

    return {
      disableHandles: disableHandles,
      done: done,
      enableHandles: enableHandles,
      enableOnly: function () {},
      isAwake: function () {
        return awake;
      },
      refresh: refresh,
      release: release,
      setBgOpacity: setBgOpacity,
      setCursor: function (cursor) {
        $track.css('cursor', cursor);
      },
      update: update,
    };
  })();

  let api = {
    cancel: cancelCrop,
    destroy: destroy,

    disable: disableCrop,
    enable: enableCrop,
    focus: null,
    release: Selection.release,
    setOptions: setOptionsNew,

    setSelect: setSelect,

    ui: {
      holder: $div,
      selection: $sel,
    },
  };

  // Tracker Module {{{
  const Tracker = (function () {
    let onDone = function () {},
      onMove = function () {};

    function toFront(touch) {
      $trk.css({
        zIndex: 450,
      });

      if (touch) $(document).on('touchmove.jcrop', trackTouchMove).on('touchend.jcrop', trackTouchEnd);
      else $(document).on('mousemove.jcrop', trackMove).on('mouseup.jcrop', trackUp);
    }

    function toBack() {
      $trk.css({
        zIndex: 290,
      });
      $(document).off('.jcrop');
    }

    function trackMove(e) {
      onMove(mouseAbs(e));
      return false;
    }

    function trackUp(e) {
      e.preventDefault();
      e.stopPropagation();

      if (btndown) {
        btndown = false;

        onDone(mouseAbs(e));

        if (Selection.isAwake()) {
          options.onSelect.call(api, unscale(Coords.getFixed()));
        }

        toBack();
        onMove = function () {};
        onDone = function () {};
      }

      return false;
    }

    function activateHandlers(move, done, touch) {
      btndown = true;
      onMove = move;
      onDone = done;
      toFront(touch);
      return false;
    }

    function trackTouchMove(e) {
      onMove(mouseAbs(Touch.cfilter(e)));
      return false;
    }

    function trackTouchEnd(e) {
      return trackUp(Touch.cfilter(e));
    }

    function setCursor(t) {
      $trk.css('cursor', t);
    }

    $img.before($trk);
    return {
      activateHandlers: activateHandlers,
      setCursor: setCursor,
    };
  })();

  // Coords Module {{{
  const Coords = (function () {
    let x1 = 0,
      x2 = 0,
      y1 = 0,
      y2 = 0;

    function setPressed(pos) {
      pos = rebound(pos);
      x2 = x1 = pos[0];
      y2 = y1 = pos[1];
    }

    function setCurrent(pos) {
      pos = rebound(pos);
      x2 = pos[0];
      y2 = pos[1];
    }

    function moveOffset(offset) {
      let ox = offset[0],
        oy = offset[1];

      if (0 > x1 + ox) {
        ox -= ox + x1;
      }
      if (0 > y1 + oy) {
        oy -= oy + y1;
      }

      if (boundy < y2 + oy) {
        oy += boundy - (y2 + oy);
      }
      if (boundx < x2 + ox) {
        ox += boundx - (x2 + ox);
      }

      x1 += ox;
      x2 += ox;
      y1 += oy;
      y2 += oy;
    }

    function getCorner(ord) {
      const c = getFixed();
      switch (ord) {
        case 'ne':
          return [c.x2, c.y];
        case 'nw':
          return [c.x, c.y];
        case 'se':
          return [c.x2, c.y2];
        case 'sw':
          return [c.x, c.y2];
      }
    }

    function getFixed() {
      if (!options.aspectRatio) {
        return getRect();
      }
      // This function could use some optimization I think...
      const aspect = options.aspectRatio,
        min_x = options.minSize[0] / xscale,
        rh = y2 - y1,
        rha = Math.abs(rh),
        rw = x2 - x1,
        rwa = Math.abs(rw),
        real_ratio = rwa / rha;
      let h,
        max_x = options.maxSize[0] / xscale,
        w,
        xx,
        yy;

      if (max_x === 0) {
        max_x = boundx * 10;
      }
      if (real_ratio < aspect) {
        yy = y2;
        w = rha * aspect;
        xx = rw < 0 ? x1 - w : w + x1;

        if (xx < 0) {
          xx = 0;
          h = Math.abs((xx - x1) / aspect);
          yy = rh < 0 ? y1 - h : h + y1;
        } else if (xx > boundx) {
          xx = boundx;
          h = Math.abs((xx - x1) / aspect);
          yy = rh < 0 ? y1 - h : h + y1;
        }
      } else {
        xx = x2;
        h = rwa / aspect;
        yy = rh < 0 ? y1 - h : y1 + h;
        if (yy < 0) {
          yy = 0;
          w = Math.abs((yy - y1) * aspect);
          xx = rw < 0 ? x1 - w : w + x1;
        } else if (yy > boundy) {
          yy = boundy;
          w = Math.abs(yy - y1) * aspect;
          xx = rw < 0 ? x1 - w : w + x1;
        }
      }

      // Magic %-)
      if (xx > x1) {
        // right side
        if (xx - x1 < min_x) {
          xx = x1 + min_x;
        } else if (xx - x1 > max_x) {
          xx = x1 + max_x;
        }
        if (yy > y1) {
          yy = y1 + (xx - x1) / aspect;
        } else {
          yy = y1 - (xx - x1) / aspect;
        }
      } else if (xx < x1) {
        // left side
        if (x1 - xx < min_x) {
          xx = x1 - min_x;
        } else if (x1 - xx > max_x) {
          xx = x1 - max_x;
        }
        if (yy > y1) {
          yy = y1 + (x1 - xx) / aspect;
        } else {
          yy = y1 - (x1 - xx) / aspect;
        }
      }

      if (xx < 0) {
        x1 -= xx;
        xx = 0;
      } else if (xx > boundx) {
        x1 -= xx - boundx;
        xx = boundx;
      }

      if (yy < 0) {
        y1 -= yy;
        yy = 0;
      } else if (yy > boundy) {
        y1 -= yy - boundy;
        yy = boundy;
      }

      return makeObj(flipCoords(x1, y1, xx, yy));
    }

    function rebound(p) {
      if (p[0] < 0) p[0] = 0;
      if (p[1] < 0) p[1] = 0;

      if (p[0] > boundx) p[0] = boundx;
      if (p[1] > boundy) p[1] = boundy;

      return [Math.round(p[0]), Math.round(p[1])];
    }

    function flipCoords(x1, y1, x2, y2) {
      let xa = x1,
        xb = x2,
        ya = y1,
        yb = y2;
      if (x2 < x1) {
        xa = x2;
        xb = x1;
      }
      if (y2 < y1) {
        ya = y2;
        yb = y1;
      }
      return [xa, ya, xb, yb];
    }

    function getRect() {
      let delta,
        xsize = x2 - x1,
        ysize = y2 - y1;

      if (xlimit && Math.abs(xsize) > xlimit) {
        x2 = xsize > 0 ? x1 + xlimit : x1 - xlimit;
      }
      if (ylimit && Math.abs(ysize) > ylimit) {
        y2 = ysize > 0 ? y1 + ylimit : y1 - ylimit;
      }

      if (ymin / yscale && Math.abs(ysize) < ymin / yscale) {
        y2 = ysize > 0 ? y1 + ymin / yscale : y1 - ymin / yscale;
      }
      if (xmin / xscale && Math.abs(xsize) < xmin / xscale) {
        x2 = xsize > 0 ? x1 + xmin / xscale : x1 - xmin / xscale;
      }

      if (x1 < 0) {
        x2 -= x1;
        x1 -= x1;
      }
      if (y1 < 0) {
        y2 -= y1;
        y1 -= y1;
      }
      if (x2 < 0) {
        x1 -= x2;
        x2 -= x2;
      }
      if (y2 < 0) {
        y1 -= y2;
        y2 -= y2;
      }
      if (x2 > boundx) {
        delta = x2 - boundx;
        x1 -= delta;
        x2 -= delta;
      }
      if (y2 > boundy) {
        delta = y2 - boundy;
        y1 -= delta;
        y2 -= delta;
      }
      if (x1 > boundx) {
        delta = x1 - boundy;
        y2 -= delta;
        y1 -= delta;
      }
      if (y1 > boundy) {
        delta = y1 - boundy;
        y2 -= delta;
        y1 -= delta;
      }

      return makeObj(flipCoords(x1, y1, x2, y2));
    }

    function makeObj(a) {
      return {
        h: a[3] - a[1],
        w: a[2] - a[0],
        x: a[0],
        x2: a[2],
        y: a[1],
        y2: a[3],
      };
    }

    return {
      getCorner: getCorner,
      getFixed: getFixed,
      moveOffset: moveOffset,
      setCurrent: setCurrent,
      setPressed: setPressed,
    };
  })();

  let btndown;

  let docOffset;

  // Internal Methods {{{
  function px(n) {
    return Math.round(n) + 'px';
  }
  function cssClass(cl) {
    return 'jcrop-' + cl;
  }
  function getPos(obj) {
    const pos = $(obj).offset();
    return [pos.left, pos.top];
  }

  function mouseAbs(e) {
    return [e.pageX - docOffset[0], e.pageY - docOffset[1]];
  }

  function setOptions(opt) {
    if (typeof opt !== 'object') opt = {};
    options = $.extend(options, opt);

    if (typeof options.onSelect !== 'function') {
      options.onSelect = function () {};
    }
  }

  function startDragMode(mode, pos, touch) {
    docOffset = getPos($img);
    Tracker.setCursor(mode === 'move' ? mode : mode + '-resize');

    if (mode === 'move') {
      return Tracker.activateHandlers(createMover(pos), doneSelect, touch);
    }

    const fc = Coords.getFixed();
    const opp = oppLockCorner(mode);
    const opc = Coords.getCorner(oppLockCorner(opp));

    Coords.setPressed(Coords.getCorner(opp));
    Coords.setCurrent(opc);

    Tracker.activateHandlers(dragmodeHandler(mode, fc), doneSelect, touch);
  }

  function dragmodeHandler(mode, f) {
    return function (pos) {
      if (!options.aspectRatio) {
        switch (mode) {
          case 'e':
            pos[1] = f.y2;
            break;
          case 'n':
            pos[0] = f.x2;
            break;
          case 's':
            pos[0] = f.x2;
            break;
          case 'w':
            pos[1] = f.y2;
            break;
        }
      } else {
        switch (mode) {
          case 'e':
            pos[1] = f.y + 1;
            break;
          case 'n':
            pos[0] = f.x + 1;
            break;
          case 's':
            pos[0] = f.x + 1;
            break;
          case 'w':
            pos[1] = f.y + 1;
            break;
        }
      }
      Coords.setCurrent(pos);
      Selection.update();
    };
  }

  function createMover(pos) {
    let lloc = pos;

    return function (pos) {
      Coords.moveOffset([pos[0] - lloc[0], pos[1] - lloc[1]]);
      lloc = pos;

      Selection.update();
    };
  }

  function oppLockCorner(ord) {
    switch (ord) {
      case 'e':
        return 'nw';
      case 'n':
        return 'sw';
      case 'ne':
        return 'sw';
      case 'nw':
        return 'se';
      case 's':
        return 'nw';
      case 'se':
        return 'nw';
      case 'sw':
        return 'ne';
      case 'w':
        return 'ne';
    }
  }

  function createDragger(ord) {
    return function (e) {
      if (options.disabled) {
        return false;
      }

      // Fix position of crop area when dragged the very first time.
      // Necessary when crop image is in a hidden element when page is loaded.
      docOffset = getPos($img);

      btndown = true;
      startDragMode(ord, mouseAbs(e));
      e.stopPropagation();
      e.preventDefault();
      return false;
    };
  }

  function presize($obj, w, h) {
    let nh = $obj.height(),
      nw = $obj.width();
    if (nw > w && w > 0) {
      nw = w;
      nh = (w / $obj.width()) * $obj.height();
    }
    if (nh > h && h > 0) {
      nh = h;
      nw = (h / $obj.height()) * $obj.width();
    }
    xscale = $obj.width() / nw;
    yscale = $obj.height() / nh;
    $obj.width(nw).height(nh);
  }

  function unscale(c) {
    return {
      h: c.h * yscale,
      w: c.w * xscale,
      x: c.x * xscale,
      x2: c.x2 * xscale,
      y: c.y * yscale,
      y2: c.y2 * yscale,
    };
  }

  function doneSelect() {
    const c = Coords.getFixed();
    if (c.w > options.minSelect[0] && c.h > options.minSelect[1]) {
      Selection.enableHandles();
      Selection.done();
    } else {
      Selection.release();
    }
    Tracker.setCursor('crosshair');
  }

  function newSelection(e) {
    if (options.disabled) {
      return false;
    }
    btndown = true;
    docOffset = getPos($img);
    Selection.disableHandles();
    Tracker.setCursor('crosshair');
    const pos = mouseAbs(e);
    Coords.setPressed(pos);
    Selection.update();
    Tracker.activateHandlers(selectDrag, doneSelect, e.type.substring(0, 5) === 'touch');

    e.stopPropagation();
    e.preventDefault();
    return false;
  }

  function selectDrag(pos) {
    Coords.setCurrent(pos);
    Selection.update();
  }

  function newTracker() {
    const trk = $('<div></div>').addClass(cssClass('tracker'));
    if (is_msie) {
      trk.css({
        backgroundColor: 'white',
        opacity: 0,
      });
    }
    return trk;
  }

  $img2 = $('<img />').attr('src', $img.attr('src')).css(img_css).width(boundx).height(boundy);
  $img_holder.append($img2);

  /* }}} */
  // Set more variables {{{
  let bgcolor = 'black',
    bgopacity = options.bgOpacity,
    xlimit,
    xmin,
    ylimit,
    ymin;

  docOffset = getPos($img);
  // }}}
  // }}}
  // Internal Modules {{

  // }}}
  // API methods {{{

  function setSelect(rect) {
    setSelectRaw([rect[0] / xscale, rect[1] / yscale, rect[2] / xscale, rect[3] / yscale]);
    options.onSelect.call(api, unscale(Coords.getFixed()));
    Selection.enableHandles();
  }

  function setSelectRaw(l) {
    Coords.setPressed([l[0], l[1]]);
    Coords.setCurrent([l[2], l[3]]);
    Selection.update();
  }

  function setOptionsNew(opt) {
    setOptions(opt);
    interfaceUpdate();
  }

  function disableCrop() {
    options.disabled = true;
    Selection.disableHandles();
    Selection.setCursor('default');
    Tracker.setCursor('default');
  }

  function enableCrop() {
    options.disabled = false;
    interfaceUpdate();
  }

  function cancelCrop() {
    Selection.done();
    Tracker.activateHandlers(null, null);
  }

  function destroy() {
    $div.remove();
    $origimg.show();
    $origimg.css('visibility', 'visible');
    $(obj).removeData('Jcrop');
  }

  function interfaceUpdate(
    alt, // This method tweaks the interface based on options object. // Called when options are changed and at end of initialization.
  ) {
    if (alt) {
      Selection.enableOnly();
    } else {
      Selection.enableHandles();
    }

    Tracker.setCursor('crosshair');
    Selection.setCursor('move');

    if (options.hasOwnProperty('trueSize')) {
      xscale = options.trueSize[0] / boundx;
      yscale = options.trueSize[1] / boundy;
    }

    if (options.hasOwnProperty('setSelect')) {
      setSelect(options.setSelect);
      Selection.done();
      delete options.setSelect;
    }

    if ('black' !== bgcolor) {
      $div.css('backgroundColor', 'black');
      bgcolor = 'black';
    }

    if (bgopacity !== options.bgOpacity) {
      bgopacity = options.bgOpacity;
      Selection.setBgOpacity(bgopacity);
    }

    xlimit = options.maxSize[0] || 0;
    ylimit = options.maxSize[1] || 0;
    xmin = options.minSize[0] || 0;
    ymin = options.minSize[1] || 0;

    if (options.hasOwnProperty('outerImage')) {
      $img.attr('src', options.outerImage);
      delete options.outerImage;
    }

    Selection.refresh();
  }

  if (Touch.support) $trk.on('touchstart.jcrop', Touch.newSelection);

  $hdl_holder.hide();
  interfaceUpdate(true);

  if (is_msie)
    $div.on('selectstart', function () {
      return false;
    });

  $origimg.data('Jcrop', api);
  return api;
}

export default Jcrop;
