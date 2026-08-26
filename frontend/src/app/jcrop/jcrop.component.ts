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
 * JcropComponent below is an Angular reimplementation of the vendored jquery.Jcrop.js plugin above -
 * image crop selection UI supporting a freehand drag to select, resizing via corner/edge handles, and
 * moving an existing selection by dragging it. JcropCrop and CropSummary/cropSummary() are its shared
 * public API, also imported directly by the three pages that embed JcropComponent for cropping
 * (upload/crop, moder/pictures/item/crop, moder/pictures/item/area): JcropCrop to type the crop rect
 * they read back out via (cropChange) and pass back in via [initialCrop], cropSummary() for their own
 * aspect/resolution readout next to the crop UI.
 */

import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  ElementRef,
  inject,
  input,
  output,
  signal,
  untracked,
  viewChild,
  ViewEncapsulation,
} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

export interface CropSummary {
  aspect: string;
  resolution: string;
}

// "N:M" aspect-ratio text (e.g. "4:3") normalized to a width of 4 - used only by the three pages that
// embed JcropComponent, for their own aspect/resolution readout, not by JcropComponent itself.
export function cropSummary(crop: JcropCrop): CropSummary {
  const pw = 4;
  const ph = Math.round(((pw * crop.h) / crop.w) * 10) / 10;

  return {
    aspect: `${pw}:${ph}`,
    resolution: `${Math.round(crop.w)}×${Math.round(crop.h)}`,
  };
}

// #coordsFixed (below) returns a JcropCrop plus its own bottom-right corner (x2/y2) - #getCoordsCorner
// and #resizeMove need the raw corner, not just the box it bounds.
type FixedCrop = JcropCrop & {x2: number; y2: number};

// #mouseAbs() below is the sole reader of pageX/pageY off either kind.
type JcropMouseEvent = MouseEvent | TouchEvent;

// TouchEvent is the only one with a changedTouches property - used to pick pageX/pageY's source in
// #mouseAbs(), and to reject a stray event of the wrong kind (e.g. a touch brushing the screen
// mid mouse-drag on a hybrid device) in onDocumentMove()/onDocumentEnd().
function isTouchEvent(e: JcropMouseEvent): e is TouchEvent {
  return 'changedTouches' in e;
}

type Point = [number, number];

// oppLockCorners below only ever maps onto a diagonal, so both are typed narrower than the full
// Ordinal union below.
type Corner = 'ne' | 'nw' | 'se' | 'sw';

// A handle/dragbar position, or the drag mode #dragMode can be active with - 'move' alongside the 8
// ordinals rather than a separate type, since #dragMode is exactly this union (or null, at rest).
type Ordinal = 'e' | 'n' | 'ne' | 'nw' | 's' | 'se' | 'sw' | 'w';
type DragMode = 'move' | Ordinal;

// The corner diagonally opposite a dragged edge/corner - what a resize anchors on, so it grows or
// shrinks from the edge/corner actually being dragged rather than the selection's center.
const oppLockCorners: Record<Ordinal, Corner> = {
  e: 'nw',
  n: 'sw',
  ne: 'sw',
  nw: 'se',
  s: 'nw',
  se: 'nw',
  sw: 'ne',
  w: 'ne',
};

// Normalizes a selection rect's corners so x1/y1 is always top-left and x2/y2 bottom-right, regardless
// of which corner was actually dragged.
function flipCoords(x1: number, y1: number, x2: number, y2: number): [number, number, number, number] {
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

function makeObj(a: [number, number, number, number]): FixedCrop {
  return {
    h: a[3] - a[1],
    w: a[2] - a[0],
    x: a[0],
    x2: a[2],
    y: a[1],
    y2: a[3],
  };
}

// Normalizes value into [min, max]. #coordsFixed's own bounds pass uses slideToBound() below instead,
// since it shifts a *pair* of coordinates together to preserve the selection's size - this only
// clamps one independent value (a point's position, or how far a move drag may shift the whole
// selection).
function clamp(value: number, min: number, max: number): number {
  if (value < min) return min;
  if (value > max) return max;
  return value;
}

// Shifts [a, b] by the same delta so probe lands exactly on bound, keeping b-a (the selection's size)
// intact - unlike clamp() above, which would squash the pair instead of sliding it.
function slideToBound(a: number, b: number, probe: number, bound: number): [number, number] {
  const delta = probe - bound;
  return [a - delta, b - delta];
}

// z-index is derived from array position plus a shared base, so the dragbar/handle ranges are
// provably non-overlapping.
const dragbarOrdinals: Ordinal[] = ['n', 's', 'e', 'w'];
const handleOrdinals: Ordinal[] = ['n', 's', 'e', 'w', 'nw', 'ne', 'se', 'sw'];
const DRAGBAR_Z_BASE = 370;
const HANDLE_Z_BASE = DRAGBAR_Z_BASE + dragbarOrdinals.length;

// Selection-box margin (in CSS pixels) and the dimmed background opacity while a selection is
// awake - see trackerHeight/trackerWidth and imgOpacity below.
const BOUNDARY = 2;
const BG_OPACITY = 0.6;

@Component({
  selector: 'app-jcrop',
  templateUrl: './jcrop.component.html',
  styleUrl: './jcrop.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  // .jcrop-* selectors in jcrop.component.scss are unscoped, so this stays None rather than the
  // Angular default - switching to emulated encapsulation hasn't been verified safe.
  // eslint-disable-next-line @angular-eslint/use-component-view-encapsulation
  encapsulation: ViewEncapsulation.None,
  // Permanently bound (Angular can't attach/detach a document listener conditionally) and forward
  // every event unconditionally - onDocumentMove()/onDocumentEnd() gate on whether a drag is actually
  // in progress. onDocumentTouchStart is unconditional by design (see its own comment).
  host: {
    '(document:mousemove)': 'onDocumentMove($event)',
    '(document:mouseup)': 'onDocumentEnd($event)',
    '(document:touchend)': 'onDocumentEnd($event)',
    '(document:touchmove)': 'onDocumentMove($event)',
    '(document:touchstart)': 'onDocumentTouchStart($event)',
  },
  preserveWhitespaces: false,
})
export class JcropComponent {
  readonly #elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  readonly #window = browserWindow();
  // Whether this device supports touch - computed once here (not in the constructor's own init
  // effect() below) since it only ever needs #window above.
  readonly #touchSupport: boolean;

  // The <img> box size (whole CSS pixels) that fits pictureWidth/pictureHeight inside the real page
  // container at its current width, preserving aspect ratio. displayWidth/displayHeight are thin
  // per-axis views onto this so callers (including the template) don't have to destructure a pair.
  readonly #display = computed(() => {
    const win = this.#window;
    if (!win) {
      return {height: 0, width: 0};
    }

    // Reads the container's own width, not this component's host element (which has no padding of
    // its own).
    const container = this.#elementRef.nativeElement.parentElement;
    if (!container) {
      return {height: 0, width: 0};
    }

    const pictureWidth = this.pictureWidth();
    const pictureHeight = this.pictureHeight();

    const styles = win.getComputedStyle(container, null);
    const containerWidth =
      container.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight) || 1;

    const scale = pictureWidth / containerWidth;
    return {
      height: Math.round(pictureHeight / scale),
      width: Math.round(pictureWidth / scale),
    };
  });
  protected readonly displayWidth = computed(() => this.#display().width);
  protected readonly displayHeight = computed(() => this.#display().height);

  // Scale factor between pictureWidth/pictureHeight (full resolution) and displayWidth/displayHeight
  // (on-screen size).
  readonly #xscale = computed(() => this.pictureWidth() / this.displayWidth());
  readonly #yscale = computed(() => this.pictureHeight() / this.displayHeight());

  // The selection rectangle's pressed/current corners. Signals so #coordsFixed below (and the
  // selection/preview position/size signals derived from it) can be computed() rather than something
  // a method has to remember to .set() on every drag frame.
  readonly #x1 = signal(0);
  readonly #x2 = signal(0);
  readonly #y1 = signal(0);
  readonly #y2 = signal(0);

  readonly src = input.required<string>();
  readonly pictureWidth = input.required<number>();
  readonly pictureHeight = input.required<number>();
  readonly initialCrop = input<JcropCrop>();
  readonly minSize = input.required<[number, number]>();

  readonly cropChange = output<JcropCrop>();

  private readonly workingImgRef = viewChild.required<ElementRef<HTMLImageElement>>('workingImg');

  // z-index derived from position in dragbarOrdinals/handleOrdinals (plus a shared base) rather than
  // hand-typed per ordinal.
  protected readonly dragbars = dragbarOrdinals.map((ord, i) => ({ord, zIndex: DRAGBAR_Z_BASE + i}));
  protected readonly handles = handleOrdinals.map((ord, i) => ({ord, zIndex: HANDLE_Z_BASE + i}));

  constructor() {
    // Feature-detects the window. #window can be null during SSR, but touch support is meaningless
    // there anyway, so false is a harmless fallback.
    this.#touchSupport = this.#window
      ? 'ontouchstart' in this.#window || this.#window.navigator.maxTouchPoints > 0
      : false;

    // Reacts only to pictureWidth/pictureHeight (a new picture is what should reset the selection,
    // not just a new initialCrop/minSize for the current one) - everything else is read via
    // untracked() so it doesn't also trigger: initialCrop especially, since a host page that feeds
    // cropChange back in as [initialCrop] (e.g. moder/pictures/item/area) would otherwise re-fire
    // this on every drag frame.
    effect(() => {
      const pictureWidth = this.pictureWidth();
      const pictureHeight = this.pictureHeight();

      untracked(() => {
        const initial = this.initialCrop() ?? {h: pictureHeight, w: pictureWidth, x: 0, y: 0};

        this.#initialized = true;

        this.#setSelect([initial.x, initial.y, initial.x + initial.w, initial.y + initial.h]);
        // Rounds x1/y1/x2/y2 to whole pixels in case the min-size clamp in #coordsFixed left them
        // fractional - #setSelect() above doesn't do that rounding itself.
        this.#selectionRefresh();
      });
    });
  }

  // Read directly by .jcrop-handles-holder's [style.display] binding (jcrop.component.html).
  protected readonly handlesVisible = signal(false);

  // Which drag gesture (if any) is active - null at rest, a DragMode while resizing/moving. The
  // freehand "drag a new box" gesture deliberately never sets this (see trackerCursor below).
  // onDocumentEnd() always resets it; onTrackerStart() also resets it defensively, in case a previous
  // touch gesture was interrupted mid-drag with no touchcancel to catch it.
  readonly #dragMode = signal<DragMode | null>(null);

  // 'crosshair' at rest and during a freehand drag, 'move' while dragging the selection, or an
  // ordinal's own '<ordinal>-resize' while resizing from that edge/corner.
  protected readonly trackerCursor = computed(() => {
    const mode = this.#dragMode();
    if (!mode) return 'crosshair';
    return mode === 'move' ? mode : `${mode}-resize`;
  });

  // 290 at rest, 450 while a drag from #tracker is in progress.
  protected readonly trackerZIndex = computed(() => (this.#trackerBtndown() ? 450 : 290));

  // displayWidth/displayHeight plus the fixed BOUNDARY margin.
  protected readonly trackerHeight = computed(() => this.displayHeight() + BOUNDARY * 2);
  protected readonly trackerWidth = computed(() => this.displayWidth() + BOUNDARY * 2);
  // Always -BOUNDARY, regardless of picture/container size.
  protected readonly trackerOffset = -BOUNDARY;

  // Negated top-left corner, so the full-size preview image shifts under the selection box by exactly
  // its offset.
  protected readonly img2Left = computed(() => -this.#coordsFixed().x);
  protected readonly img2Top = computed(() => -this.#coordsFixed().y);

  protected readonly selLeft = computed(() => this.#coordsFixed().x);
  protected readonly selTop = computed(() => this.#coordsFixed().y);
  protected readonly selHeight = computed(() => Math.round(this.#coordsFixed().h));
  protected readonly selWidth = computed(() => Math.round(this.#coordsFixed().w));

  // #workingImg's opacity - dimmed to BG_OPACITY while a selection is awake, restored to fully
  // opaque on release - a pure derivation of selectionAwake below.
  protected readonly imgOpacity = computed(() => (this.selectionAwake() ? BG_OPACITY : 1));

  // One-shot: set true by the constructor's own init effect() and never touched again - guards
  // selectAll() against running before that has happened.
  #initialized = false;
  // True from the first genuine selection until onDocumentEnd() releases it. Not #private since the
  // template's own [style.display] binding on #sel (jcrop.component.html) reads it directly too.
  protected readonly selectionAwake = signal(false);
  // Whether a drag is active, and whether it started from touch - which gesture kind is active isn't
  // stored here, #dragMode() above already says (see onDocumentMove()'s own comment). The on*()
  // methods below are always reachable (no conditional attach/detach), so they gate on
  // #trackerBtndown/#trackerIstouch instead of relying on not being called while no drag is active.
  readonly #trackerBtndown = signal(false);
  #trackerIstouch = false;
  // The #coordsFixed() snapshot onDragStart() below took at its own gesture's start, reused for every
  // onDocumentMove() event of that gesture - the opposite corner it locks against must stay put for
  // the whole drag. `!` since it's only ever read mid-gesture, after onDragStart() has assigned it.
  #dragStartFc!: FixedCrop;
  // The ordinal onDragStart() below is resizing from. Also doubles as onDocumentMove()'s own "is this
  // a resize gesture" test, since #resizeMove's switch only ever reads it while #dragMode() already
  // guarantees a resize is active.
  #dragStartOrd!: Ordinal;
  // Pointer position #selectionMove last saw during an active move gesture - unlike #dragStartFc/
  // #dragStartOrd above, updated on every move event, not just once at gesture start.
  #moveLastPos!: Point;

  // Called by the host page's own "select all" button via viewChild() - each crop dialog keeps that
  // button in its own layout, since positioning differs per page.
  public selectAll(): void {
    if (!this.#initialized) return;
    this.#setSelect([0, 0, this.pictureWidth(), this.pictureHeight()]);
  }

  // Emits the current selection in picture-pixel space. Pulled out since the same chain repeats at
  // both call sites below.
  #emitSelect(): void {
    const c = this.#coordsFixed();
    const xscale = this.#xscale();
    const yscale = this.#yscale();
    // Coords already clamps against [0, boundx]/[0, boundy] in scaled space, but rounding through
    // xscale/yscale can still leave a hair of negative slop after a drag to the top/left edge - guard
    // against saving that.
    const x = Math.max(0, c.x * xscale);
    const y = Math.max(0, c.y * yscale);
    this.cropChange.emit({h: c.h * yscale, w: c.w * xscale, x, y});
  }

  // Shared by onTrackerStart()/onSelectionTrackerStart()/onDragStart() below - #initialized guards
  // against firing before the constructor's own effect() has run; touch is rejected on a device
  // #touchSupport says can't generate one.
  #canStartGesture(touch: boolean): boolean {
    return this.#initialized && (!touch || this.#touchSupport);
  }

  // Called directly by onDocumentMove() for onTrackerStart()'s own freehand gesture.
  #newSelectionMove(e: JcropMouseEvent): void {
    this.#setCoordsCurrent(this.#mouseAbs(e));
    this.selectionAwake.set(true);
  }

  // Bound on #tracker (jcrop.component.html), merged from mousedown/touchstart into one handler.
  protected onTrackerStart(e: JcropMouseEvent): void {
    const touch = isTouchEvent(e);
    if (!this.#canStartGesture(touch)) return;

    this.handlesVisible.set(false);
    this.#dragMode.set(null);
    const pos = this.#mouseAbs(e);
    this.#setCoordsPressed(pos);
    this.selectionAwake.set(true);
    this.#trackerBtndown.set(true);
    this.#trackerIstouch = touch;

    e.stopPropagation();
    e.preventDefault();
  }

  // Called directly by onDocumentMove() for onSelectionTrackerStart()'s own move gesture.
  #selectionMove(e: JcropMouseEvent): void {
    const pos = this.#mouseAbs(e);
    // Clamps the move so the selection stays in bounds - the same two-sided clamp() #rebound() does
    // for a single point, applied here to how far the whole selection may shift.
    const ox = clamp(pos[0] - this.#moveLastPos[0], -this.#x1(), this.displayWidth() - this.#x2());
    const oy = clamp(pos[1] - this.#moveLastPos[1], -this.#y1(), this.displayHeight() - this.#y2());
    this.#x1.update((v) => v + ox);
    this.#x2.update((v) => v + ox);
    this.#y1.update((v) => v + oy);
    this.#y2.update((v) => v + oy);
    this.#moveLastPos = pos;

    this.selectionAwake.set(true);
  }

  protected onSelectionTrackerStart(e: JcropMouseEvent): void {
    const touch = isTouchEvent(e);
    if (!this.#canStartGesture(touch)) return;

    this.#dragMode.set('move');

    this.#moveLastPos = this.#mouseAbs(e);
    this.#trackerBtndown.set(true);
    this.#trackerIstouch = touch;

    e.stopPropagation();
    e.preventDefault();
  }

  // Called directly by onDocumentMove() for a resize gesture.
  #resizeMove(e: JcropMouseEvent): void {
    const pos = this.#mouseAbs(e);
    switch (this.#dragStartOrd) {
      case 'e':
      case 'w':
        pos[1] = this.#dragStartFc.y2;
        break;
      case 'n':
      case 's':
        pos[0] = this.#dragStartFc.x2;
        break;
    }
    this.#setCoordsCurrent(pos);
    this.selectionAwake.set(true);
  }

  // Bound on each of the 12 handle/dragbar elements (jcrop.component.html), passing its own fixed
  // ordinal.
  protected onDragStart(ord: Ordinal, e: JcropMouseEvent): void {
    const touch = isTouchEvent(e);
    if (!this.#canStartGesture(touch)) return;

    this.#dragMode.set(ord);

    this.#dragStartOrd = ord;
    this.#dragStartFc = this.#coordsFixed();
    const opp = oppLockCorners[ord];
    const opc = this.#getCoordsCorner(oppLockCorners[opp]);

    this.#setCoordsPressed(this.#getCoordsCorner(opp));
    this.#setCoordsCurrent(opc);

    this.#trackerBtndown.set(true);
    this.#trackerIstouch = touch;

    e.stopPropagation();
    e.preventDefault();
  }

  // Merged from mousemove/touchmove into one handler - dispatches to whichever gesture #dragMode()
  // says is active once #trackerBtndown() confirms one is: null means onTrackerStart()'s freehand
  // gesture (it never sets #dragMode - see its own comment), 'move' means onSelectionTrackerStart's,
  // any Ordinal means a resize.
  protected onDocumentMove(e: JcropMouseEvent): void {
    if (!this.#initialized || !this.#trackerBtndown() || isTouchEvent(e) !== this.#trackerIstouch) return;

    const mode = this.#dragMode();
    if (mode === null) {
      this.#newSelectionMove(e);
      return;
    }
    if (mode === 'move') {
      this.#selectionMove(e);
      return;
    }

    this.#resizeMove(e);
  }

  protected onDocumentEnd(e: JcropMouseEvent): void {
    if (!this.#initialized || !this.#trackerBtndown() || isTouchEvent(e) !== this.#trackerIstouch) return;

    e.preventDefault();
    e.stopPropagation();

    this.#trackerBtndown.set(false);

    const c = this.#coordsFixed();
    // [0, 0] is the minimum size below which nothing counts as a real selection. selectionAwake
    // always mirrors c.w > 0 && c.h > 0 here: a resize/move can only start on an already-awake
    // selection, and a plain move never changes size.
    if (c.w > 0 && c.h > 0) {
      this.handlesVisible.set(true);
      this.#selectionRefresh();
      this.#emitSelect();
    } else {
      this.handlesVisible.set(false);
      this.selectionAwake.set(false);
    }
    this.#dragMode.set(null);
  }

  // iOS5 touch hack. e.currentTarget is always `document` here (not delegated), so the `instanceof
  // Element` check below never passes and stopPropagation() never runs - preserved as dead-but-safe
  // rather than "fixed" to e.target, which would be a behavior change.
  protected onDocumentTouchStart(e: TouchEvent): void {
    const target = e.currentTarget;
    if (target instanceof Element && target.classList.contains('jcrop-tracker')) e.stopPropagation();
  }

  #setCoordsPressed(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x1.set(rebounded[0]);
    this.#x2.set(rebounded[0]);
    this.#y1.set(rebounded[1]);
    this.#y2.set(rebounded[1]);
  }

  #setCoordsCurrent(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x2.set(rebounded[0]);
    this.#y2.set(rebounded[1]);
  }

  // The four cases below (ne/nw/se/sw) are really one rule, not four: east picks x2 over x, south
  // picks y2 over y - a switch would just spell that same rule out longhand per corner.
  #getCoordsCorner(ord: Corner): Point {
    const c = this.#coordsFixed();
    return [ord.includes('e') ? c.x2 : c.x, ord.includes('s') ? c.y2 : c.y];
  }

  #rebound(p: Point): Point {
    const px0 = clamp(p[0], 0, this.displayWidth());
    const py0 = clamp(p[1], 0, this.displayHeight());

    return [Math.round(px0), Math.round(py0)];
  }

  // A pure derivation of #x1/#y1/#x2/#y2 - clamps a local working copy (never the signals themselves)
  // to the min-size and in-bounds constraints, then normalizes via flipCoords/makeObj. Callers persist
  // the clamp themselves via #setCoordsPressed()/#setCoordsCurrent(). Inherently branchy - splitting
  // it up to satisfy the complexity budget below would risk the geometry itself.
  // eslint-disable-next-line sonarjs/cognitive-complexity
  readonly #coordsFixed = computed<FixedCrop>(() => {
    let x1 = this.#x1(),
      x2 = this.#x2(),
      y1 = this.#y1(),
      y2 = this.#y2();
    const xsize = x2 - x1,
      ysize = y2 - y1;
    const xscale = this.#xscale(),
      yscale = this.#yscale();
    const [xmin, ymin] = this.minSize();
    const displayWidth = this.displayWidth();
    const displayHeight = this.displayHeight();

    if (ymin / yscale && Math.abs(ysize) < ymin / yscale) {
      y2 = ysize > 0 ? y1 + ymin / yscale : y1 - ymin / yscale;
    }
    if (xmin / xscale && Math.abs(xsize) < xmin / xscale) {
      x2 = xsize > 0 ? x1 + xmin / xscale : x1 - xmin / xscale;
    }

    if (x1 < 0) [x1, x2] = slideToBound(x1, x2, x1, 0);
    if (y1 < 0) [y1, y2] = slideToBound(y1, y2, y1, 0);
    if (x2 < 0) [x1, x2] = slideToBound(x1, x2, x2, 0);
    if (y2 < 0) [y1, y2] = slideToBound(y1, y2, y2, 0);
    if (x2 > displayWidth) [x1, x2] = slideToBound(x1, x2, x2, displayWidth);
    if (y2 > displayHeight) [y1, y2] = slideToBound(y1, y2, y2, displayHeight);
    if (x1 > displayWidth) [x1, x2] = slideToBound(x1, x2, x1, displayWidth);
    if (y1 > displayHeight) [y1, y2] = slideToBound(y1, y2, y1, displayHeight);

    return makeObj(flipCoords(x1, y1, x2, y2));
  });

  #setSelect(rect: number[]): void {
    this.#setCoordsPressed([(rect[0] ?? 0) / this.#xscale(), (rect[1] ?? 0) / this.#yscale()]);
    this.#setCoordsCurrent([(rect[2] ?? 0) / this.#xscale(), (rect[3] ?? 0) / this.#yscale()]);
    this.selectionAwake.set(true);
    this.#emitSelect();
    this.handlesVisible.set(true);
  }

  // Reads pageX/pageY off e, or off its first changed touch for a touch event. Reads #workingImg's
  // position fresh on every call, so it's never stale even if the image was hidden when the page
  // first loaded.
  #mouseAbs(e: JcropMouseEvent): Point {
    const src = isTouchEvent(e) ? e.changedTouches[0] : e;

    // #window is only null during SSR, where this component is never actually rendered - harmless
    // fallback for an unreachable branch.
    if (!this.#window) return [src.pageX, src.pageY];

    const rect = this.workingImgRef().nativeElement.getBoundingClientRect();
    const offsetX = rect.left + this.#window.scrollX;
    const offsetY = rect.top + this.#window.scrollY;

    return [src.pageX - offsetX, src.pageY - offsetY];
  }

  #selectionRefresh(): void {
    const c = this.#coordsFixed();

    this.#setCoordsPressed([c.x, c.y]);
    this.#setCoordsCurrent([c.x2, c.y2]);
  }
}
