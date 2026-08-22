import type {OnDestroy} from '@angular/core';

import {DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  inject,
  input,
  output,
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

import {Coords, defaults, px, Selection, setStyle, Touch, Tracker} from './Jcrop';

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
  private readonly hdlHolderRef = viewChild.required<ElementRef<HTMLDivElement>>('hdlHolder');
  private readonly trackerRef = viewChild.required<ElementRef<HTMLDivElement>>('tracker');

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
  #hdlHolder!: HTMLDivElement;
  #imgHolder!: HTMLDivElement;
  #div!: HTMLDivElement;
  #boundx!: number;
  #boundy!: number;
  #coords!: Coords;
  #touch!: Touch;
  #bgopacity!: number;
  #selection!: Selection;
  #tracker!: Tracker;
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

  // The vendored plugin's own single closure-based factory function, ported first to a class and
  // then merged directly onto this component: everything that used to be a local variable closed
  // over by every nested function is now a private field, and the standalone Touch/Selection/
  // Tracker/Coords modules it built as IIFEs (see Jcrop.ts) are collaborator objects constructed
  // here and wired together explicitly instead.
  //
  // A handful of methods below are field arrows rather than ordinary methods -
  // #getPos/#mouseAbs/#startDragMode/#createDragger/#doneSelect/#selectDrag - because each is handed
  // to a collaborator (Touch/Selection/Tracker) by bare reference rather than called directly; an
  // ordinary method read that way loses its `this` binding the moment something else invokes it.
  #init(origimg: HTMLImageElement, opt: JcropOptions, win: Window): void {
    this.#win = win;
    this.#options = {...defaults};

    // hdlHolder/imgHolder's styling (height/width/z-index, and imgHolder's overflow/position) never
    // changes at runtime, so it's static CSS on their template elements (jcrop.component.html/.scss)
    // instead of set here.
    this.#hdlHolder = this.hdlHolderRef().nativeElement;
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
    origimg.after(img);
    setStyle(origimg, {display: 'none'});

    this.#presize(img, this.#options.boxWidth, this.#options.boxHeight);

    this.#boundx = img.offsetWidth;
    this.#boundy = img.offsetHeight;
    // backgroundColor/position are static CSS on .jcrop-holder (jcrop.component.scss); only the
    // image-dependent size is set here.
    const div = this.holderRef().nativeElement;
    this.#div = div;
    setStyle(div, {height: px(this.#boundy), width: px(this.#boundx)});
    div.append(img);

    const bound = this.#options.boundary;
    const trk = this.trackerRef().nativeElement;
    // position is static CSS on .jcrop-tracker; zIndex is left here even though 290 is also its
    // resting value, because Tracker#toFront()/#toBack() toggle it between 290 and 450 at runtime.
    setStyle(trk, {
      height: px(this.#boundy + bound * 2),
      left: px(-bound),
      top: px(-bound),
      width: px(this.#boundx + bound * 2),
      zIndex: '290',
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
      this.#document,
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
    );

    // Tracker Module {{{
    this.#tracker = new Tracker(
      this.#document,
      trk,
      (e) => this.#mouseAbs(e),
      (e) => this.#touch.cfilter(e),
      () => {
        if (this.#selection.isAwake()) {
          this.#options.onSelect.call(this, this.#unscale(this.#coords.getFixed()));
        }
      },
    );

    this.#img2 = this.#document.createElement('img');
    (this.#img2 as HTMLImageElement).src = img.getAttribute('src') ?? '';
    setStyle(this.#img2, imgStyle);
    setStyle(this.#img2, {display: '', height: px(this.#boundy), width: px(this.#boundx)});
    this.#imgHolder.append(this.#img2);

    this.#docOffset = this.#getPos(img);

    setStyle(this.#hdlHolder, {display: 'none'});
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

  #newSelection(e: JcropMouseEvent): void {
    this.#docOffset = this.#getPos(this.#img);
    this.#selection.disableHandles();
    this.#tracker.setCursor('crosshair');
    const pos = this.#mouseAbs(e);
    this.#coords.setPressed(pos);
    this.#selection.update();
    this.#tracker.activateHandlers(this.#selectDrag, this.#doneSelect, e.type.startsWith('touch'));

    e.stopPropagation();
    e.preventDefault();
  }

  readonly #selectDrag = (pos: Point): void => {
    this.#coords.setCurrent(pos);
    this.#selection.update();
  };

  #setSelectRaw(l: [number, number, number, number]): void {
    this.#coords.setPressed([l[0], l[1]]);
    this.#coords.setCurrent([l[2], l[3]]);
    this.#selection.update();
  }

  // This method tweaks the interface based on options object. Called once, at end of #init().
  #interfaceUpdate(): void {
    this.#tracker.setCursor('crosshair');

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
