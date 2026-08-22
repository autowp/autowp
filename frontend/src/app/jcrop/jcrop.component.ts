import type {OnDestroy} from '@angular/core';

import {DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  inject,
  input,
  output,
  signal,
  viewChild,
  ViewEncapsulation,
} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

import type {
  Corner,
  DragMode,
  InternalOptions,
  JcropCrop,
  JcropMouseEvent,
  JcropOptions,
  Ordinal,
  Point,
  PositionCallback,
} from './Jcrop';

import {Coords, defaults, px, Selection, setStyle, Touch} from './Jcrop';

@Component({
  selector: 'app-jcrop',
  templateUrl: './jcrop.component.html',
  styleUrl: './jcrop.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  // Jcrop builds its handles/borders/tracker overlay with raw document.createElement() calls, not
  // this component's own template - Angular's emulated encapsulation only stamps its scoping
  // attribute onto elements it renders itself, so those .jcrop-* selectors would silently match
  // nothing under the default encapsulation. Global scope here is what angular.json's top-level
  // `styles` array already gave this stylesheet before it moved here.
  // eslint-disable-next-line @angular-eslint/use-component-view-encapsulation
  encapsulation: ViewEncapsulation.None,
  // Nothing here calls document.addEventListener()/removeEventListener() itself to track an active
  // drag - Angular has no equivalent to attaching/detaching a document-level listener conditionally,
  // so these are permanently bound (from the moment this component is created, well before #init()
  // ever runs) and just forward every event unconditionally; the on*() methods below are what
  // actually gate on whether a drag is in progress (#trackerBtndown/#trackerIstouch).
  host: {
    '(document:mousemove)': 'onDocumentMouseMove($event)',
    '(document:mouseup)': 'onDocumentMouseUp($event)',
    '(document:touchend)': 'onDocumentTouchEnd($event)',
    '(document:touchmove)': 'onDocumentTouchMove($event)',
  },
})
export class JcropComponent implements OnDestroy {
  readonly #elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  readonly #document = inject(DOCUMENT);
  readonly #window = browserWindow();

  readonly src = input.required<string>();
  readonly pictureWidth = input.required<number>();
  readonly pictureHeight = input.required<number>();
  readonly initialCrop = input<JcropCrop>();
  readonly minSize = input.required<[number, number]>();

  readonly cropChange = output<JcropCrop>();

  private readonly holderRef = viewChild.required<ElementRef<HTMLDivElement>>('holder');
  private readonly selRef = viewChild.required<ElementRef<HTMLDivElement>>('sel');
  private readonly imgHolderRef = viewChild.required<ElementRef<HTMLDivElement>>('imgHolder');
  private readonly trackerRef = viewChild.required<ElementRef<HTMLDivElement>>('tracker');

  // Whether the resize handles/dragbars are shown - driven by Selection#disableHandles()/
  // enableHandles() via the setHandlesVisible callback passed into it below, and read directly by
  // the #hdlHolder template element's [style.display] binding instead of Jcrop imperatively setting
  // that style itself.
  protected readonly handlesVisible = signal(false);

  // The #tracker template element's cursor - set directly via style manipulation in the vendored
  // original; #interfaceUpdate() (the first thing to set it, at the end of #init()) always sets
  // 'crosshair' first, so that's the signal's initial value too, matching what the element would
  // otherwise briefly render before #init() has ever run.
  protected readonly trackerCursor = signal('crosshair');

  // The #tracker template element's z-index - toggled between 290 (resting) and 450 (while a drag
  // started from it is in progress) by #activateTrackerHandlers()/#finishTrackerDrag() below.
  protected readonly trackerZIndex = signal(290);

  // Everything below is Jcrop's own former per-instance state - it used to belong to a separately
  // constructed Jcrop object, but that was pure indirection given this component is its only ever
  // consumer, so it was folded in directly (see the file-level comment in Jcrop.ts). All of it is
  // set once by #init(), when the template's own <img> fires its load event - never in this
  // component's own constructor, so TS's definite-assignment analysis can't see it; #initialized
  // guards the two externally-triggered entry points (ngOnDestroy/selectAll) against running before
  // that has happened - everything else here only ever runs from inside #init() itself.
  #initialized = false;
  #win!: Window;
  #origimg!: HTMLImageElement;
  #img!: HTMLImageElement;
  #imgHolder!: HTMLDivElement;
  #div!: HTMLDivElement;
  #boundx!: number;
  #boundy!: number;
  #coords!: Coords;
  #touch!: Touch;
  #bgopacity!: number;
  #selection!: Selection;
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
  // Starts as a placeholder div, replaced with a real <img> further down in #init() -
  // #moveto() (inside Selection, via the getImg2 getter passed to it) is the only thing that reads
  // this afterward, so the placeholder is never actually rendered.
  #img2!: HTMLElement;
  #options!: InternalOptions;
  #xscale!: number;
  #yscale!: number;
  #docOffset!: Point;

  ngOnDestroy(): void {
    if (!this.#initialized) return;
    this.#div.remove();
    setStyle(this.#origimg, {display: '', visibility: 'visible'});
  }

  // Called by the host page's own "select all" button via viewChild() - the crop dialogs each keep
  // that button (and the aspect/resolution readout next to it) in their own template/layout rather
  // than have this component own them, since each page positions them differently (a modal footer
  // flex row vs. an inline block).
  public selectAll(): void {
    if (!this.#initialized) return;
    this.#setSelect([0, 0, this.pictureWidth(), this.pictureHeight()]);
  }

  protected onLoad(e: Event): void {
    if (!(e.target instanceof HTMLImageElement)) {
      return;
    }
    const img = e.target;

    // Both guaranteed by the time a real `load` event can fire: this component's own template is
    // never rendered server-side (every consumer sits behind a RenderMode.Client route), so
    // #window is only ever null in a scenario where onLoad() can't be called in the first place.
    const win = this.#window;
    if (!win) {
      return;
    }

    // The real page container this component's content used to sit directly inside, before this
    // component existed - reading its width (rather than this component's own host element, which
    // isn't styled with any padding of its own) keeps the responsive-fit math identical to what it
    // was when the three crop pages each duplicated it inline.
    const container = this.#elementRef.nativeElement.parentElement;
    if (!container) {
      return;
    }

    const pictureWidth = this.pictureWidth();
    const pictureHeight = this.pictureHeight();

    const styles = win.getComputedStyle(container, null);
    const containerWidth =
      container.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight) || 1;

    const scale = pictureWidth / containerWidth;
    const width = pictureWidth / scale;
    const height = pictureHeight / scale;

    img.style.width = `${width}px`;
    img.style.height = `${height}px`;

    const initial = this.initialCrop() ?? {h: pictureHeight, w: pictureWidth, x: 0, y: 0};

    this.#init(
      img,
      {
        boxHeight: height,
        boxWidth: width,
        minSize: this.minSize(),
        onSelect: (crop: JcropCrop) => {
          // Coords already clamps against [0, boundx]/[0, boundy] in scaled space, but rounding
          // through xscale/yscale in unscale() can still leave a hair of negative slop after a
          // drag to the top/left edge - guard against saving that.
          this.cropChange.emit({...crop, x: Math.max(0, crop.x), y: Math.max(0, crop.y)});
        },
        setSelect: [initial.x, initial.y, initial.x + initial.w, initial.y + initial.h],
        trueSize: [pictureWidth, pictureHeight],
      },
      win,
    );
  }

  // Bound directly on the #tracker/#selectionTracker template elements (jcrop.component.html)
  // instead of Jcrop wiring them up itself with addEventListener() - both are static, permanent
  // elements, so there's no need for imperative attach/detach. Each still needs the #initialized
  // guard: the template binds these unconditionally, from before #init() has ever run.
  protected onTrackerMouseDown(e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#newSelection(e);
  }

  protected onTrackerTouchStart(e: TouchEvent): void {
    if (!this.#initialized || !this.#touch.support) return;
    this.#touch.newSelection(e);
  }

  protected onSelectionTrackerMouseDown(e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#createDragger('move')(e);
  }

  protected onSelectionTrackerTouchStart(e: TouchEvent): void {
    if (!this.#initialized || !this.#touch.support) return;
    this.#touch.createDragger('move')(e);
  }

  // Bound on each of the 12 static handle/dragbar template elements (jcrop.component.html), each
  // passing its own fixed ordinal - Selection itself no longer listens for DOM events at all, it
  // just owns what happens once one is forwarded to it (see Selection#dragStart/touchDragStart).
  protected onDragMouseDown(ord: Ordinal, e: MouseEvent): void {
    if (!this.#initialized) return;
    this.#selection.dragStart(ord, e);
  }

  protected onDragTouchStart(ord: Ordinal, e: TouchEvent): void {
    if (!this.#initialized || !this.#touch.support) return;
    this.#selection.touchDragStart(ord, e);
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
    this.#trackerOnMove(this.#mouseAbs(this.#touch.cfilter(e)));
  }

  protected onDocumentTouchEnd(e: TouchEvent): void {
    if (!this.#initialized || !this.#trackerBtndown || !this.#trackerIstouch) return;
    this.#finishTrackerDrag(this.#touch.cfilter(e));
  }

  // The vendored plugin's own single closure-based factory function, ported first to a class and
  // then merged directly onto this component: everything that used to be a local variable closed
  // over by every nested function is now a private field, and the standalone Touch/Selection/Coords
  // modules it built as IIFEs (see Jcrop.ts) are collaborator objects constructed here and wired
  // together explicitly instead. (The would-be fourth module, Tracker, never had a distinct piece of
  // DOM/state of its own - just fields closing over this component's collaborators - so it's folded
  // in directly as #trackerBtndown/#trackerIstouch/#trackerOnMove/#trackerOnDone and the
  // #activateTrackerHandlers()/#finishTrackerDrag() methods below instead of a class in Jcrop.ts.)
  //
  // A handful of methods below are field arrows rather than ordinary methods -
  // #getPos/#mouseAbs/#startDragMode/#createDragger/#doneSelect/#selectDrag - because each is handed
  // to a collaborator (Touch/Selection) by bare reference rather than called directly; an ordinary
  // method read that way loses its `this` binding the moment something else invokes it.
  #init(origimg: HTMLImageElement, opt: JcropOptions, win: Window): void {
    this.#win = win;
    this.#options = {...defaults};

    // imgHolder's styling (height/width/z-index/overflow/position) never changes at runtime, so
    // it's static CSS on its template element (jcrop.component.html/.scss) instead of set here.
    this.#imgHolder = this.imgHolderRef().nativeElement;

    this.#mergeOptions(opt);

    // The values are SET on the image(s) for the interface. If the original image has any of these
    // set, they will be reset. However, on ngOnDestroy() the original image's character in the DOM
    // will be as it was left.
    const imgStyle: Partial<CSSStyleDeclaration> = {
      border: 'none',
      left: '0',
      margin: '0',
      padding: '0',
      position: 'absolute',
      top: '0',
      visibility: 'visible',
    };

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
    setStyle(origimg, {display: 'none'});

    // #holder is already positioned right where the clone belongs (jcrop.component.html declares it
    // immediately after the real <img>) - append the clone straight into it instead of first
    // attaching it as origimg's sibling and reparenting it in afterward. offsetWidth/offsetHeight
    // below only need the clone attached to the DOM somewhere, not specifically to this parent.
    const div = this.holderRef().nativeElement;
    this.#div = div;
    div.append(img);

    this.#presize(img, this.#options.boxWidth, this.#options.boxHeight);

    this.#boundx = img.offsetWidth;
    this.#boundy = img.offsetHeight;
    // backgroundColor/position are static CSS on .jcrop-holder (jcrop.component.scss); only the
    // image-dependent size is set here.
    setStyle(div, {height: px(this.#boundy), width: px(this.#boundx)});

    const bound = this.#options.boundary;
    const trk = this.trackerRef().nativeElement;
    // position is static CSS on .jcrop-tracker; z-index is the trackerZIndex signal (bound in the
    // template), toggled by Tracker itself between 290 and 450 at runtime.
    setStyle(trk, {
      height: px(this.#boundy + bound * 2),
      left: px(-bound),
      top: px(-bound),
      width: px(this.#boundx + bound * 2),
    });

    this.#img2 = this.#document.createElement('div');
    // position/zIndex are static CSS on the sel template element (jcrop.component.html).
    const sel = this.selRef().nativeElement;

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
    this.#document.addEventListener('touchstart', function (e) {
      const target = e.currentTarget;
      if (target instanceof Element && target.classList.contains('jcrop-tracker')) e.stopPropagation();
    });

    this.#selection = new Selection(
      img,
      () => this.#img2,
      sel,
      this.#bgopacity,
      (ord) => this.#createDragger(ord),
      (ord) => this.#touch.createDragger(ord),
      this.#coords,
      (c) => {
        this.#options.onSelect.call(this, this.#unscale(c));
      },
      (visible) => {
        this.handlesVisible.set(visible);
      },
    );

    this.#img2 = this.#document.createElement('img');
    (this.#img2 as HTMLImageElement).src = img.getAttribute('src') ?? '';
    setStyle(this.#img2, imgStyle);
    setStyle(this.#img2, {display: '', height: px(this.#boundy), width: px(this.#boundx)});
    this.#imgHolder.append(this.#img2);

    this.#docOffset = this.#getPos(img);

    this.#initialized = true;
    this.#interfaceUpdate();
  }

  #setSelect(rect: number[]): void {
    this.#setSelectRaw([
      (rect[0] ?? 0) / this.#xscale,
      (rect[1] ?? 0) / this.#yscale,
      (rect[2] ?? 0) / this.#xscale,
      (rect[3] ?? 0) / this.#yscale,
    ]);
    this.#options.onSelect.call(this, this.#unscale(this.#coords.getFixed()));
    this.#selection.enableHandles();
  }

  readonly #getPos = (el: HTMLElement): Point => {
    const rect = el.getBoundingClientRect();
    return [rect.left + this.#win.scrollX, rect.top + this.#win.scrollY];
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
    this.trackerCursor.set(mode === 'move' ? mode : mode + '-resize');

    if (mode === 'move') {
      this.#activateTrackerHandlers(this.#createMover(pos), this.#doneSelect, touch);
      return;
    }

    const fc = this.#coords.getFixed();
    const opp = this.#oppLockCorner(mode);
    const opc = this.#coords.getCorner(this.#oppLockCorner(opp));

    this.#coords.setPressed(this.#coords.getCorner(opp));
    this.#coords.setCurrent(opc);

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
    this.trackerCursor.set('crosshair');
  };

  #newSelection(e: JcropMouseEvent): void {
    this.#docOffset = this.#getPos(this.#img);
    this.#selection.disableHandles();
    this.trackerCursor.set('crosshair');
    const pos = this.#mouseAbs(e);
    this.#coords.setPressed(pos);
    this.#selection.update();
    this.#activateTrackerHandlers(this.#selectDrag, this.#doneSelect, e.type.startsWith('touch'));

    e.stopPropagation();
    e.preventDefault();
  }

  readonly #selectDrag = (pos: Point): void => {
    this.#coords.setCurrent(pos);
    this.#selection.update();
  };

  // The tracker equivalent of Selection#dragStart/touchDragStart et al.: what used to be Tracker's
  // own activateHandlers()/#finish() (Jcrop.ts), folded in directly since every collaborator those
  // needed was already a closure over this component anyway.
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
    if (this.#selection.isAwake()) {
      this.#options.onSelect.call(this, this.#unscale(this.#coords.getFixed()));
    }

    this.trackerZIndex.set(290);
    this.#trackerOnMove = function () {};
    this.#trackerOnDone = function () {};
  }

  #setSelectRaw(l: [number, number, number, number]): void {
    this.#coords.setPressed([l[0], l[1]]);
    this.#coords.setCurrent([l[2], l[3]]);
    this.#selection.update();
  }

  // This method tweaks the interface based on options object. Called once, at end of #init().
  #interfaceUpdate(): void {
    this.trackerCursor.set('crosshair');

    if (Object.hasOwn(this.#options, 'trueSize') && this.#options.trueSize) {
      this.#xscale = this.#options.trueSize[0] / this.#boundx;
      this.#yscale = this.#options.trueSize[1] / this.#boundy;
    }

    if (Object.hasOwn(this.#options, 'setSelect') && this.#options.setSelect) {
      this.#setSelect(this.#options.setSelect);
      this.#selection.done();
      delete this.#options.setSelect;
    }

    this.#coords.setLimits(this.#options.minSize[0], this.#options.minSize[1]);

    this.#selection.refresh();
  }
}
