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
 *
 * The plugin's own orchestrating factory function (constructing and wiring up Touch/Selection/
 * Coords, driving the drag/resize state machine) now lives directly on JcropComponent - there's
 * only ever one consumer, so a separate "Jcrop instance" object was pure indirection. The
 * document-level drag-tracking state (what used to be a separate Tracker class) lives there too now,
 * for the same reason: every collaborator it needed (mouseAbs/touchCfilter/notifySelectionSettled/
 * setZIndex) was already a closure over JcropComponent itself, so the class was pure indirection as
 * well. What remains here are the three collaborator classes that actually own a distinct piece of
 * DOM/state, plus the option types/defaults and small DOM helpers they and JcropComponent both need.
 */

function hasTouchSupport(win: Window): boolean {
  return 'ontouchstart' in win || win.navigator.maxTouchPoints > 0;
}

export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

// The only options JcropComponent (the sole consumer) ever passes at construction. Everything else
// the upstream plugin supported (bgOpacity/boundary/minSelect/touchSupport) is still implemented -
// it just always runs with its DefaultedOptions default now, since nothing here can override it
// any more.
// addClass/aspectRatio/borderOpacity/createBorders/createDragbars/createHandles/disabled/
// handleOpacity/handleSize/keySupport/maxSize/outerImage were dropped entirely (not just
// defaulted): each was either dead on arrival (keySupport - no keyboard-nudge support was ever
// ported into this vendored copy), gated a whole branch behind a falsy sentinel (null/0/[0,0]/
// false) that, once unoverridable, could never turn truthy again, or - once its DefaultedOptions
// default became the only value it could ever hold - ended up read only by code that's now static
// markup instead (border/handle opacity and classes, which per-side/per-corner divs even exist to
// begin with, and - for createDragbars/createHandles specifically - which of them get their
// mousedown/touchstart bound directly in the template now instead of via a runtime loop; see
// jcrop.component.html/.scss).
export interface JcropOptions {
  boxHeight?: number;
  boxWidth?: number;
  minSize?: number[];
  onSelect?: (crop: JcropCrop) => void;
  setSelect?: number[];
  trueSize?: number[];
}

// The subset of options `defaults` always supplies a value for - everything else on JcropOptions
// (setSelect/trueSize) is genuinely optional at runtime, read only behind an
// `options.hasOwnProperty(...)` check.
interface DefaultedOptions {
  bgOpacity: number;
  boundary: number;
  boxHeight: number;
  boxWidth: number;
  minSelect: number[];
  minSize: number[];
  onSelect: (crop: JcropCrop) => void;
  touchSupport: boolean | null;
}

export type InternalOptions = DefaultedOptions & JcropOptions;

export type Point = [number, number];

// A native MouseEvent/TouchEvent, widened with a writable pageX/pageY: Touch#cfilter() copies the
// active touch's page coordinates onto the event itself so mouseAbs() can read event.pageX/pageY
// the same way regardless of whether the drag started from a mouse or touch listener - TouchEvent
// doesn't carry its own pageX/pageY (only the individual Touch entries in its touch lists do).
export type JcropMouseEvent = (MouseEvent | TouchEvent) & {pageX?: number; pageY?: number};

export type PositionCallback = (pos: Point) => void;

// A handle/border/dragbar position, or the drag mode passed around while resizing/moving the
// selection - 'move' alongside the 8 ordinals rather than a separate type, since startDragMode's
// mode parameter is exactly this union and every ordinal-only site narrows it via `mode !== 'move'`.
export type Ordinal = 'e' | 'n' | 'ne' | 'nw' | 's' | 'se' | 'sw' | 'w';
export type DragMode = 'move' | Ordinal;

// oppLockCorner only ever maps onto a diagonal - the corner opposite the dragged edge/corner, which
// getCorner then reads off Coords - so both are typed to this narrower union rather than Ordinal.
export type Corner = 'ne' | 'nw' | 'se' | 'sw';

// Global Defaults {{{
export const defaults: DefaultedOptions = {
  // Styling Options
  bgOpacity: 0.6,
  boundary: 2,

  boxHeight: 0,
  boxWidth: 0,

  minSelect: [0, 0],
  minSize: [0, 0],
  // Callbacks / Event Handlers
  onSelect: function () {},

  touchSupport: null,
};

// Every element Jcrop creates keeps zero border/padding (either by never setting any, or via
// imgStyle explicitly zeroing them), so content-box width/height - what a getter needs to return to
// stay faithful to the plugin's original jQuery .width()/.height() reads - always equals
// offsetWidth/offsetHeight here. No box-model conversion needed.
export function setStyle(el: HTMLElement, styles: Partial<CSSStyleDeclaration>): void {
  Object.assign(el.style, styles);
}

export function px(n: number): string {
  return Math.round(n) + 'px';
}

// Owns the selection rectangle's coordinate math: the pressed/current corner state (x1/y1/x2/y2)
// and everything derived from it (min-size clamping, bounding to the image). `boundx`/`boundy`
// never change after construction, so they're plain constructor values; `minSize` (read off
// #options, which JcropComponent's own init sets once and never mutates afterward) and
// `xscale`/`yscale` (recomputed by presize()/interfaceUpdate() outside this class) do change, so
// those are read live through the two getters instead of copied in once. xmin/ymin, by contrast, are
// written from outside (interfaceUpdate()) but read only here, so they're owned outright as private
// state with a setter.
export class Coords {
  #x1 = 0;
  #x2 = 0;
  #y1 = 0;
  #y2 = 0;

  #xmin = 0;
  #ymin = 0;

  constructor(
    private readonly boundx: number,
    private readonly boundy: number,
    private readonly getScale: () => {xscale: number; yscale: number},
  ) {}

  setLimits(xmin: number, ymin: number): void {
    this.#xmin = xmin;
    this.#ymin = ymin;
  }

  setPressed(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x2 = this.#x1 = rebounded[0];
    this.#y2 = this.#y1 = rebounded[1];
  }

  setCurrent(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x2 = rebounded[0];
    this.#y2 = rebounded[1];
  }

  moveOffset(offset: Point): void {
    let ox = offset[0],
      oy = offset[1];

    if (0 > this.#x1 + ox) {
      ox -= ox + this.#x1;
    }
    if (0 > this.#y1 + oy) {
      oy -= oy + this.#y1;
    }

    if (this.boundy < this.#y2 + oy) {
      oy += this.boundy - (this.#y2 + oy);
    }
    if (this.boundx < this.#x2 + ox) {
      ox += this.boundx - (this.#x2 + ox);
    }

    this.#x1 += ox;
    this.#x2 += ox;
    this.#y1 += oy;
    this.#y2 += oy;
  }

  getCorner(ord: Corner): Point {
    const c = this.getFixed();
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

  #rebound(p: Point): Point {
    let px0 = p[0],
      py0 = p[1];
    if (px0 < 0) px0 = 0;
    if (py0 < 0) py0 = 0;

    if (px0 > this.boundx) px0 = this.boundx;
    if (py0 > this.boundy) py0 = this.boundy;

    return [Math.round(px0), Math.round(py0)];
  }

  #flipCoords(x1: number, y1: number, x2: number, y2: number): [number, number, number, number] {
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

  getFixed(): JcropCrop & {x2: number; y2: number} {
    let delta;
    const xsize = this.#x2 - this.#x1,
      ysize = this.#y2 - this.#y1;
    const {xscale, yscale} = this.getScale();

    if (this.#ymin / yscale && Math.abs(ysize) < this.#ymin / yscale) {
      this.#y2 = ysize > 0 ? this.#y1 + this.#ymin / yscale : this.#y1 - this.#ymin / yscale;
    }
    if (this.#xmin / xscale && Math.abs(xsize) < this.#xmin / xscale) {
      this.#x2 = xsize > 0 ? this.#x1 + this.#xmin / xscale : this.#x1 - this.#xmin / xscale;
    }

    if (this.#x1 < 0) {
      this.#x2 -= this.#x1;
      this.#x1 -= this.#x1;
    }
    if (this.#y1 < 0) {
      this.#y2 -= this.#y1;
      this.#y1 -= this.#y1;
    }
    if (this.#x2 < 0) {
      this.#x1 -= this.#x2;
      this.#x2 -= this.#x2;
    }
    if (this.#y2 < 0) {
      this.#y1 -= this.#y2;
      this.#y2 -= this.#y2;
    }
    if (this.#x2 > this.boundx) {
      delta = this.#x2 - this.boundx;
      this.#x1 -= delta;
      this.#x2 -= delta;
    }
    if (this.#y2 > this.boundy) {
      delta = this.#y2 - this.boundy;
      this.#y1 -= delta;
      this.#y2 -= delta;
    }
    if (this.#x1 > this.boundx) {
      delta = this.#x1 - this.boundy;
      this.#y2 -= delta;
      this.#y1 -= delta;
    }
    if (this.#y1 > this.boundy) {
      delta = this.#y1 - this.boundy;
      this.#y2 -= delta;
      this.#y1 -= delta;
    }

    return this.#makeObj(this.#flipCoords(this.#x1, this.#y1, this.#x2, this.#y2));
  }

  #makeObj(a: [number, number, number, number]): JcropCrop & {x2: number; y2: number} {
    return {
      h: a[3] - a[1],
      w: a[2] - a[0],
      x: a[0],
      x2: a[2],
      y: a[1],
      y2: a[3],
    };
  }
}

// Owns the visible selection box: its resize handles, dragbars, borders, and the crop-preview image
// clipped to it. Doesn't listen for DOM events itself any more - JcropComponent binds
// mousedown/touchstart on the static handle/dragbar/tracker elements directly in its own template
// (same for borders, which need no interaction at all) and forwards them into dragStart()/
// touchDragStart() below, the same way it already does for the "move" tracker overlay via
// createDragger/createTouchDragger directly. Kept independent of Touch by taking its
// touch-specific bit (how to build a touch-drag handler) as a plain function rather than the Touch
// object itself. `coords`/`img2` are taken as the real collaborator objects they are (not
// individually wrapped getters), since by the time Selection needs them, they already exist and
// never get replaced.
export class Selection {
  #awake: boolean | undefined;

  constructor(
    private readonly img: HTMLImageElement,
    // The crop-preview image clipped inside #imgHolder (jcrop.component.html) - a real, static
    // template element now, so (unlike img/sel/coords below) it never needed a getter or a
    // placeholder swap in the first place.
    private readonly img2: HTMLImageElement,
    private readonly sel: HTMLDivElement,
    private readonly bgopacity: number,
    private readonly createDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly createTouchDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly coords: Coords,
    private readonly notifySelect: (crop: JcropCrop & {x2: number; y2: number}) => void,
    // Handle/dragbar visibility is a JcropComponent signal ([style.display] on the #hdlHolder
    // template element), not a style this class sets directly any more.
    private readonly setHandlesVisible: (visible: boolean) => void,
  ) {
    this.disableHandles();
  }

  // Forwarded from the mousedown/touchstart bindings on each handle/dragbar's static template
  // element (jcrop.component.html) - JcropComponent doesn't run the drag logic itself, just routes
  // the DOM event here with the ordinal the template already knows at each binding site.
  dragStart(ord: Ordinal, e: JcropMouseEvent): void {
    this.createDragger(ord)(e);
  }

  touchDragStart(ord: Ordinal, e: JcropMouseEvent): void {
    this.createTouchDragger(ord)(e);
  }

  disableHandles(): void {
    this.setHandlesVisible(false);
  }

  done(): void {
    this.refresh();
  }

  enableHandles(): void {
    this.setHandlesVisible(true);
  }

  isAwake(): boolean {
    return !!this.#awake;
  }

  refresh(): void {
    const c = this.coords.getFixed();

    this.coords.setPressed([c.x, c.y]);
    this.coords.setCurrent([c.x2, c.y2]);

    this.#updateVisible();
  }

  release(): void {
    this.disableHandles();
    setStyle(this.sel, {display: 'none'});

    this.#setBgOpacity(1);

    this.#awake = false;
  }

  update(select?: boolean): void {
    const c = this.coords.getFixed();

    this.#resize(c.w, c.h);
    this.#moveto(c.x, c.y);

    if (!this.#awake) this.#show();

    if (select) {
      this.notifySelect(c);
    }
  }

  #moveto(x: number, y: number): void {
    setStyle(this.img2, {left: px(-x), top: px(-y)});
    setStyle(this.sel, {left: px(x), top: px(y)});
  }

  #resize(w: number, h: number): void {
    setStyle(this.sel, {height: px(Math.round(h)), width: px(Math.round(w))});
  }

  #updateVisible(select?: boolean): void {
    if (this.#awake) {
      this.update(select);
    }
  }

  #setBgOpacity(opacity: number, force?: boolean): void {
    if (!this.#awake && !force) return;
    setStyle(this.img, {opacity: String(opacity)});
  }

  #show(): void {
    setStyle(this.sel, {display: ''});

    this.#setBgOpacity(this.bgopacity, true);

    this.#awake = true;
  }
}

// Normalizes touch input for the rest of Jcrop: computes whether touch is supported once at
// construction, copies a touch event's active-finger page coordinates onto the event itself
// (cfilter), and builds touch-flavored equivalents of the mouse drag/new-selection entry points.
// `startDragMode`/`onNewSelection` stay outside this class (passed in) since they belong to
// JcropComponent's own drag-mode/selection logic, not to touch handling specifically - mousedown and
// touchstart both ultimately call the same logic, just filtered through cfilter() or not first.
export class Touch {
  readonly support: boolean;

  constructor(
    win: Window,
    private readonly img: HTMLImageElement,
    private readonly getOptions: () => InternalOptions,
    private readonly getPos: (el: HTMLElement) => Point,
    private readonly mouseAbs: (e: JcropMouseEvent) => Point,
    private readonly startDragMode: (mode: DragMode, pos: Point, touch?: boolean) => void,
    private readonly setDocOffset: (pos: Point) => void,
    private readonly onNewSelection: (e: JcropMouseEvent) => void,
  ) {
    const touchSupport = this.getOptions().touchSupport;
    this.support = touchSupport === true || touchSupport === false ? touchSupport : hasTouchSupport(win);
  }

  cfilter(e: JcropMouseEvent): JcropMouseEvent {
    const touch = (e as TouchEvent).changedTouches[0];
    e.pageX = touch.pageX;
    e.pageY = touch.pageY;
    return e;
  }

  createDragger(ord: DragMode): (e: JcropMouseEvent) => void {
    return (e: JcropMouseEvent): void => {
      this.setDocOffset(this.getPos(this.img));
      this.startDragMode(ord, this.mouseAbs(this.cfilter(e)), true);
      e.stopPropagation();
      e.preventDefault();
    };
  }

  newSelection(e: JcropMouseEvent): void {
    this.onNewSelection(this.cfilter(e));
  }
}
