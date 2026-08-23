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
  viewChild,
  ViewEncapsulation,
} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

import type {JcropCrop} from './Jcrop';

// Every option the vendored plugin's own options object supported
// (addClass/aspectRatio/bgOpacity/borderOpacity/boundary/boxHeight/boxWidth/createBorders/
// createDragbars/createHandles/disabled/handleOpacity/handleSize/keySupport/maxSize/minSelect/
// minSize/outerImage/setSelect/touchSupport/trueSize) is gone from JcropComponent as a distinct
// "options" concept now: each is either dead code that was dropped entirely (keySupport - no
// keyboard-nudge support was ever ported into this vendored copy), gated a whole branch behind a
// falsy sentinel (null/0/[0,0]/false) that, once unoverridable, could never turn truthy again
// (touchSupport's null - once it could never be true/false instead, JcropComponent's own
// constructor always fell through to feature-detecting the window, so the override check itself
// was dead too), ended up read only by code that's now static markup instead (border/handle
// opacity and classes, which per-side/per-corner divs even exist to begin with, and - for
// createDragbars/createHandles specifically - which of them get their mousedown/touchstart bound
// directly in the template now instead of via a runtime loop; see jcrop.component.html/.scss), was
// never genuinely variable to begin with so it's a fixed value at its one use site now
// (bgOpacity/boundary/minSelect/setSelect/trueSize - #selectionUpdate()'s bgopacity, the
// trackerHeight/trackerWidth computed()s' BOUNDARY margin, #doneSelect()'s minSelect check,
// onLoad()'s own #setSelect() call, and the #xscale/#yscale computed()s respectively), or
// (boxHeight/boxWidth/minSize, the three that do vary per crop) is read straight from the
// displayWidth/displayHeight computed()s/the minSize input directly now instead of routed through a
// shared #options object - every value it ever held was derivable from a component input (or, for
// boxHeight/boxWidth, the page container's own layout) anyway, so the object was pure indirection.

// A native MouseEvent/TouchEvent, widened with a writable pageX/pageY: touchCfilter() below copies
// the active touch's page coordinates onto the event itself so #mouseAbs() can read event.pageX/
// pageY the same way regardless of whether the drag started from a mouse or touch listener -
// TouchEvent doesn't carry its own pageX/pageY (only the individual Touch entries in its touch
// lists do).
type JcropMouseEvent = (MouseEvent | TouchEvent) & {pageX?: number; pageY?: number};

type Point = [number, number];

// oppLockCorner (below) only ever maps onto a diagonal - the corner opposite the dragged
// edge/corner, which #getCoordsCorner (on the class) then reads off - so both are typed to this
// narrower union rather than the full Ordinal union below.
type Corner = 'ne' | 'nw' | 'se' | 'sw';

type PositionCallback = (pos: Point) => void;

// A handle/border/dragbar position, or the drag mode passed around while resizing/moving the
// selection - 'move' alongside the 8 ordinals rather than a separate type, since #startDragMode's
// mode parameter is exactly this union and every ordinal-only site narrows it via `mode !== 'move'`.
type Ordinal = 'e' | 'n' | 'ne' | 'nw' | 's' | 'se' | 'sw' | 'w';
type DragMode = 'move' | Ordinal;

// Copies the active touch's page coordinates onto the event itself so #mouseAbs() (below, on the
// class) can read event.pageX/pageY the same way regardless of whether the drag started from a
// mouse or touch listener - see the JcropMouseEvent comment above. What used to be Touch's own
// cfilter() (Jcrop.ts). A plain function rather than a class method since it only ever touches its
// own parameter, never this component's own state.
function touchCfilter(e: JcropMouseEvent): JcropMouseEvent {
  const touch = (e as TouchEvent).changedTouches[0];
  e.pageX = touch.pageX;
  e.pageY = touch.pageY;
  return e;
}

// The corner diagonally opposite a dragged edge/corner - what a resize drag anchors on, so it grows
// or shrinks from the edge/corner actually being dragged rather than the selection's center. A plain
// function for the same reason touchCfilter above is.
function oppLockCorner(ord: Ordinal): Corner {
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

// Normalizes a selection rect's corners so x1/y1 is always the top-left and x2/y2 the bottom-right,
// regardless of which corner was actually dragged (dragging the top-left corner past the bottom-right
// one, for instance, would otherwise leave x1 > x2). A plain function for the same reason
// touchCfilter above is - it only operates on its own four parameters.
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

// Builds the JcropCrop (plus x2/y2) shape #getCoordsFixed() (below, on the class) returns, from the
// already-normalized [x1, y1, x2, y2] tuple flipCoords produces. A plain function for the same
// reason flipCoords above is.
function makeObj(a: [number, number, number, number]): JcropCrop & {x2: number; y2: number} {
  return {
    h: a[3] - a[1],
    w: a[2] - a[0],
    x: a[0],
    x2: a[2],
    y: a[1],
    y2: a[3],
  };
}

// The ordinals rendered as dragbars/handles (jcrop.component.html) and the z-index each one gets -
// listed here as plain arrays, with z-index derived from array position plus a shared base, rather
// than hand-typed per ordinal, so the two ranges are provably non-overlapping by construction
// instead of by careful counting.
const dragbarOrdinals: Ordinal[] = ['n', 's', 'e', 'w'];
const handleOrdinals: Ordinal[] = ['n', 's', 'e', 'w', 'nw', 'ne', 'se', 'sw'];
const DRAGBAR_Z_BASE = 370;
const HANDLE_Z_BASE = DRAGBAR_Z_BASE + dragbarOrdinals.length;

// The vendored plugin's boundary and bgOpacity options - neither ever overridable via
// JcropOptions, so both are plain constants now instead of routed through #options.
const BOUNDARY = 2;
const BG_OPACITY = 0.6;

@Component({
  selector: 'app-jcrop',
  templateUrl: './jcrop.component.html',
  styleUrl: './jcrop.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  // Jcrop used to build its handles/borders/tracker/image overlay with raw document.createElement()
  // calls rather than this component's own template - Angular's emulated encapsulation only stamps
  // its scoping attribute onto elements it renders itself, so those .jcrop-* selectors would silently
  // match nothing under the default encapsulation. Every element is template-owned now (#workingImg
  // and #img2 were the last two built imperatively), so this could in principle switch back to
  // emulated encapsulation - left as None for now since nothing has verified that switch is safe
  // (e.g. any external/global CSS still expecting to reach .jcrop-* unscoped). Global scope here is
  // what angular.json's top-level `styles` array already gave this stylesheet before it moved here.
  // eslint-disable-next-line @angular-eslint/use-component-view-encapsulation
  encapsulation: ViewEncapsulation.None,
  // Nothing here calls document.addEventListener()/removeEventListener() itself to track an active
  // drag - Angular has no equivalent to attaching/detaching a document-level listener conditionally,
  // so these are permanently bound (from the moment this component is created, well before onLoad()
  // ever runs) and just forward every event unconditionally; the mouse/touch move/up/end on*()
  // methods below are what actually gate on whether a drag is in progress
  // (#trackerBtndown/#trackerIstouch). onDocumentTouchStart is the exception - it's unconditional by
  // design, same as it was as a plain document.addEventListener() call (see its own comment).
  host: {
    '(document:mousemove)': 'onDocumentMouseMove($event)',
    '(document:mouseup)': 'onDocumentMouseUp($event)',
    '(document:touchend)': 'onDocumentTouchEnd($event)',
    '(document:touchmove)': 'onDocumentTouchMove($event)',
    '(document:touchstart)': 'onDocumentTouchStart($event)',
  },
})
export class JcropComponent {
  readonly #elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  readonly #window = browserWindow();
  // Whether this device supports touch - what used to be a separate Touch class's own `support`
  // field (see Jcrop.ts): computed once here rather than in onLoad(), since (now that
  // #options.touchSupport, the one-time "explicit override" option, is gone) it needs nothing from
  // onLoad()/#options any more, just #window above.
  readonly #touchSupport: boolean;

  // The <img> box size (rounded to whole CSS pixels - the template binds #workingImg's own
  // height/width straight to these, and onLoad() below uses them as what used to be its own
  // boundx/boundy fields holding a copy of the same numbers) that fits pictureWidth/pictureHeight
  // inside the real page container (#elementRef's parentElement) at its current width, preserving
  // aspect ratio. Doesn't need #workingImg to have finished loading, since it only ever reads
  // pictureWidth/pictureHeight (component inputs) and the container's own layout, never anything off
  // the image itself - so computed()s rather than something only onLoad() could populate, recomputed
  // whenever pictureWidth/pictureHeight change (the container read itself isn't reactive - nothing
  // here tracks live container resizes - but doesn't need to be: it happens fresh on every
  // recomputation anyway). displayWidth/displayHeight are thin per-axis views onto #display so call
  // sites (including the template, which can't reach a #private field) don't all have to destructure
  // a {width, height} pair.
  readonly #display = computed(() => {
    const win = this.#window;
    if (!win) {
      return {height: 0, width: 0};
    }

    // The real page container this component's content used to sit directly inside, before this
    // component existed - reading its width (rather than this component's own host element, which
    // isn't styled with any padding of its own) keeps the responsive-fit math identical to what it
    // was when the three crop pages each duplicated it inline.
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

  // trueSize's own role (the true/full-resolution size, as opposed to displayWidth/displayHeight's
  // on-screen size, to compute the scale factor between the two) used to be a JcropOptions field
  // routed through #options - it never needed to be, since pictureWidth/pictureHeight are already
  // available directly, same as displayWidth/displayHeight, so this is a pure derivation of the two,
  // not runtime state onLoad() has to populate.
  readonly #xscale = computed(() => this.pictureWidth() / this.displayWidth());
  readonly #yscale = computed(() => this.pictureHeight() / this.displayHeight());

  // The selection rectangle's own pressed/current corner state (x1/y1/x2/y2) and everything derived
  // from it (min-size clamping, bounding to the image) - what used to be a separate Coords class's
  // own state (Jcrop.ts), folded in directly since nothing outside this component ever constructed
  // more than one instance, same reasoning as Tracker/Touch/Selection before it (see the "vendored
  // plugin's own single closure-based factory function" comment below). boundx/boundy/#coordXscale/
  // #coordYscale are a frozen snapshot of what the picture currently looks sized as - kept fresh by
  // #setCoordsBounds() (called by the constructor effect() below whenever displayWidth/displayHeight/
  // #xscale/#yscale change, independent of onLoad()/the image finishing loading) rather than read
  // live on every access, so an in-progress drag's own selection rect survives a bounds update
  // undisturbed; #coordXscale/#coordYscale are named apart from the #xscale/#yscale computed()s
  // above for exactly that reason - a snapshot, not the same live value. xmin/ymin are the current
  // minSize input in unscaled pixels, written from onLoad() (via #setCoordsLimits()) but read only by
  // #getCoordsFixed() below - all ten fields start at 0 (1 for the two scales, to avoid a division by
  // zero) since real values aren't available this early: required inputs like pictureWidth (which
  // displayWidth/etc. read, transitively) aren't set yet during field initialization - the
  // constructor effect() applies the real bounds/scale moments later, well before #initialized (set
  // at the end of onLoad()) lets anything actually read them back.
  #x1 = 0;
  #x2 = 0;
  #y1 = 0;
  #y2 = 0;
  #xmin = 0;
  #ymin = 0;
  #boundx = 0;
  #boundy = 0;
  #coordXscale = 1;
  #coordYscale = 1;

  readonly src = input.required<string>();
  readonly pictureWidth = input.required<number>();
  readonly pictureHeight = input.required<number>();
  readonly initialCrop = input<JcropCrop>();
  readonly minSize = input.required<[number, number]>();

  readonly cropChange = output<JcropCrop>();

  private readonly workingImgRef = viewChild.required<ElementRef<HTMLImageElement>>('workingImg');

  // Rendered with @for (jcrop.component.html) instead of one static element per ordinal - z-index is
  // derived from each ordinal's position in dragbarOrdinals/handleOrdinals above (plus a shared
  // base) rather than hand-typed, since it's the one thing genuinely unique per element, not
  // shareable via a class or derivable from the ordinal itself.
  protected readonly dragbars = dragbarOrdinals.map((ord, i) => ({ord, zIndex: DRAGBAR_Z_BASE + i}));
  protected readonly handles = handleOrdinals.map((ord, i) => ({ord, zIndex: HANDLE_Z_BASE + i}));

  constructor() {
    // Feature-detect the window (what used to be Touch's own constructor logic and the free
    // hasTouchSupport() helper in Jcrop.ts). #window can be null during SSR, but touch support is
    // meaningless there anyway, so false is a harmless fallback.
    this.#touchSupport = this.#window
      ? 'ontouchstart' in this.#window || this.#window.navigator.maxTouchPoints > 0
      : false;

    // Keeps boundx/boundy/#coordXscale/#coordYscale fresh whenever displayWidth/displayHeight/
    // #xscale/#yscale change, independent of onLoad()/the image finishing loading - same reasoning as
    // the #display comment above, just one step further downstream. #setCoordsBounds() rather than
    // also resetting x1/y1/x2/y2/xmin/ymin (what onLoad() itself used to do here, rebuilding a whole
    // new Coords) so an in-progress drag's selection rect and #setCoordsLimits() minimum survive the
    // update.
    effect(() => {
      this.#setCoordsBounds(this.displayWidth(), this.displayHeight(), this.#xscale(), this.#yscale());
    });
  }

  // Whether the resize handles/dragbars are shown - set directly by this component (#setSelect(),
  // #doneSelect(), #newSelection() below), and read directly by the .jcrop-handles-holder template
  // element's [style.display] binding instead of Jcrop imperatively setting that style itself.
  protected readonly handlesVisible = signal(false);

  // The #tracker template element's cursor - set directly via style manipulation in the vendored
  // original; onLoad() (the first thing to set it, right after #initialized) always sets 'crosshair'
  // first, so that's the signal's initial value too, matching what the element would otherwise
  // briefly render before onLoad() has ever run.
  protected readonly trackerCursor = signal('crosshair');

  // The #tracker template element's z-index - toggled between 290 (resting) and 450 (while a drag
  // started from it is in progress) by #activateTrackerHandlers()/#finishTrackerDrag() below.
  protected readonly trackerZIndex = signal(290);

  // The #tracker template element's height/width - unlike cursor/z-index above, pure derivations of
  // displayWidth/displayHeight (plus the fixed BOUNDARY margin), so computed() rather than a signal
  // some method has to remember to keep in sync.
  protected readonly trackerHeight = computed(() => this.displayHeight() + BOUNDARY * 2);
  protected readonly trackerWidth = computed(() => this.displayWidth() + BOUNDARY * 2);
  // #tracker's left/top - unlike height/width above, always -BOUNDARY regardless of picture/container
  // size, so a plain constant (both take the same value, hence one field for both) rather than a
  // signal.
  protected readonly trackerOffset = -BOUNDARY;

  // The #holder template element's height/width - exactly displayWidth/displayHeight (unlike
  // trackerHeight/trackerWidth above, no BOUNDARY margin added), so computed() straight off them.
  protected readonly holderHeight = computed(() => this.displayHeight());
  protected readonly holderWidth = computed(() => this.displayWidth());

  // #img2's height/width - the crop-preview <img> clipped inside #imgHolder (jcrop.component.html),
  // always the same size as #holder above, hence the same computed().
  protected readonly img2Height = computed(() => this.displayHeight());
  protected readonly img2Width = computed(() => this.displayWidth());

  // #img2's own left/top - driven by #selectionUpdate() below, on every drag frame.
  protected readonly img2Left = signal(0);
  protected readonly img2Top = signal(0);

  // #sel's own left/top/height/width - driven by #selectionUpdate() below, on every drag frame, the
  // same way img2Left/img2Top are.
  protected readonly selLeft = signal(0);
  protected readonly selTop = signal(0);
  protected readonly selHeight = signal(0);
  protected readonly selWidth = signal(0);

  // #sel's own display - whether the selection box (and everything clipped/nested inside it: borders,
  // handles, the #img2 crop-preview) is shown at all. Driven by #doneSelect()/#selectionUpdate()
  // below, false until the first selection - matching handlesVisible's own initial value - since #sel
  // would otherwise flash unstyled before onLoad() has run.
  protected readonly selVisible = signal(false);

  // #workingImg's opacity - dimmed while a selection is awake (#selectionUpdate()'s bgopacity),
  // restored to fully opaque on release. Same reasoning as selVisible: a state transition, not a
  // per-frame update, so a signal is safe. 1 (fully opaque) is #workingImg's state before any
  // selection exists yet, matching its default.
  protected readonly imgOpacity = signal(1);

  // Everything below is Jcrop's own former per-instance state - it used to belong to a separately
  // constructed Jcrop object, but that was pure indirection given this component is its only ever
  // consumer, so it was folded in directly (see the file-level comment in Jcrop.ts). Unlike the
  // x1/y1/x2/y2/etc. fields above (kept fresh by signals for this component's whole lifetime),
  // everything below is genuinely onLoad()-only: set once by onLoad(), when the template's own <img>
  // fires its load event - never in this component's own constructor, so TS's definite-assignment
  // analysis can't see it; #initialized guards selectAll() (the only externally-triggered entry
  // point) against running before that has happened - everything else here only ever runs from
  // inside onLoad() itself.
  #initialized = false;
  // Whether the selection box is awake (visible/tracked) - what used to be a separate Selection
  // class's own `#awake` field (see Jcrop.ts): true from the first #selectionUpdate() call after
  // construction or a release, until #doneSelect() sets it back to false on release.
  #selectionAwake: boolean | undefined;
  // Document-level drag-tracking state (what used to be a separate Tracker class - see Jcrop.ts):
  // whether a drag is currently active, whether it started from a touch, and the move/done callback
  // the caller currently has active (set via #activateTrackerHandlers(), one call per drag gesture:
  // #newSelection() for a fresh drag, #startDragMode() for resizing/moving an existing one). Unlike
  // the vendored original, the on*() methods below are always reachable (Angular has no equivalent
  // to conditionally attaching/detaching a document listener) and gate themselves on #trackerBtndown
  // (and, to ignore a stray event of the wrong kind - e.g. a touch brushing the screen mid mouse-drag
  // on a hybrid device - #trackerIstouch) rather than relying on not being called at all while no
  // drag is active.
  #trackerBtndown: boolean | undefined;
  #trackerIstouch: boolean | undefined;
  #trackerOnDone: PositionCallback = function () {};
  #trackerOnMove: PositionCallback = function () {};
  #docOffset!: Point;

  // Called by the host page's own "select all" button via viewChild() - the crop dialogs each keep
  // that button (and the aspect/resolution readout next to it) in their own template/layout rather
  // than have this component own them, since each page positions them differently (a modal footer
  // flex row vs. an inline block).
  public selectAll(): void {
    if (!this.#initialized) return;
    this.#setSelect([0, 0, this.pictureWidth(), this.pictureHeight()]);
  }

  // The vendored plugin's own single closure-based factory function, ported first to a class and
  // then merged directly onto this component: everything that used to be a local variable closed
  // over by every nested function is now a private field. Coords, the standalone module it built as
  // an IIFE (see Jcrop.ts), was the last of the plugin's modules to move here too (as the
  // x1/y1/x2/y2/etc. fields and #setCoordsBounds()/#setCoordsLimits()/etc. methods above/below): once
  // it needed its own bounds/scale driven directly by this component's own signals rather than being
  // handed pre-computed numbers once at construction, keeping it a separate class only added a layer
  // of indirection between the two. Tracker, Touch, and Selection - the plugin's other three modules -
  // never had a distinct piece of DOM/state of their own (once Selection stopped holding #sel/#img2
  // DOM references directly - see #selectionAwake above), just fields/methods closing over this
  // component's own collaborators, so all three were folded in directly instead of classes in
  // Jcrop.ts: Tracker as
  // #trackerBtndown/#trackerIstouch/#trackerOnMove/#trackerOnDone and the
  // #activateTrackerHandlers()/#finishTrackerDrag() methods below, Touch as #touchSupport and the
  // #createDragger() method below (touchCfilter() moved out as a plain function above - see there),
  // Selection as #selectionAwake and the
  // #selectionRefresh()/#selectionUpdate()/#setSelBgOpacity() methods below (#doneSelect()/
  // #selectionUpdate() themselves absorbed what used to be Selection's own release()/#show()).
  // #init() itself (what used to build/wire all of the above) was folded in here too - onLoad() was
  // its only caller.
  //
  // #doneSelect/#selectDrag below are field arrows rather than ordinary methods because each is
  // handed to #activateTrackerHandlers() by bare reference (stored as #trackerOnDone/#trackerOnMove
  // and invoked later, not called directly where passed) - an ordinary method read that way loses its
  // `this` binding the moment something else invokes it. A few other methods
  // (#getPos/#mouseAbs/#startDragMode/#createDragger) are field arrows too, left over from when
  // Selection/Touch needed them the same way - nothing still requires that of them, but nothing's
  // broken by it either.
  protected onLoad(): void {
    const pictureWidth = this.pictureWidth();
    const pictureHeight = this.pictureHeight();

    const initial = this.initialCrop() ?? {h: pictureHeight, w: pictureWidth, x: 0, y: 0};

    // #workingImg is a static template <img> now (jcrop.component.html), not a runtime clone of a
    // separate, hidden <img> - its (load) below is what used to be that other element's, and its
    // static styling (position/border/margin/padding/top/left/visibility, plus its height/width -
    // bound straight to displayWidth/displayHeight) is a plain template attribute instead of set
    // here. #holder's/#tracker's/#img2's own sizes don't need anything from onLoad() either, being
    // computed() straight off displayWidth/displayHeight themselves too (see holderHeight/
    // holderWidth, trackerHeight/trackerWidth/trackerOffset, and img2Height/img2Width above) - so
    // everything below reads displayWidth()/displayHeight() directly instead of measuring the DOM
    // element back or keeping its own boundx/boundy copy of the same numbers. boundx/boundy/
    // #coordXscale/#coordYscale are likewise already kept fresh by the constructor effect() above -
    // onLoad() only ever needs to (re)establish the selection itself for whatever picture just
    // loaded, via #setCoordsLimits()/#setSelect() below.

    // Matches what used to be Selection's own constructor (Jcrop.ts) resetting handle visibility on
    // construction - handlesVisible already starts false, but onLoad() (unlike a constructor) can run
    // more than once per component instance, so this still matters if a previous run left it true.
    this.handlesVisible.set(false);

    this.#docOffset = this.#getPos();

    this.#initialized = true;

    this.trackerCursor.set('crosshair');

    // setSelect's own role (the initial selection rect to apply once, on load) used to be a
    // JcropOptions field routed through #options, deleted after being consumed so it wouldn't reapply
    // - it never needed any of that, since this runs unconditionally anyway and `initial` (used to
    // build it) is already a plain local variable above, in this same onLoad() call.
    this.#setSelect([initial.x, initial.y, initial.x + initial.w, initial.y + initial.h]);
    this.#selectionRefresh();

    const minSize = this.minSize();
    this.#setCoordsLimits(minSize[0], minSize[1]);

    this.#selectionRefresh();
  }

  // Called wherever the vendored plugin used to call its configurable onSelect option - this is the
  // sole consumer JcropComponent ever passed for it, so it's a real method now instead of a callback
  // routed through #options (which never actually varied at runtime).
  #onSelect(crop: JcropCrop): void {
    // Coords already clamps against [0, boundx]/[0, boundy] in scaled space, but rounding through
    // xscale/yscale in unscale() can still leave a hair of negative slop after a drag to the
    // top/left edge - guard against saving that.
    this.cropChange.emit({...crop, x: Math.max(0, crop.x), y: Math.max(0, crop.y)});
  }

  // Bound directly on the #tracker/#selectionTracker template elements (jcrop.component.html)
  // instead of Jcrop wiring them up itself with addEventListener() - both are static, permanent
  // elements, so there's no need for imperative attach/detach. Each still needs the #initialized
  // guard: the template binds these unconditionally, from before onLoad() has ever run.
  protected onTrackerMouseDown(e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#newSelection(e);
  }

  protected onTrackerTouchStart(e: TouchEvent): void {
    if (!this.#initialized || !this.#touchSupport) return;
    this.#newSelection(touchCfilter(e));
  }

  protected onSelectionTrackerMouseDown(e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#createDragger('move')(e);
  }

  protected onSelectionTrackerTouchStart(e: TouchEvent): void {
    if (!this.#initialized || !this.#touchSupport) return;
    this.#createDragger('move', true)(e);
  }

  // Bound on each of the 12 static handle/dragbar template elements (jcrop.component.html), each
  // passing its own fixed ordinal directly into #createDragger() below.
  protected onDragMouseDown(ord: Ordinal, e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#createDragger(ord)(e);
  }

  protected onDragTouchStart(ord: Ordinal, e: TouchEvent): void {
    if (!this.#initialized || !this.#touchSupport) return;
    this.#createDragger(ord, true)(e);
  }

  protected onDocumentMouseMove(e: MouseEvent): void {
    if (!this.#initialized || !this.#trackerBtndown || this.#trackerIstouch) return;
    this.#trackerOnMove(this.#mouseAbs(e));
  }

  protected onDocumentMouseUp(e: MouseEvent): void {
    if (!this.#initialized || !this.#trackerBtndown || this.#trackerIstouch) return;
    this.#finishTrackerDrag(e);
  }

  protected onDocumentTouchMove(e: TouchEvent): void {
    if (!this.#initialized || !this.#trackerBtndown || !this.#trackerIstouch) return;
    this.#trackerOnMove(this.#mouseAbs(touchCfilter(e)));
  }

  protected onDocumentTouchEnd(e: TouchEvent): void {
    if (!this.#initialized || !this.#trackerBtndown || !this.#trackerIstouch) return;
    this.#finishTrackerDrag(touchCfilter(e));
  }

  // This is a hack for iOS5 to support drag/move touch functionality. Note that e.currentTarget is
  // always `document` here (Angular attaches this the same way a plain document.addEventListener()
  // call would - not delegated), so the `instanceof Element` check below never passes and
  // stopPropagation() never actually runs - jQuery's .hasClass() had the equivalent guard
  // (elem.nodeType === 1) built in, silently no-opping for a Document node rather than throwing;
  // preserved as dead-but-safe rather than "fixed" to e.target, since that would be a behavior change
  // from the original.
  protected onDocumentTouchStart(e: TouchEvent): void {
    const target = e.currentTarget;
    if (target instanceof Element && target.classList.contains('jcrop-tracker')) e.stopPropagation();
  }

  // What used to be a separate Coords class's own methods (Jcrop.ts), folded in directly for the
  // same reason Tracker/Touch/Selection were (see the "vendored plugin's own single closure-based
  // factory function" comment above) - operating on the x1/y1/x2/y2/xmin/ymin/boundx/boundy/
  // #coordXscale/#coordYscale fields declared with the rest of this component's own state above.
  #setCoordsLimits(xmin: number, ymin: number): void {
    this.#xmin = xmin;
    this.#ymin = ymin;
  }

  #setCoordsBounds(boundx: number, boundy: number, xscale: number, yscale: number): void {
    this.#boundx = boundx;
    this.#boundy = boundy;
    this.#coordXscale = xscale;
    this.#coordYscale = yscale;
  }

  #setCoordsPressed(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x2 = this.#x1 = rebounded[0];
    this.#y2 = this.#y1 = rebounded[1];
  }

  #setCoordsCurrent(pos: Point): void {
    const rebounded = this.#rebound(pos);
    this.#x2 = rebounded[0];
    this.#y2 = rebounded[1];
  }

  #moveCoordsOffset(offset: Point): void {
    let ox = offset[0],
      oy = offset[1];

    if (0 > this.#x1 + ox) {
      ox -= ox + this.#x1;
    }
    if (0 > this.#y1 + oy) {
      oy -= oy + this.#y1;
    }

    if (this.#boundy < this.#y2 + oy) {
      oy += this.#boundy - (this.#y2 + oy);
    }
    if (this.#boundx < this.#x2 + ox) {
      ox += this.#boundx - (this.#x2 + ox);
    }

    this.#x1 += ox;
    this.#x2 += ox;
    this.#y1 += oy;
    this.#y2 += oy;
  }

  #getCoordsCorner(ord: Corner): Point {
    const c = this.#getCoordsFixed();
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

    if (px0 > this.#boundx) px0 = this.#boundx;
    if (py0 > this.#boundy) py0 = this.#boundy;

    return [Math.round(px0), Math.round(py0)];
  }

  #getCoordsFixed(): JcropCrop & {x2: number; y2: number} {
    let delta;
    const xsize = this.#x2 - this.#x1,
      ysize = this.#y2 - this.#y1;
    const xscale = this.#coordXscale,
      yscale = this.#coordYscale;

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
    if (this.#x2 > this.#boundx) {
      delta = this.#x2 - this.#boundx;
      this.#x1 -= delta;
      this.#x2 -= delta;
    }
    if (this.#y2 > this.#boundy) {
      delta = this.#y2 - this.#boundy;
      this.#y1 -= delta;
      this.#y2 -= delta;
    }
    if (this.#x1 > this.#boundx) {
      delta = this.#x1 - this.#boundx;
      this.#x2 -= delta;
      this.#x1 -= delta;
    }
    if (this.#y1 > this.#boundy) {
      delta = this.#y1 - this.#boundy;
      this.#y2 -= delta;
      this.#y1 -= delta;
    }

    return makeObj(flipCoords(this.#x1, this.#y1, this.#x2, this.#y2));
  }

  #setSelect(rect: number[]): void {
    this.#setCoordsPressed([(rect[0] ?? 0) / this.#xscale(), (rect[1] ?? 0) / this.#yscale()]);
    this.#setCoordsCurrent([(rect[2] ?? 0) / this.#xscale(), (rect[3] ?? 0) / this.#yscale()]);
    this.#selectionUpdate();
    this.#onSelect(this.#unscale(this.#getCoordsFixed()));
    this.handlesVisible.set(true);
  }

  // Always reads #workingImg's own position - every caller only ever wants that, so it's read here
  // directly rather than threaded through as a parameter.
  readonly #getPos = (): Point => {
    // Guarded the same defensive way onLoad() is - see there. [0, 0] is an arbitrary but harmless
    // fallback for a branch that can't actually be reached (every caller only runs after onLoad()).
    if (!this.#window) return [0, 0];
    const rect = this.workingImgRef().nativeElement.getBoundingClientRect();
    return [rect.left + this.#window.scrollX, rect.top + this.#window.scrollY];
  };

  readonly #mouseAbs = (e: JcropMouseEvent): Point => {
    return [(e.pageX ?? 0) - this.#docOffset[0], (e.pageY ?? 0) - this.#docOffset[1]];
  };

  readonly #startDragMode = (mode: DragMode, pos: Point, touch?: boolean): void => {
    this.#docOffset = this.#getPos();
    this.trackerCursor.set(mode === 'move' ? mode : mode + '-resize');

    if (mode === 'move') {
      this.#activateTrackerHandlers(this.#createMover(pos), this.#doneSelect, touch);
      return;
    }

    const fc = this.#getCoordsFixed();
    const opp = oppLockCorner(mode);
    const opc = this.#getCoordsCorner(oppLockCorner(opp));

    this.#setCoordsPressed(this.#getCoordsCorner(opp));
    this.#setCoordsCurrent(opc);

    this.#activateTrackerHandlers(this.#dragmodeHandler(mode, fc), this.#doneSelect, touch);
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
      this.#setCoordsCurrent(pos);
      this.#selectionUpdate();
    };
  }

  #createMover(pos: Point): PositionCallback {
    let lloc = pos;

    return (pos: Point): void => {
      this.#moveCoordsOffset([pos[0] - lloc[0], pos[1] - lloc[1]]);
      lloc = pos;

      this.#selectionUpdate();
    };
  }

  // What used to be Touch's own createDragger() (Jcrop.ts), folded in directly since every
  // collaborator it needed was already a closure over this component anyway - and merged with its
  // own touch equivalent, since the two differed only in running the event through touchCfilter()
  // first and passing touch=true through to #startDragMode (which activates the tracker handlers in
  // touch mode - see #trackerIstouch).
  readonly #createDragger = (ord: DragMode, touch?: boolean): ((e: JcropMouseEvent) => void) => {
    return (e: JcropMouseEvent): void => {
      // Fix position of crop area when dragged the very first time.
      // Necessary when crop image is in a hidden element when page is loaded.
      this.#docOffset = this.#getPos();

      this.#startDragMode(ord, this.#mouseAbs(touch ? touchCfilter(e) : e), touch);
      e.stopPropagation();
      e.preventDefault();
    };
  };

  #unscale(c: JcropCrop & {x2: number; y2: number}): JcropCrop {
    return {
      h: c.h * this.#yscale(),
      w: c.w * this.#xscale(),
      x: c.x * this.#xscale(),
      y: c.y * this.#yscale(),
    };
  }

  readonly #doneSelect = (): void => {
    const c = this.#getCoordsFixed();
    // [0, 0] is the vendored plugin's minSelect option - never overridable via JcropOptions, so it's
    // a plain check now instead of routed through #options.
    if (c.w > 0 && c.h > 0) {
      this.handlesVisible.set(true);
      this.#selectionRefresh();
    } else {
      this.handlesVisible.set(false);
      this.selVisible.set(false);
      this.#setSelBgOpacity(1);
      this.#selectionAwake = false;
    }
    this.trackerCursor.set('crosshair');
  };

  #newSelection(e: JcropMouseEvent): void {
    this.#docOffset = this.#getPos();
    this.handlesVisible.set(false);
    this.trackerCursor.set('crosshair');
    const pos = this.#mouseAbs(e);
    this.#setCoordsPressed(pos);
    this.#selectionUpdate();
    this.#activateTrackerHandlers(this.#selectDrag, this.#doneSelect, e.type.startsWith('touch'));

    e.stopPropagation();
    e.preventDefault();
  }

  readonly #selectDrag = (pos: Point): void => {
    this.#setCoordsCurrent(pos);
    this.#selectionUpdate();
  };

  // What used to be Tracker's own activateHandlers()/#finish() (Jcrop.ts), folded in directly since
  // every collaborator those needed was already a closure over this component anyway.
  #activateTrackerHandlers(move: PositionCallback, done: PositionCallback, touch?: boolean): void {
    this.#trackerBtndown = true;
    this.#trackerIstouch = touch;
    this.#trackerOnMove = move;
    this.#trackerOnDone = done;
    this.trackerZIndex.set(450);
  }

  #finishTrackerDrag(e: JcropMouseEvent): void {
    e.preventDefault();
    e.stopPropagation();

    this.#trackerBtndown = false;

    this.#trackerOnDone(this.#mouseAbs(e));
    if (this.#selectionAwake) {
      this.#onSelect(this.#unscale(this.#getCoordsFixed()));
    }

    this.trackerZIndex.set(290);
    this.#trackerOnMove = function () {};
    this.#trackerOnDone = function () {};
  }

  // What used to be a separate Selection class (Jcrop.ts), folded in directly since every
  // collaborator it needed was already a closure over this component anyway, and it held no DOM
  // reference of its own any more either - see #selectionAwake above.
  #selectionRefresh(): void {
    const c = this.#getCoordsFixed();

    this.#setCoordsPressed([c.x, c.y]);
    this.#setCoordsCurrent([c.x2, c.y2]);

    if (this.#selectionAwake) {
      this.#selectionUpdate();
    }
  }

  // `select` is never actually passed true at any of this component's own call sites - preserved
  // faithfully from the vendored Selection class rather than dropped as part of this inlining.
  #selectionUpdate(select?: boolean): void {
    const c = this.#getCoordsFixed();

    this.selWidth.set(Math.round(c.w));
    this.selHeight.set(Math.round(c.h));
    this.img2Left.set(-c.x);
    this.img2Top.set(-c.y);
    this.selLeft.set(c.x);
    this.selTop.set(c.y);

    if (!this.#selectionAwake) {
      this.selVisible.set(true);
      this.#setSelBgOpacity(BG_OPACITY, true);
      this.#selectionAwake = true;
    }

    if (select) {
      this.#onSelect(this.#unscale(c));
    }
  }

  #setSelBgOpacity(opacity: number, force?: boolean): void {
    if (!this.#selectionAwake && !force) return;
    this.imgOpacity.set(opacity);
  }
}
