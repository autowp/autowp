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
 * Tracker/Coords, driving the drag/resize state machine) now lives directly on JcropComponent -
 * there's only ever one consumer, so a separate "Jcrop instance" object was pure indirection. What
 * remains here are the four collaborator classes (kept separate, unlike the orchestration, since
 * each owns a distinct piece of DOM/state) plus the option types/defaults and small DOM helpers they
 * and JcropComponent both need.
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
// the upstream plugin supported (bgOpacity/borderOpacity/boundary/createBorders/createDragbars/
// createHandles/handleOpacity/minSelect/touchSupport) is still implemented - it just always runs
// with its DefaultedOptions default now, since nothing here can override it any more.
// addClass/aspectRatio/disabled/handleSize/keySupport/maxSize/outerImage were dropped entirely
// (not just defaulted): each was either dead on arrival (keySupport - no keyboard-nudge support was
// ever ported into this vendored copy) or gated a whole branch behind a falsy sentinel
// (null/0/[0,0]/false) that, once unoverridable, could never turn truthy again, so the branch
// itself was dead code, not just fixed-at-its-default.
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
  borderOpacity: number;
  boundary: number;
  boxHeight: number;
  boxWidth: number;
  createBorders: Ordinal[];
  createDragbars: Ordinal[];
  createHandles: Ordinal[];
  handleOpacity: number;
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
  borderOpacity: 0.4,
  boundary: 2,

  boxHeight: 0,
  boxWidth: 0,
  createBorders: ['n', 's', 'e', 'w'],
  createDragbars: ['n', 's', 'e', 'w'],
  createHandles: ['n', 's', 'e', 'w', 'nw', 'ne', 'se', 'sw'],

  handleOpacity: 0.5,

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

export function cssClass(cl: string): string {
  return 'jcrop-' + cl;
}

// Owns the "new selection" tracker overlay - the invisible full-image click target used both to
// start a fresh selection drag and, once active, to forward mousemove/touchmove/mouseup/touchend on
// `document` to whichever move/done callback the caller currently has active (set via
// activateHandlers(), one call per drag gesture: newSelection() for a fresh drag, startDragMode()
// for resizing/moving an existing one). Kept independent of Selection/Coords/options - the two
// things it needs from outside are provided as plain function references so it doesn't have to know
// about those modules at all: `mouseAbs`/`touchCfilter` (shared, stateful math owned by
// JcropComponent) and `notifySelectionSettled` (what to do once a drag ends and the selection has
// possibly changed - checking whether there's now an awake selection and firing onSelect is the
// caller's business, not Tracker's).
export class Tracker {
  #btndown: boolean | undefined;
  #onDone: PositionCallback = function () {};
  #onMove: PositionCallback = function () {};

  constructor(
    private readonly doc: Document,
    private readonly trk: HTMLDivElement,
    private readonly mouseAbs: (e: JcropMouseEvent) => Point,
    private readonly touchCfilter: (e: JcropMouseEvent) => JcropMouseEvent,
    private readonly notifySelectionSettled: () => void,
  ) {}

  activateHandlers(move: PositionCallback, done: PositionCallback, touch?: boolean): void {
    this.#btndown = true;
    this.#onMove = move;
    this.#onDone = done;
    this.#toFront(touch);
  }

  setCursor(t: string): void {
    setStyle(this.trk, {cursor: t});
  }

  #toFront(touch?: boolean): void {
    setStyle(this.trk, {zIndex: '450'});

    if (touch) {
      this.doc.addEventListener('touchmove', this.#trackTouchMove);
      this.doc.addEventListener('touchend', this.#trackTouchEnd);
    } else {
      this.doc.addEventListener('mousemove', this.#trackMove);
      this.doc.addEventListener('mouseup', this.#trackUp);
    }
  }

  #toBack(): void {
    setStyle(this.trk, {zIndex: '290'});
    this.doc.removeEventListener('mousemove', this.#trackMove);
    this.doc.removeEventListener('mouseup', this.#trackUp);
    this.doc.removeEventListener('touchmove', this.#trackTouchMove);
    this.doc.removeEventListener('touchend', this.#trackTouchEnd);
  }

  // Field arrows (not methods): registered on `document` via addEventListener/removeEventListener
  // pairs in #toFront/#toBack, which match handlers by reference - a plain method isn't auto-bound
  // to `this`, so `this.#trackMove` etc. have to be the same stable, already-bound function object
  // every time they're read, both to add and to later remove the exact same listener.
  readonly #trackMove = (e: JcropMouseEvent): boolean => {
    this.#onMove(this.mouseAbs(e));
    return false;
  };

  readonly #trackUp = (e: JcropMouseEvent): boolean => {
    e.preventDefault();
    e.stopPropagation();

    if (this.#btndown) {
      this.#btndown = false;

      this.#onDone(this.mouseAbs(e));
      this.notifySelectionSettled();

      this.#toBack();
      this.#onMove = function () {};
      this.#onDone = function () {};
    }

    return false;
  };

  readonly #trackTouchMove = (e: JcropMouseEvent): boolean => {
    this.#onMove(this.mouseAbs(this.touchCfilter(e)));
    return false;
  };

  readonly #trackTouchEnd = (e: JcropMouseEvent): boolean => {
    return this.#trackUp(this.touchCfilter(e));
  };
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
// clipped to it. The "move" tracker overlay for dragging an existing selection is a sibling concern,
// not owned here - JcropComponent binds mousedown/touchstart on it directly in its own template,
// calling back into the same createDragger/createTouchDragger this class uses for its own handles.
// Kept independent of Touch by taking its two touch-specific bits (whether it's supported, and how
// to build a touch-drag handler) as plain values/functions rather than the Touch object itself -
// same decoupling as Tracker. `coords` is taken as the real collaborator object it is (not
// individually wrapped getters), since by the time Selection needs it, it already exists and never
// gets replaced.
export class Selection {
  #awake: boolean | undefined;
  #hdep = 370;

  constructor(
    private readonly doc: Document,
    private readonly img: HTMLImageElement,
    private readonly imgHolder: HTMLDivElement,
    private readonly hdlHolder: HTMLDivElement,
    // img2 starts as a placeholder div and is replaced with a real <img> right after Selection is
    // constructed (see JcropComponent's own img2 field for why) - a getter so #moveto() always
    // styles whichever element is current by the time it's actually called, not the placeholder.
    private readonly getImg2: () => HTMLElement,
    private readonly sel: HTMLDivElement,
    private readonly bgopacity: number,
    private readonly touchSupport: boolean,
    private readonly createDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly createTouchDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly getOptions: () => InternalOptions,
    private readonly coords: Coords,
    private readonly notifySelect: (crop: JcropCrop & {x2: number; y2: number}) => void,
  ) {
    const options = this.getOptions();

    // createDragbars/createHandles/createBorders are DefaultedOptions fields (not part of the
    // public JcropOptions surface any more), so they're always real arrays here - no
    // Array.isArray() guard needed.
    this.#createDragbars(options.createDragbars);
    this.#createHandles(options.createHandles);
    this.#createBorders(options.createBorders);

    this.disableHandles();
  }

  disableHandles(): void {
    setStyle(this.hdlHolder, {display: 'none'});
  }

  done(): void {
    this.refresh();
  }

  enableHandles(): void {
    setStyle(this.hdlHolder, {display: ''});
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

  #insertBorder(type: string): void {
    const el = this.doc.createElement('div');
    setStyle(el, {opacity: String(this.getOptions().borderOpacity), position: 'absolute'});
    el.classList.add(cssClass(type));
    this.imgHolder.append(el);
  }

  #dragDiv(ord: Ordinal, zi: number): HTMLDivElement {
    const el = this.doc.createElement('div');
    el.addEventListener('mousedown', this.createDragger(ord));
    setStyle(el, {cursor: ord + '-resize', position: 'absolute', zIndex: String(zi)});
    el.classList.add('ord-' + ord);

    if (this.touchSupport) {
      el.addEventListener('touchstart', this.createTouchDragger(ord));
    }

    this.hdlHolder.append(el);
    return el;
  }

  #insertHandle(ord: Ordinal): void {
    const div = this.#dragDiv(ord, this.#hdep++);
    setStyle(div, {opacity: String(this.getOptions().handleOpacity)});
    div.classList.add(cssClass('handle'));
  }

  #insertDragbar(ord: Ordinal): void {
    const el = this.#dragDiv(ord, this.#hdep++);
    el.classList.add('jcrop-dragbar');
  }

  #createDragbars(li: Ordinal[]): void {
    for (const ord of li) {
      this.#insertDragbar(ord);
    }
  }

  #createBorders(li: Ordinal[]): void {
    let cl = '';
    for (const ord of li) {
      switch (ord) {
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
      this.#insertBorder(cl);
    }
  }

  #createHandles(li: Ordinal[]): void {
    for (const ord of li) {
      this.#insertHandle(ord);
    }
  }

  #moveto(x: number, y: number): void {
    setStyle(this.getImg2(), {left: px(-x), top: px(-y)});
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
