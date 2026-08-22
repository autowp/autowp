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
 * The plugin's own orchestrating factory function (constructing and wiring up Selection/Coords,
 * driving the drag/resize state machine) now lives directly on JcropComponent - there's only ever
 * one consumer, so a separate "Jcrop instance" object was pure indirection. The document-level
 * drag-tracking state and touch handling (what used to be separate Tracker and Touch classes) live
 * there too now, for the same reason: every collaborator either needed (mouseAbs/touchCfilter/
 * notifySelectionSettled/setZIndex/getPos/startDragMode/setDocOffset/onNewSelection) was already a
 * closure over JcropComponent itself, so both classes were pure indirection. What remains here are
 * the two collaborator classes that actually own a distinct piece of DOM/state, plus the option
 * types and small DOM helpers they and JcropComponent both need. The default option values
 * themselves (what used to be a `defaults` constant here) live directly in JcropComponent's #init()
 * now, for the same reason - it was the only consumer.
 */

export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

// The only options JcropComponent (the sole consumer) ever passes at construction. Everything else
// the upstream plugin supported (bgOpacity/boundary/minSelect) is still implemented - it just always
// runs with its DefaultedOptions default now, since nothing here can override it any more.
// addClass/aspectRatio/borderOpacity/createBorders/createDragbars/createHandles/disabled/
// handleOpacity/handleSize/keySupport/maxSize/outerImage/touchSupport were dropped entirely (not
// just defaulted): each was either dead on arrival (keySupport - no keyboard-nudge support was ever
// ported into this vendored copy), gated a whole branch behind a falsy sentinel (null/0/[0,0]/
// false) that, once unoverridable, could never turn truthy again (touchSupport's null - once it
// could never be true/false instead, JcropComponent's own #init() always fell through to
// feature-detecting the window, so the override check itself was dead too), or - once its
// DefaultedOptions default became the only value it could ever hold - ended up read only by code
// that's now static markup instead (border/handle opacity and classes, which per-side/per-corner
// divs even exist to begin with, and - for createDragbars/createHandles specifically - which of
// them get their mousedown/touchstart bound directly in the template now instead of via a runtime
// loop; see jcrop.component.html/.scss).
export interface JcropOptions {
  boxHeight?: number;
  boxWidth?: number;
  minSize?: number[];
  setSelect?: number[];
  trueSize?: number[];
}

// The subset of options JcropComponent's #init() always supplies a value for (in its own default
// object literal, spread into #options before opt) - everything else on JcropOptions
// (setSelect/trueSize) is genuinely optional at runtime, read only behind an
// `options.hasOwnProperty(...)` check.
interface DefaultedOptions {
  bgOpacity: number;
  boundary: number;
  boxHeight: number;
  boxWidth: number;
  minSelect: number[];
  minSize: number[];
}

export type InternalOptions = DefaultedOptions & JcropOptions;

export type Point = [number, number];

// A native MouseEvent/TouchEvent, widened with a writable pageX/pageY: JcropComponent's own
// #touchCfilter() copies the active touch's page coordinates onto the event itself so mouseAbs() can
// read event.pageX/pageY the same way regardless of whether the drag started from a mouse or touch
// listener - TouchEvent doesn't carry its own pageX/pageY (only the individual Touch entries in its
// touch lists do).
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
// createDragger/createTouchDragger directly. Kept independent of JcropComponent's own touch handling
// by taking its touch-specific bit (how to build a touch-drag handler) as a plain function rather
// than depending on that state directly. `coords` is taken as the real collaborator object it is (not
// an individually wrapped getter), since by the time Selection needs it, it already exists and never
// gets replaced. Doesn't hold a DOM reference to #sel/#img2 at all any more - every style it used to
// set on them directly (display, opacity, position, size) is a JcropComponent signal instead, set
// through the callbacks below.
export class Selection {
  #awake: boolean | undefined;

  constructor(
    private readonly bgopacity: number,
    private readonly createDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly createTouchDragger: (ord: DragMode) => (e: JcropMouseEvent) => void,
    private readonly coords: Coords,
    private readonly notifySelect: (crop: JcropCrop & {x2: number; y2: number}) => void,
    // Handle/dragbar visibility is a JcropComponent signal ([style.display] on the #hdlHolder
    // template element), not a style this class sets directly any more.
    private readonly setHandlesVisible: (visible: boolean) => void,
    // #sel's own display and #workingImg's opacity - these only change once per drag gesture
    // (awake/asleep, dimmed/undimmed), not on every frame.
    private readonly setSelVisible: (visible: boolean) => void,
    private readonly setImgOpacity: (opacity: number) => void,
    // #img2's and #sel's own left/top (plus #sel's height/width) - unlike the above, these change on
    // every drag frame, in #moveto()/#resize() below, but that's no reason they can't be signals too.
    private readonly setImg2Position: (x: number, y: number) => void,
    private readonly setSelPosition: (x: number, y: number) => void,
    private readonly setSelSize: (w: number, h: number) => void,
  ) {
    this.setHandlesVisible(false);
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
    this.setHandlesVisible(false);
    this.setSelVisible(false);

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
    this.setImg2Position(-x, -y);
    this.setSelPosition(x, y);
  }

  #resize(w: number, h: number): void {
    this.setSelSize(Math.round(w), Math.round(h));
  }

  #updateVisible(select?: boolean): void {
    if (this.#awake) {
      this.update(select);
    }
  }

  #setBgOpacity(opacity: number, force?: boolean): void {
    if (!this.#awake && !force) return;
    this.setImgOpacity(opacity);
  }

  #show(): void {
    this.setSelVisible(true);

    this.#setBgOpacity(this.bgopacity, true);

    this.#awake = true;
  }
}
