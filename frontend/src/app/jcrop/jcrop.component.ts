import type {OnDestroy} from '@angular/core';

import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, ElementRef, inject, input, output} from '@angular/core';
import {browserWindow} from '@utils/browser-window';

import type {JcropCrop, JcropInstance} from './Jcrop';

import Jcrop from './Jcrop';

@Component({
  selector: 'app-jcrop',
  templateUrl: './jcrop.component.html',
  styleUrl: './jcrop.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
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

  #jcrop: JcropInstance | null = null;

  ngOnDestroy(): void {
    this.#jcrop?.destroy();
  }

  // Called by the host page's own "select all" button via viewChild() - the crop dialogs each keep
  // that button (and the aspect/resolution readout next to it) in their own template/layout rather
  // than have this component own them, since each page positions them differently (a modal footer
  // flex row vs. an inline block).
  public selectAll(): void {
    this.#jcrop?.setSelect([0, 0, this.pictureWidth(), this.pictureHeight()]);
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

    this.#jcrop = Jcrop(
      img,
      {
        boxHeight: height,
        boxWidth: width,
        keySupport: false,
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
      this.#document,
      win,
    );
  }
}
