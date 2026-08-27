import type {AfterViewInit} from '@angular/core';
import type {Picture, PictureItem} from '@grpc/spec.pb';

import {NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, ElementRef, inject, input, signal} from '@angular/core';
import {DomSanitizer} from '@angular/platform-browser';
import {RouterLink} from '@angular/router';
import {NgMathPipesModule} from 'ngx-pipes';

import {AreaComponent} from './area.component';

interface Area {
  pictureItem: PictureItem;
  styles: Record<string, number>;
}

interface Bounds {
  height: number;
  left: number;
  top: number;
  width: number;
}

interface Dimension {
  height: number;
  width: number;
}

function bound(container: Dimension, content: Dimension): Dimension {
  const containerRatio = container.width / container.height;
  const contentRatio = content.width / content.height;

  let width: number;
  let height: number;
  if (contentRatio > containerRatio) {
    width = container.width;
    height = width / contentRatio;
  } else {
    height = container.height;
    width = height * contentRatio;
  }

  return {
    height,
    width,
  };
}

function boundCenter(container: Dimension, content: Dimension): Bounds {
  return {
    height: content.height,
    left: (container.width - content.width) / 2,
    top: (container.height - content.height) / 2,
    width: content.width,
  };
}

function maxBounds(bounds: Dimension, max: Dimension): Dimension {
  if (bounds.height > max.height || bounds.width > max.width) {
    return max;
  }
  return bounds;
}

@Component({
  selector: 'app-gallery-carousel-item',
  imports: [NgStyle, AreaComponent, RouterLink, NgMathPipesModule],
  templateUrl: './carousel-item.component.html',
  styleUrl: './carousel-item.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(window:resize)': 'onResize()',
  },
  preserveWhitespaces: false,
})
export class CarouselItemComponent implements AfterViewInit {
  readonly #el = inject<ElementRef<HTMLElement>>(ElementRef);
  readonly #sanitizer = inject(DomSanitizer);

  readonly item = input.required<Picture>();
  readonly prefix = input.required<string[]>();

  protected readonly cropMode = signal(true);
  protected readonly cropModeAvailable = computed(() => !!this.item().imageGallery);
  protected readonly fullLoading = signal(true);
  protected readonly cropLoading = signal(true);

  protected readonly nameHtml = computed(() => {
    // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
    return this.#sanitizer.bypassSecurityTrustHtml(this.item().nameHtml);
  });

  readonly #elSize = signal<Dimension>({height: 0, width: 0});

  readonly #offsetBounds = computed<Bounds | undefined>(() => {
    const item = this.item();
    if (!item.image) {
      return undefined;
    }

    const crop = item.imageGallery;
    const refImage = crop && this.cropMode() ? crop : item.imageGalleryFull;

    if (refImage) {
      const cSize = this.#elSize();
      if (cSize.width === 0 || cSize.height === 0) {
        return undefined;
      }

      const bounds = maxBounds(
        bound(cSize, {
          height: refImage.height,
          width: refImage.width,
        }),
        {
          height: refImage.height,
          width: refImage.width,
        },
      );
      return boundCenter(cSize, bounds);
    }

    return undefined;
  });

  protected readonly cropStyle = computed<Record<string, number>>((): Record<string, number> => {
    const item = this.item();
    if (!item.image || !item.imageGallery) {
      return {};
    }

    const full = item.imageGalleryFull;
    const offsetBounds = this.#offsetBounds();
    if (!offsetBounds) {
      return {};
    }

    if (this.cropMode()) {
      return {
        'height.px': offsetBounds.height,
        'left.px': offsetBounds.left,
        'top.px': offsetBounds.top,
        'width.px': offsetBounds.width,
      };
    }

    if (full) {
      const cSize = this.#elSize();
      const ih = item.image.height;
      const iw = item.image.width;
      const bounds = maxBounds(
        bound(cSize, {
          height: full.height,
          width: full.width,
        }),
        {
          height: full.height,
          width: full.width,
        },
      );
      return {
        'height.px': (bounds.height * item.image.cropHeight) / ih,
        'left.px': offsetBounds.left + (item.image.cropLeft * bounds.width) / iw,
        'top.px': offsetBounds.top + (item.image.cropTop * bounds.height) / ih,
        'width.px': (bounds.width * item.image.cropWidth) / iw,
      };
    }

    return {};
  });

  protected readonly fullStyle = computed<Record<string, number> | undefined>(
    (): Record<string, number> | undefined => {
      const item = this.item();
      if (!item.image) {
        return undefined;
      }

      const crop = item.imageGallery;
      const offsetBounds = this.#offsetBounds();
      if (!offsetBounds) {
        return undefined;
      }

      let refBounds: Bounds | undefined = undefined;

      if (crop && this.cropMode()) {
        const cSize = this.#elSize();
        if (cSize.width <= 0 || cSize.height <= 0) {
          return undefined;
        }

        const bounds = maxBounds(
          bound(cSize, {
            height: crop.height,
            width: crop.width,
          }),
          {
            height: crop.height,
            width: crop.width,
          },
        );

        const ih = item.image.height;
        const iw = item.image.width;
        const fullWidth = (bounds.width / item.image.cropWidth) * iw;
        const fullHeight = (bounds.height / item.image.cropHeight) * ih;
        refBounds = {
          height: fullHeight,
          left: offsetBounds.left - (item.image.cropLeft * fullWidth) / iw,
          top: offsetBounds.top - (item.image.cropTop * fullHeight) / ih,
          width: fullWidth,
        };
      } else if (item.imageGalleryFull) {
        refBounds = offsetBounds;
      }

      if (!refBounds) {
        return undefined;
      }

      return {
        'height.px': refBounds.height,
        'left.px': refBounds.left,
        'top.px': refBounds.top,
        'width.px': refBounds.width,
      };
    },
  );

  protected readonly areas = computed<Area[]>(() => {
    const item = this.item(),
      offsetBounds = this.#offsetBounds();

    if (!item.image || !offsetBounds) {
      return [];
    }

    let areaBounds = offsetBounds;
    const ih = item.image.height;
    const iw = item.image.width;
    const crop = item.imageGallery;

    if (crop && this.cropMode()) {
      const cSize = this.#elSize();
      const bounds = maxBounds(
        bound(cSize, {
          height: crop.height,
          width: crop.width,
        }),
        {
          height: crop.height,
          width: crop.width,
        },
      );
      const fullWidth = (bounds.width / item.image.cropWidth) * iw;
      const fullHeight = (bounds.height / item.image.cropHeight) * ih;
      areaBounds = {
        height: fullHeight,
        left: offsetBounds.left - (item.image.cropLeft * fullWidth) / iw,
        top: offsetBounds.top - (item.image.cropTop * fullHeight) / ih,
        width: fullWidth,
      };
    }

    return (item.pictureItems?.items ?? []).map((pictureItem) => {
      return {
        pictureItem: pictureItem,
        styles: {
          'height.px': (pictureItem.cropHeight * areaBounds.height) / ih,
          'left.px': areaBounds.left + (pictureItem.cropLeft * areaBounds.width) / iw,
          'top.px': areaBounds.top + (pictureItem.cropTop * areaBounds.height) / ih,
          'width.px': (pictureItem.cropWidth * areaBounds.width) / iw,
        },
      };
    });
  });

  ngAfterViewInit(): void {
    this.onResize();
  }

  protected onResize() {
    this.#elSize.update(() => ({
      height: this.#el.nativeElement.clientHeight || 0,
      width: this.#el.nativeElement.clientWidth || 0,
    }));
  }

  protected fullLoaded() {
    this.fullLoading.set(false);
  }

  protected cropLoaded() {
    this.cropLoading.set(false);
  }

  protected toggleCrop() {
    this.cropMode.set(!this.cropMode());
  }
}
