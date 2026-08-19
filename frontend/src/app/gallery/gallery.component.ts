import type {OnInit, ResourceRef} from '@angular/core';
import type {GalleryResponse, Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, effect, inject, Injector, input, output} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {Router, RouterLink} from '@angular/router';
import {
  GalleryRequest,
  ItemFields,
  ItemParentCacheListOptions,
  ItemsRequest,
  PictureFields,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {catchError, EMPTY, of, switchMap} from 'rxjs';

import {ToastsService} from '../toasts/toasts.service';
import {CarouselItemComponent} from './carousel-item.component';

const MAX_INDICATORS = 30;
const PER_PAGE = 10;

const galleryFields = new PictureFields({
  commentsCount: true,
  image: true,
  imageGallery: true,
  imageGalleryFull: true,
  nameHtml: true,
  nameText: true,
  pictureItem: new PictureItemsRequest({
    fields: new PictureItemFields({
      item: new ItemsRequest({
        fields: new ItemFields({nameHtml: true}),
      }),
    }),
    options: new PictureItemListOptions({
      hasArea: true,
      typeId: PictureItemType.PICTURE_ITEM_CONTENT,
    }),
  }),
});

export interface APIGalleryFilter {
  exactItemID?: string;
  exactItemLinkType?: PictureItemType;
  itemID?: string;
  perspectiveExclude?: number[];
  perspectiveID?: number;
}

// Plain data, not a class with methods - this is stored as an rxResource value, which round-trips
// through TransferState via JSON.stringify/JSON.parse for SSR hydration, losing any prototype a
// class instance would have. The functions below operate on this shape instead of living on it.
interface GalleryState {
  current: number;
  filter: APIGalleryFilter;
  items: (null | Picture)[];
  status: PictureStatus;
}

function galleryFilterParams(state: GalleryState, language: string): PicturesRequest {
  const options = new PictureListOptions({
    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
  });

  let order = PicturesRequest.Order.ORDER_RESOLUTION_DESC;
  if (state.filter.itemID || state.filter.exactItemID) {
    order = PicturesRequest.Order.ORDER_PERSPECTIVES;
  }

  if (
    state.filter.itemID ||
    state.filter.exactItemID ||
    state.filter.exactItemLinkType ||
    state.filter.perspectiveID ||
    state.filter.perspectiveExclude
  ) {
    options.pictureItem = new PictureItemListOptions({
      excludePerspectiveId: state.filter.perspectiveExclude,
      itemId: state.filter.exactItemID,
      itemParentCacheAncestor: state.filter.itemID
        ? new ItemParentCacheListOptions({
            parentId: state.filter.itemID,
          })
        : undefined,
      perspectiveId: state.filter.perspectiveID,
      typeId: state.filter.exactItemLinkType,
    });
  }

  return new PicturesRequest({
    fields: galleryFields,
    language,
    options,
    order,
  });
}

function galleryItemIndex(state: GalleryState, identity: string): number {
  return state.items.findIndex((item) => item?.identity === identity);
}

function galleryItemByIndex(state: GalleryState, index: number): null | Picture {
  if (index < 0 || index >= state.items.length) {
    return null;
  }

  return state.items[index] ?? null;
}

function galleryItem(state: GalleryState, identity: string): null | Picture {
  const index = galleryItemIndex(state, identity);
  return index < 0 ? null : galleryItemByIndex(state, index);
}

function applyGalleryResponse(state: GalleryState, response: GalleryResponse): void {
  if (state.items.length < response.count) {
    state.items[response.count - 1] = null;
    state.status = response.status;
  }

  (response.items ?? []).forEach((item, i) => {
    const index = (response.page - 1) * PER_PAGE + i;
    state.items[index] = item;
  });
}

function galleryPageNumberByIndex(index: number): number {
  return Math.floor(index / PER_PAGE) + 1;
}

@Component({
  selector: 'app-gallery',
  imports: [CarouselItemComponent, RouterLink],
  templateUrl: './gallery.component.html',
  styleUrl: './gallery.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(document:keydown.escape)': 'onKeydownHandler()',
    '(document:keydown.arrowright)': 'onRightKeydownHandler()',
    '(document:keydown.arrowleft)': 'onLeftKeydownHandler()',
  },
})
export class GalleryComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #injector = inject(Injector);

  readonly filter = input.required<APIGalleryFilter>();
  readonly current = input.required<null | string>();
  readonly galleryPrefix = input.required<string[]>();
  readonly picturePrefix = input.required<string[]>();
  readonly pictureSelected = output<null | Picture>();

  // The accumulator this component builds up as the user pages through the gallery - kept as a
  // plain mutable object outside any signal, so paging via loadPage$() can fill it in ahead of
  // navigating without fighting the resource's own value-replacement lifecycle (navigating
  // afterwards changes `current`, which re-triggers galleryResource's stream() below and picks up
  // the prefetched item from this same instance). A new instance is only created when the filter
  // itself actually changes.
  #state?: GalleryState;

  // Memoized so an inline object-literal input binding (e.g. [filter]="{}") re-evaluating to a new
  // reference on every change-detection cycle doesn't look like a param change and re-trigger the
  // loader - matches the previous distinctUntilChanged((a, b) => JSON.stringify(a) ===
  // JSON.stringify(b)) on the old Observable-based #currentFilter$.
  #lastParamsKey?: string;
  #lastParams?: {filter: APIGalleryFilter; identity: string};

  // Fetches the picture named by `current` as a pending task Angular's SSR whenStable() waits on
  // through Angular's reactive graph, instead of a raw Observable subscribed only via the
  // template's `| async` (the previous shape here) - that chain's debounceTime(50) on filter and
  // debounceTime(10) on identity, both before ever making an HTTP call, only register as pending
  // via ZoneStablePendingTask, the zone-based-CD bridge that holds a task while any macrotask is
  // pending; it covers them today, but it disappears with the zone, and then SSR could serialize a
  // 200 before the NOT_FOUND redirect below had even fired. See the constructor effect(), the
  // single place that navigates off this resource's error() signal.
  //
  // Non-NOT_FOUND errors are toasted and resolved with the unchanged state rather than left as a
  // resource error - this mirrors the previous behavior of leaving the last-good gallery on screen
  // through a background refresh failure, and keeps this resource's error() meaning exclusively
  // "not found" for the effect() below.
  //
  // Constructed in ngOnInit() (with an explicit injector, since ngOnInit isn't an injection
  // context), not as a field initializer: filter/current are *required* inputs, and Angular's
  // compiler forbids reading a required input's value before the class is fully constructed - they
  // aren't bound yet at field-initializer/constructor time.
  protected galleryResource!: ResourceRef<GalleryState | undefined>;

  constructor() {
    // Router.navigate() is fire-and-forget here (not folded into the resource stream): it runs
    // outside galleryResource's own pending-task lifecycle, so there's no window where the
    // resource can settle and let SSR's whenStable() serialize before the redirect it triggered has
    // actually registered.
    effect(() => {
      if (isNotFoundError(this.galleryResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });
  }

  ngOnInit(): void {
    this.galleryResource = rxResource({
      id: `gallery-${JSON.stringify(this.filter())}-${this.current() ?? ''}`,
      injector: this.#injector,
      params: (): undefined | {filter: APIGalleryFilter; identity: string} => {
        const filter = this.filter();
        const identity = this.current();
        if (!identity) {
          return undefined;
        }

        const key = JSON.stringify(filter) + '|' + identity;
        if (this.#lastParamsKey !== key) {
          this.#lastParamsKey = key;
          this.#lastParams = {filter, identity};
        }

        return this.#lastParams;
      },
      stream: ({params: {filter, identity}}): Observable<GalleryState> => {
        if (!this.#state || JSON.stringify(this.#state.filter) !== JSON.stringify(filter)) {
          this.#state = {current: 0, filter, items: [], status: PictureStatus.PICTURE_STATUS_UNKNOWN};
        }
        const state = this.#state;

        if (galleryItem(state, identity)) {
          return of(this.#withCurrent(state, identity));
        }

        return this.#picturesClient
          .getGallery(
            new GalleryRequest({
              pictureIdentity: identity,
              request: galleryFilterParams(state, this.#languageService.language),
            }),
          )
          .pipe(
            switchMap((response) => {
              applyGalleryResponse(state, response);
              return of(this.#withCurrent(state, identity));
            }),
            catchError((response: unknown) => {
              if (isNotFoundError(response)) {
                return notFoundError();
              }
              this.#toastService.handleError(response);
              return of(state);
            }),
          );
      },
    });
  }

  #withCurrent(state: GalleryState, identity: string): GalleryState {
    const index = galleryItemIndex(state, identity);
    state.current = index;
    this.pictureSelected.emit(galleryItemByIndex(state, index));
    return state;
  }

  protected useCircleIndicator(state: GalleryState): boolean {
    return state.items.length <= MAX_INDICATORS;
  }

  onKeydownHandler() {
    const identity = this.current();
    if (identity) {
      void this.#router.navigate(this.picturePrefix().concat([identity]));
    }
  }

  onRightKeydownHandler() {
    if (!this.galleryResource.hasValue()) {
      return;
    }

    const state = this.galleryResource.value();
    if (state.current + 1 < state.items.length) {
      this.navigateToIndex(state.current + 1, state);
    }
  }

  onLeftKeydownHandler() {
    if (!this.galleryResource.hasValue()) {
      return;
    }

    const state = this.galleryResource.value();
    if (state.current > 0) {
      this.navigateToIndex(state.current - 1, state);
    }
  }

  private loadPage$(page: number, state: GalleryState): Observable<GalleryResponse> {
    const request = galleryFilterParams(state, this.#languageService.language);
    // galleryFilterParams() always constructs and sets `options` on the PicturesRequest it
    // returns - TS just can't see that across the function boundary since the field itself is
    // optional on the generated proto message type.
    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
    request.options!.status = state.status;
    request.page = page;

    return this.#picturesClient.getGallery(new GalleryRequest({request})).pipe(
      catchError((response: unknown) => {
        if (isNotFoundError(response)) {
          void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        } else {
          this.#toastService.handleError(response);
        }
        return EMPTY;
      }),
      switchMap((response) => {
        applyGalleryResponse(state, response);
        return of(response);
      }),
    );
  }

  protected navigateToIndex(index: number, state: GalleryState): void {
    const item = galleryItemByIndex(state, index);
    if (item) {
      void this.#router.navigate(this.galleryPrefix().concat([item.identity]));
      return;
    }

    const page = galleryPageNumberByIndex(index);
    this.loadPage$(page, state).subscribe(() => {
      const sitem = galleryItemByIndex(state, index);
      if (sitem) {
        void this.#router.navigate(this.galleryPrefix().concat([sitem.identity]));
      }
    });
  }
}
