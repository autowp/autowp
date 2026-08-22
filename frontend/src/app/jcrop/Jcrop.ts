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
// createHandles/disabled/handleOpacity/minSelect/touchSupport) is still implemented - it just
// always runs with its DefaultedOptions default now, since nothing here can override it any more.
// addClass/aspectRatio/handleSize/maxSize/outerImage were dropped entirely (not just defaulted):
// each gated a whole branch behind a falsy sentinel (null/0/[0,0]) that, once unoverridable, could
// never turn truthy again, so the branch itself was dead code, not just fixed-at-its-default.
export interface JcropOptions {
  boxHeight?: number;
  boxWidth?: number;
  // Accepted for API compatibility with the upstream plugin, but dead in this vendored copy - no
  // keyboard-nudge support was ported over, so this option is read nowhere below.
  keySupport?: boolean;
  minSize?: number[];
  onSelect?: (crop: JcropCrop) => void;
  setSelect?: number[];
  trueSize?: number[];
}

export interface JcropInstance {
  cancel: () => void;
  destroy: () => void;
  disable: () => void;
  enable: () => void;
  focus: null;
  release: () => void;
  setOptions: (opt: JcropOptions) => void;
  setSelect: (rect: number[]) => void;
  ui: {
    holder: HTMLDivElement;
    selection: HTMLDivElement;
  };
}

// The subset of options `defaults` always supplies a value for - everything else on JcropOptions
// (keySupport/setSelect/trueSize) is genuinely optional at runtime, read only behind an
// `options.hasOwnProperty(...)` check (or, for keySupport, nowhere at all).
interface DefaultedOptions {
  bgOpacity: number;
  borderOpacity: number;
  boundary: number;
  boxHeight: number;
  boxWidth: number;
  createBorders: Ordinal[];
  createDragbars: Ordinal[];
  createHandles: Ordinal[];
  disabled: boolean;
  handleOpacity: number;
  minSelect: number[];
  minSize: number[];
  onSelect: (crop: JcropCrop) => void;
  touchSupport: boolean | null;
}

type InternalOptions = DefaultedOptions & JcropOptions;

type Point = [number, number];

// A native MouseEvent/TouchEvent, widened with a writable pageX/pageY: Touch#cfilter() copies the
// active touch's page coordinates onto the event itself so mouseAbs() can read event.pageX/pageY
// the same way regardless of whether the drag started from a mouse or touch listener - TouchEvent
// doesn't carry its own pageX/pageY (only the individual Touch entries in its touch lists do).
type JcropMouseEvent = (MouseEvent | TouchEvent) & {pageX?: number; pageY?: number};

type PositionCallback = (pos: Point) => void;

// A handle/border/dragbar position, or the drag mode passed around while resizing/moving the
// selection - 'move' alongside the 8 ordinals rather than a separate type, since startDragMode's
// mode parameter is exactly this union and every ordinal-only site narrows it via `mode !== 'move'`.
type Ordinal = 'e' | 'n' | 'ne' | 'nw' | 's' | 'se' | 'sw' | 'w';
type DragMode = 'move' | Ordinal;

// oppLockCorner only ever maps onto a diagonal - the corner opposite the dragged edge/corner, which
// getCorner then reads off Coords - so both are typed to this narrower union rather than Ordinal.
type Corner = 'ne' | 'nw' | 'se' | 'sw';

// Global Defaults {{{
const defaults: DefaultedOptions = {
  // Styling Options
  bgOpacity: 0.6,
  borderOpacity: 0.4,
  boundary: 2,

  boxHeight: 0,
  boxWidth: 0,
  createBorders: ['n', 's', 'e', 'w'],
  createDragbars: ['n', 's', 'e', 'w'],
  createHandles: ['n', 's', 'e', 'w', 'nw', 'ne', 'se', 'sw'],

  disabled: false,
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
function setStyle(el: HTMLElement, styles: Partial<CSSStyleDeclaration>): void {
  Object.assign(el.style, styles);
}

function px(n: number): string {
  return Math.round(n) + 'px';
}

function cssClass(cl: string): string {
  return 'jcrop-' + cl;
}

// Owns the "new selection" tracker overlay - the invisible full-image click target used both to
// start a fresh selection drag and, once active, to forward mousemove/touchmove/mouseup/touchend on
// `document` to whichever move/done callback the caller currently has active (set via
// activateHandlers(), one call per drag gesture: newSelection() for a fresh drag, startDragMode()
// for resizing/moving an existing one). Kept independent of Selection/Coords/options - the two
// things it needs from outside are provided as plain function references so it doesn't have to know
// about those modules at all: `mouseAbs`/`touchCfilter` (shared, stateful math owned by the outer
// Jcrop instance) and `notifySelectionSettled` (what to do once a drag ends and the selection has
// possibly changed - checking whether there's now an awake selection and firing onSelect is the
// caller's business, not Tracker's).
class Tracker {
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

  activateHandlers(move: PositionCallback, done: PositionCallback, touch?: boolean): boolean {
    this.#btndown = true;
    this.#onMove = move;
    this.#onDone = done;
    this.#toFront(touch);
    return false;
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
// never change after construction, so they're plain constructor values; `minSize` (part of the
// outer Jcrop options, which get wholesale-reassigned on setOptions()) and `xscale`/`yscale`
// (recomputed by presize()/interfaceUpdate() outside this class) do change, so those are read live
// through the two getters instead of copied in once. xmin/ymin, by contrast, are written from
// outside (interfaceUpdate()) but read only here, so they're owned outright as private state with a
// setter.
class Coords {
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

// Owns the visible selection box: its resize handles, dragbars, borders, the "move" tracker overlay
// for dragging an existing selection, and the crop-preview image clipped to it. Kept independent of
// Touch by taking its two touch-specific bits (whether it's supported, and how to build a
// touch-drag handler) as plain values/functions rather than the Touch object itself - same
// decoupling as Tracker. `coords` is taken as the real collaborator object it is (not individually
// wrapped getters), since by the time Selection needs it, it already exists and never gets replaced.
class Selection {
  #awake: boolean | undefined;
  readonly #borders: Record<string, HTMLDivElement> = {};
  readonly #dragbar: Record<string, HTMLDivElement> = {};
  readonly #handle: Record<string, HTMLDivElement> = {};
  #hdep = 370;

  constructor(
    private readonly doc: Document,
    private readonly img: HTMLImageElement,
    private readonly imgHolder: HTMLDivElement,
    private readonly hdlHolder: HTMLDivElement,
    // img2 starts as a placeholder div and is replaced with a real <img> right after Selection is
    // constructed (see Jcrop's own img2 field for why) - a getter so #moveto() always styles
    // whichever element is current by the time it's actually called, not the placeholder.
    private readonly getImg2: () => HTMLElement,
    private readonly sel: HTMLDivElement,
    private readonly bgopacity: number,
    private readonly touchSupport: boolean,
    private readonly createDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly createTouchDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly getOptions: () => InternalOptions,
    private readonly coords: Coords,
    private readonly notifySelect: (crop: JcropCrop & {x2: number; y2: number}) => void,
    track: HTMLDivElement,
  ) {
    const options = this.getOptions();

    if (Array.isArray(options.createDragbars)) this.#createDragbars(options.createDragbars);
    if (Array.isArray(options.createHandles)) this.#createHandles(options.createHandles);
    if (Array.isArray(options.createBorders)) this.#createBorders(options.createBorders);

    track.addEventListener('mousedown', createDragger('move'));
    if (touchSupport) {
      track.addEventListener('touchstart', createTouchDragger('move'));
    }
    imgHolder.append(track);
    this.disableHandles();
  }

  disableHandles(): void {
    setStyle(this.hdlHolder, {display: 'none'});
  }

  done(): void {
    this.refresh();
  }

  enableHandles(): boolean {
    setStyle(this.hdlHolder, {display: ''});
    return true;
  }

  enableOnly(): void {}

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

  #insertBorder(type: string): HTMLDivElement {
    const el = this.doc.createElement('div');
    setStyle(el, {opacity: String(this.getOptions().borderOpacity), position: 'absolute'});
    el.classList.add(cssClass(type));
    this.imgHolder.append(el);
    return el;
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

  #insertHandle(ord: Ordinal): HTMLDivElement {
    const div = this.#dragDiv(ord, this.#hdep++);
    setStyle(div, {opacity: String(this.getOptions().handleOpacity)});
    div.classList.add(cssClass('handle'));

    return div;
  }

  #insertDragbar(ord: Ordinal): HTMLDivElement {
    const el = this.#dragDiv(ord, this.#hdep++);
    el.classList.add('jcrop-dragbar');
    return el;
  }

  #createDragbars(li: Ordinal[]): void {
    for (const ord of li) {
      this.#dragbar[ord] = this.#insertDragbar(ord);
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
      this.#borders[ord] = this.#insertBorder(cl);
    }
  }

  #createHandles(li: Ordinal[]): void {
    for (const ord of li) {
      this.#handle[ord] = this.#insertHandle(ord);
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
// `startDragMode`/`onNewSelection` stay outside this class (passed in) since they belong to the
// outer Jcrop instance's own drag-mode/selection logic, not to touch handling specifically -
// mousedown and touchstart both ultimately call the same logic, just filtered through cfilter() or
// not first.
class Touch {
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
      if (this.getOptions().disabled) {
        return;
      }
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

// The vendored plugin's own single closure-based factory function, ported to a class: everything
// that used to be a local variable closed over by every nested function is now a private field, and
// the standalone Touch/Selection/Tracker/Coords modules it built as IIFEs (see those classes above)
// are collaborator objects constructed here and wired together explicitly instead.
//
// A handful of methods below are field arrows rather than ordinary methods -
// #getPos/#mouseAbs/#startDragMode/#createDragger/#newSelection/#doneSelect/#selectDrag - because
// each is handed to a collaborator (Touch/Selection/Tracker, or a native addEventListener) by bare
// reference rather than called directly; an ordinary method read that way loses its `this` binding
// the moment something else invokes it.
export class Jcrop implements JcropInstance {
  readonly focus: null = null;

  readonly #origimg: HTMLImageElement;
  readonly #img: HTMLImageElement;
  readonly #hdlHolder: HTMLDivElement;
  readonly #imgHolder: HTMLDivElement;
  readonly #div: HTMLDivElement;
  readonly #sel: HTMLDivElement;
  readonly #boundx: number;
  readonly #boundy: number;
  readonly #coords: Coords;
  readonly #touch: Touch;
  readonly #bgopacity: number;
  readonly #selection: Selection;
  readonly #tracker: Tracker;
  // Starts as a placeholder div, replaced with a real <img> further down in this same constructor -
  // #moveto() (inside Selection, via the getImg2 getter passed to it) is the only thing that reads
  // this afterward, so the placeholder is never actually rendered. Both assignments happen here in
  // the constructor, never in a method, so this can stay readonly despite being set twice.
  readonly #img2: HTMLElement;

  #options: InternalOptions;

  // Assigned by #presize()/#interfaceUpdate(), not by a direct `this.x = ...` in the constructor, so
  // TS's definite-assignment analysis can't see it - always set before anything reads them.
  #xscale!: number;
  #yscale!: number;
  #docOffset!: Point;

  #bgcolor = 'black';

  constructor(
    obj: HTMLImageElement,
    opt: JcropOptions,
    private readonly doc: Document,
    private readonly win: Window,
  ) {
    this.#options = {...defaults};

    this.#hdlHolder = doc.createElement('div');
    setStyle(this.#hdlHolder, {height: '100%', width: '100%', zIndex: '320'});

    this.#imgHolder = doc.createElement('div');
    setStyle(this.#imgHolder, {
      height: '100%',
      overflow: 'hidden',
      position: 'absolute',
      width: '100%',
      zIndex: '310',
    });

    // Initialization {{{
    this.#mergeOptions(opt);
    // Initialize the DOM elements for the interface {{{
    // The values are SET on the image(s) for the interface
    // If the original image has any of these set, they will be reset
    // However, if you destroy() the Jcrop instance the original image's
    // character in the DOM will be as you left it.
    const imgStyle: Partial<CSSStyleDeclaration> = {
      border: 'none',
      left: '0',
      margin: '0',
      padding: '0',
      position: 'absolute',
      top: '0',
      visibility: 'visible',
    };

    const origimg = obj;
    this.#origimg = origimg;

    if (origimg.tagName !== 'IMG') {
      throw new Error('Only img is supported');
    }
    // Fix size of crop image.
    // Necessary when crop image is within a hidden element when page is loaded.
    if (origimg.width !== 0 && origimg.height !== 0) {
      // Obtain dimensions from contained img element.
      setStyle(origimg, {height: px(origimg.height), width: px(origimg.width)});
    } else {
      // width/height read 0 above because the source <img> sits inside a hidden (display:none)
      // container at this point in every browser, not just old IE - load a detached copy to read
      // its intrinsic dimensions instead.
      const tempImage = new Image();
      tempImage.src = origimg.getAttribute('src') ?? '';
      setStyle(origimg, {height: px(tempImage.height), width: px(tempImage.width)});
    }

    const img = origimg.cloneNode(true) as HTMLImageElement;
    this.#img = img;
    img.removeAttribute('id');
    setStyle(img, imgStyle);
    setStyle(img, {display: ''});

    setStyle(img, {height: px(origimg.offsetHeight), width: px(origimg.offsetWidth)});
    origimg.after(img);
    setStyle(origimg, {display: 'none'});

    this.#presize(img, this.#options.boxWidth, this.#options.boxHeight);

    this.#boundx = img.offsetWidth;
    this.#boundy = img.offsetHeight;
    const div = doc.createElement('div');
    this.#div = div;
    setStyle(div, {
      backgroundColor: 'black',
      height: px(this.#boundy),
      position: 'relative',
      width: px(this.#boundx),
    });
    div.classList.add(cssClass('holder'));
    origimg.after(div);
    div.append(img);

    const bound = this.#options.boundary;
    const trk = this.#newTracker();
    setStyle(trk, {
      height: px(this.#boundy + bound * 2),
      left: px(-bound),
      position: 'absolute',
      top: px(-bound),
      width: px(this.#boundx + bound * 2),
      zIndex: '290',
    });
    trk.addEventListener('mousedown', this.#newSelection);

    this.#img2 = doc.createElement('div');
    const sel = doc.createElement('div');
    this.#sel = sel;
    setStyle(sel, {position: 'absolute', zIndex: '600'});
    img.before(sel);
    sel.append(this.#imgHolder, this.#hdlHolder);

    // Coords Module {{{
    this.#coords = new Coords(this.#boundx, this.#boundy, () => ({xscale: this.#xscale, yscale: this.#yscale}));
    // }}}

    // Touch Module {{{
    this.#touch = new Touch(
      win,
      img,
      () => this.#options,
      (el) => this.#getPos(el),
      (e) => this.#mouseAbs(e),
      (mode, pos, touch) => {
        this.#startDragMode(mode, pos, touch);
      },
      (pos) => {
        this.#docOffset = pos;
      },
      (e) => {
        this.#newSelection(e);
      },
    );

    this.#bgopacity = this.#options.bgOpacity;

    // Selection Module {{{
    // This is a hack for iOS5 to support drag/move touch functionality. Note that e.currentTarget
    // is always `document` here (this listener is bound directly on it, not delegated), so the
    // `instanceof Element` check below never passes and stopPropagation() never actually runs -
    // jQuery's .hasClass() had the equivalent guard (elem.nodeType === 1) built in, silently
    // no-opping for a Document node rather than throwing; preserved as dead-but-safe rather than
    // "fixed" to e.target, since that would be a behavior change from the original.
    doc.addEventListener('touchstart', function (e) {
      const target = e.currentTarget;
      if (target instanceof Element && target.classList.contains('jcrop-tracker')) e.stopPropagation();
    });

    const selectionTrack = this.#newTracker();
    setStyle(selectionTrack, {cursor: 'move', position: 'absolute', zIndex: '360'});

    this.#selection = new Selection(
      doc,
      img,
      this.#imgHolder,
      this.#hdlHolder,
      () => this.#img2,
      sel,
      this.#bgopacity,
      this.#touch.support,
      (ord) => this.#createDragger(ord),
      (ord) => this.#touch.createDragger(ord),
      () => this.#options,
      this.#coords,
      (c) => {
        this.#options.onSelect.call(this, this.#unscale(c));
      },
      selectionTrack,
    );

    // Tracker Module {{{
    this.#tracker = new Tracker(
      doc,
      trk,
      (e) => this.#mouseAbs(e),
      (e) => this.#touch.cfilter(e),
      () => {
        if (this.#selection.isAwake()) {
          this.#options.onSelect.call(this, this.#unscale(this.#coords.getFixed()));
        }
      },
    );
    img.before(trk);

    this.#img2 = doc.createElement('img');
    (this.#img2 as HTMLImageElement).src = img.getAttribute('src') ?? '';
    setStyle(this.#img2, imgStyle);
    setStyle(this.#img2, {display: '', height: px(this.#boundy), width: px(this.#boundx)});
    this.#imgHolder.append(this.#img2);

    this.#docOffset = this.#getPos(img);

    if (this.#touch.support) {
      trk.addEventListener('touchstart', (e: JcropMouseEvent) => {
        this.#touch.newSelection(e);
      });
    }

    setStyle(this.#hdlHolder, {display: 'none'});
    this.#interfaceUpdate(true);
  }

  // API methods {{{

  cancel(): void {
    this.#selection.done();
  }

  destroy(): void {
    this.#div.remove();
    setStyle(this.#origimg, {display: '', visibility: 'visible'});
  }

  disable(): void {
    this.#options.disabled = true;
    this.#selection.disableHandles();
    this.#tracker.setCursor('default');
  }

  enable(): void {
    this.#options.disabled = false;
    this.#interfaceUpdate();
  }

  release(): void {
    this.#selection.release();
  }

  setOptions(opt: JcropOptions): void {
    this.#mergeOptions(opt);
    this.#interfaceUpdate();
  }

  setSelect(rect: number[]): void {
    this.#setSelectRaw([
      (rect[0] ?? 0) / this.#xscale,
      (rect[1] ?? 0) / this.#yscale,
      (rect[2] ?? 0) / this.#xscale,
      (rect[3] ?? 0) / this.#yscale,
    ]);
    this.#options.onSelect.call(this, this.#unscale(this.#coords.getFixed()));
    this.#selection.enableHandles();
  }

  get ui(): {holder: HTMLDivElement; selection: HTMLDivElement} {
    return {holder: this.#div, selection: this.#sel};
  }

  // }}}

  // Internal Methods {{{
  readonly #getPos = (el: HTMLElement): Point => {
    const rect = el.getBoundingClientRect();
    return [rect.left + this.win.scrollX, rect.top + this.win.scrollY];
  };

  readonly #mouseAbs = (e: JcropMouseEvent): Point => {
    return [(e.pageX ?? 0) - this.#docOffset[0], (e.pageY ?? 0) - this.#docOffset[1]];
  };

  #mergeOptions(opt: JcropOptions): void {
    this.#options = {...this.#options, ...opt};

    if (typeof this.#options.onSelect !== 'function') {
      this.#options.onSelect = function () {};
    }
  }

  readonly #startDragMode = (mode: DragMode, pos: Point, touch?: boolean): void => {
    this.#docOffset = this.#getPos(this.#img);
    this.#tracker.setCursor(mode === 'move' ? mode : mode + '-resize');

    if (mode === 'move') {
      this.#tracker.activateHandlers(this.#createMover(pos), this.#doneSelect, touch);
      return;
    }

    const fc = this.#coords.getFixed();
    const opp = this.#oppLockCorner(mode);
    const opc = this.#coords.getCorner(this.#oppLockCorner(opp));

    this.#coords.setPressed(this.#coords.getCorner(opp));
    this.#coords.setCurrent(opc);

    this.#tracker.activateHandlers(this.#dragmodeHandler(mode, fc), this.#doneSelect, touch);
  };

  #dragmodeHandler(mode: Ordinal, f: JcropCrop & {x2: number; y2: number}): PositionCallback {
    return (pos: Point): void => {
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
      this.#coords.setCurrent(pos);
      this.#selection.update();
    };
  }

  #createMover(pos: Point): PositionCallback {
    let lloc = pos;

    return (pos: Point): void => {
      this.#coords.moveOffset([pos[0] - lloc[0], pos[1] - lloc[1]]);
      lloc = pos;

      this.#selection.update();
    };
  }

  #oppLockCorner(ord: Ordinal): Corner {
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

  readonly #createDragger = (ord: DragMode): ((e: JcropMouseEvent) => void) => {
    return (e: JcropMouseEvent): void => {
      if (this.#options.disabled) {
        return;
      }

      // Fix position of crop area when dragged the very first time.
      // Necessary when crop image is in a hidden element when page is loaded.
      this.#docOffset = this.#getPos(this.#img);

      this.#startDragMode(ord, this.#mouseAbs(e));
      e.stopPropagation();
      e.preventDefault();
    };
  };

  #presize(el: HTMLElement, w: number, h: number): void {
    let nh = el.offsetHeight,
      nw = el.offsetWidth;
    if (nw > w && w > 0) {
      nw = w;
      nh = (w / el.offsetWidth) * el.offsetHeight;
    }
    if (nh > h && h > 0) {
      nh = h;
      nw = (h / el.offsetHeight) * el.offsetWidth;
    }
    this.#xscale = el.offsetWidth / nw;
    this.#yscale = el.offsetHeight / nh;
    setStyle(el, {height: px(nh), width: px(nw)});
  }

  #unscale(c: JcropCrop & {x2: number; y2: number}): JcropCrop {
    return {
      h: c.h * this.#yscale,
      w: c.w * this.#xscale,
      x: c.x * this.#xscale,
      y: c.y * this.#yscale,
    };
  }

  readonly #doneSelect = (): void => {
    const c = this.#coords.getFixed();
    const minSelect = this.#options.minSelect;
    if (c.w > minSelect[0] && c.h > minSelect[1]) {
      this.#selection.enableHandles();
      this.#selection.done();
    } else {
      this.#selection.release();
    }
    this.#tracker.setCursor('crosshair');
  };

  readonly #newSelection = (e: JcropMouseEvent): void => {
    if (this.#options.disabled) {
      return;
    }
    this.#docOffset = this.#getPos(this.#img);
    this.#selection.disableHandles();
    this.#tracker.setCursor('crosshair');
    const pos = this.#mouseAbs(e);
    this.#coords.setPressed(pos);
    this.#selection.update();
    this.#tracker.activateHandlers(this.#selectDrag, this.#doneSelect, e.type.startsWith('touch'));

    e.stopPropagation();
    e.preventDefault();
  };

  readonly #selectDrag = (pos: Point): void => {
    this.#coords.setCurrent(pos);
    this.#selection.update();
  };

  #newTracker(): HTMLDivElement {
    const el = this.doc.createElement('div');
    el.classList.add(cssClass('tracker'));
    return el;
  }

  #setSelectRaw(l: [number, number, number, number]): void {
    this.#coords.setPressed([l[0], l[1]]);
    this.#coords.setCurrent([l[2], l[3]]);
    this.#selection.update();
  }

  // This method tweaks the interface based on options object. Called when options are changed and
  // at end of construction.
  #interfaceUpdate(alt?: boolean): void {
    if (alt) {
      this.#selection.enableOnly();
    } else {
      this.#selection.enableHandles();
    }

    this.#tracker.setCursor('crosshair');

    if (Object.hasOwn(this.#options, 'trueSize') && this.#options.trueSize) {
      this.#xscale = this.#options.trueSize[0] / this.#boundx;
      this.#yscale = this.#options.trueSize[1] / this.#boundy;
    }

    if (Object.hasOwn(this.#options, 'setSelect') && this.#options.setSelect) {
      this.setSelect(this.#options.setSelect);
      this.#selection.done();
      delete this.#options.setSelect;
    }

    if ('black' !== this.#bgcolor) {
      setStyle(this.#div, {backgroundColor: 'black'});
      this.#bgcolor = 'black';
    }

    this.#coords.setLimits(this.#options.minSize[0], this.#options.minSize[1]);

    this.#selection.refresh();
  }
  // }}}
}

export default Jcrop;
