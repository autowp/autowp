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
 * The plugin's own orchestrating factory function (constructing and wiring up Coords, driving the
 * drag/resize state machine) now lives directly on JcropComponent - there's only ever one consumer,
 * so a separate "Jcrop instance" object was pure indirection. The document-level drag-tracking state,
 * touch handling, and selection-box bookkeeping (what used to be separate Tracker, Touch, and
 * Selection classes) live there too now, for the same reason: every collaborator each one needed
 * (mouseAbs/touchCfilter/notifySelectionSettled/setZIndex/getPos/startDragMode/setDocOffset/
 * onNewSelection/setHandlesVisible/setSelVisible/setImgOpacity/setImg2Position/setSelPosition/
 * setSelSize) was already a closure over JcropComponent itself, and none of them held a DOM
 * reference of their own any more either - so all three classes were pure indirection. What remains
 * here is Coords, the one collaborator class that actually owns a distinct piece of state, plus the
 * option types and small DOM helpers it and JcropComponent both need. The default option values
 * themselves (what used to be a `defaults` constant here) live directly in JcropComponent's
 * onLoad() now, for the same reason - it was the only consumer.
 */

export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

// The only options JcropComponent (the sole consumer) ever actually varies per crop - boxHeight/
// boxWidth (the fitted display box size) and minSize (the minSize input) - so, unlike the vendored
// plugin's original options object, all three are required rather than optional: onLoad() always
// provides a fresh value for each, in its own #options field initializer.
// addClass/aspectRatio/bgOpacity/borderOpacity/boundary/createBorders/createDragbars/createHandles/
// disabled/handleOpacity/handleSize/keySupport/maxSize/minSelect/outerImage/setSelect/touchSupport/
// trueSize were dropped entirely (not just defaulted): each was either dead on arrival (keySupport -
// no keyboard-nudge support was ever ported into this vendored copy), gated a whole branch behind a
// falsy sentinel (null/0/[0,0]/false) that, once unoverridable, could never turn truthy again
// (touchSupport's null - once it could never be true/false instead, JcropComponent's own constructor
// always fell through to feature-detecting the window, so the override check itself was dead too),
// once its fixed default became the only value it could ever hold, ended up read only by code that's
// now static markup instead (border/handle opacity and classes, which per-side/per-corner divs even
// exist to begin with, and - for createDragbars/createHandles specifically - which of them get their
// mousedown/touchstart bound directly in the template now instead of via a runtime loop; see
// jcrop.component.html/.scss), or - bgOpacity/boundary/minSelect/setSelect/trueSize - was never
// genuinely variable to begin with, so JcropComponent just uses a fixed value or an already-in-scope
// local variable directly now (#selectionUpdate()'s bgopacity, onLoad()'s tracker sizing,
// #doneSelect()'s minSelect check, and onLoad()'s own #setSelect()/xscale/yscale calls respectively)
// instead of routing any of them through #options at all.
export interface InternalOptions {
  boxHeight: number;
  boxWidth: number;
  minSize: number[];
}

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
// #options, which JcropComponent's own onLoad() sets once per crop and never mutates afterward) and
// `xscale`/`yscale` (recomputed by #presize()/onLoad() outside this class) do change, so those are
// read live through the two getters instead of copied in once. xmin/ymin, by contrast, are written
// from outside (onLoad()) but read only here, so they're owned outright as private state with a
// setter.
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
