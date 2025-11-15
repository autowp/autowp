import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Router, RouterLink} from '@angular/router';
import {
  GalleryRequest,
  GalleryResponse,
  ItemFields,
  ItemParentCacheListOptions,
  ItemsRequest,
  Picture,
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
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {LanguageService} from '@services/language';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, take, tap} from 'rxjs/operators';

import {StatusCode} from '../../grpc-web-client/statuscode';
import {ToastsService} from '../toasts/toasts.service';
import {CarouselItemComponent} from './carousel-item.component';

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
  exactItemLinkType?: number;
  itemID?: string;
  perspectiveExclude?: number[];
  perspectiveID?: number;
}

class Gallery {
  readonly #MAX_INDICATORS = 30;
  readonly #PER_PAGE = 10;

  public current = 0;
  public status: PictureStatus = PictureStatus.PICTURE_STATUS_UNKNOWN;
  public get useCircleIndicator(): boolean {
    return this.items.length <= this.#MAX_INDICATORS;
  }

  constructor(
    public readonly filter: APIGalleryFilter,
    public readonly items: (null | Picture)[],
  ) {}

  public filterParams(language: string): PicturesRequest {
    const options = new PictureListOptions({
      status: PictureStatus.PICTURE_STATUS_ACCEPTED,
    });

    let order = PicturesRequest.Order.ORDER_RESOLUTION_DESC;
    if (this.filter.itemID || this.filter.exactItemID) {
      order = PicturesRequest.Order.ORDER_PERSPECTIVES;
    }

    if (
      this.filter.itemID ||
      this.filter.exactItemID ||
      this.filter.exactItemLinkType ||
      this.filter.perspectiveID ||
      this.filter.perspectiveExclude
    ) {
      options.pictureItem = new PictureItemListOptions({
        excludePerspectiveId: this.filter.perspectiveExclude,
        itemId: this.filter.exactItemID,
        itemParentCacheAncestor: this.filter.itemID
          ? new ItemParentCacheListOptions({
              parentId: this.filter.itemID,
            })
          : undefined,
        perspectiveId: this.filter.perspectiveID,
        typeId: this.filter.exactItemLinkType,
      });
    }

    return new PicturesRequest({
      fields: galleryFields,
      language,
      options,
      order,
    });
  }

  public getItemIndex(identity: string): number {
    return this.items.findIndex((item) => item && item.identity === identity);
  }

  public getItemByIndex(index: number): null | Picture {
    if (index < 0 || index >= this.items.length) {
      return null;
    }

    if (!this.items[index]) {
      return null;
    }

    return this.items[index];
  }

  public getGalleryItem(identity: string): null | Picture {
    const index = this.getItemIndex(identity);
    if (index < 0) {
      return null;
    }

    return this.getItemByIndex(index);
  }

  public applyResponse(response: GalleryResponse) {
    if (this.items.length < response.count) {
      this.items[response.count - 1] = null;
      this.status = response.status;
    }

    (response.items || []).forEach((item, i) => {
      const index = (response.page - 1) * this.#PER_PAGE + i;
      this.items[index] = item;
    });
  }

  public getGalleryPageNumberByIndex(index: number) {
    return Math.floor(index / this.#PER_PAGE) + 1;
  }
}

@Component({
  selector: 'app-gallery',
  imports: [CarouselItemComponent, RouterLink, AsyncPipe],
  templateUrl: './gallery.component.html',
  styleUrl: './gallery.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(document:keydown.escape)': 'onKeydownHandler()',
    '(document:keydown.arrowright)': 'onRightKeydownHandler()',
    '(document:keydown.arrowleft)': 'onLeftKeydownHandler()',
  },
})
export class GalleryComponent {
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly filter = input.required<APIGalleryFilter>();

  readonly current = input.required<null | string>();
  protected readonly current$ = toObservable(this.current);

  readonly galleryPrefix = input.required<string[]>();
  readonly picturePrefix = input.required<string[]>();
  readonly pictureSelected = output<null | Picture>();

  protected readonly currentFilter$ = toObservable(this.filter).pipe(
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    debounceTime(50),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly identity$ = this.current$.pipe(
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly gallery$: Observable<Gallery> = combineLatest([
    this.currentFilter$.pipe(switchMap((filter) => (filter ? of(new Gallery(filter, [] as Picture[])) : EMPTY))),
    this.identity$.pipe(switchMap((identity) => (identity ? of(identity) : EMPTY))),
  ]).pipe(
    switchMap(([gallery, identity]) => {
      if (!gallery.getGalleryItem(identity)) {
        return this.#picturesClient
          .getGallery(
            new GalleryRequest({
              pictureIdentity: identity,
              request: gallery.filterParams(this.#languageService.language),
            }),
          )
          .pipe(
            catchError((response: unknown) => {
              if (response instanceof GrpcStatusEvent && response.statusCode === StatusCode.NOT_FOUND) {
                this.#router.navigate(['/error-404'], {
                  skipLocationChange: true,
                });
              } else {
                this.#toastService.handleError(response);
              }
              return EMPTY;
            }),
            tap((response) => {
              gallery.applyResponse(response);
            }),
            map(() => ({gallery, identity})),
          );
      }
      return of({gallery, identity});
    }),
    tap(({gallery, identity}) => {
      const index = gallery.getItemIndex(identity);
      gallery.current = index;
      const currentItem = gallery.getItemByIndex(index);
      this.pictureSelected.emit(currentItem);
      this.#cdr.markForCheck();
    }),
    map(({gallery}) => gallery),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  onKeydownHandler() {
    this.current$
      .pipe(
        take(1),
        switchMap((current) => (current ? this.#router.navigate(this.picturePrefix().concat([current])) : EMPTY)),
      )
      .subscribe();
  }

  onRightKeydownHandler() {
    this.gallery$.pipe(take(1)).subscribe((gallery) => {
      if (gallery.current + 1 < gallery.items.length) {
        this.navigateToIndex(gallery.current + 1, gallery);
      }
    });
  }

  onLeftKeydownHandler() {
    this.gallery$.pipe(take(1)).subscribe((gallery) => {
      if (gallery.current > 0) {
        this.navigateToIndex(gallery.current - 1, gallery);
      }
    });
  }

  private loadPage$(page: number, gallery: Gallery): Observable<GalleryResponse> {
    const request = gallery.filterParams(this.#languageService.language);
    request.options!.status = gallery.status;
    request.page = page;

    return this.#picturesClient.getGallery(new GalleryRequest({request})).pipe(
      catchError((response: unknown) => {
        if (response instanceof GrpcStatusEvent && response.statusCode === StatusCode.NOT_FOUND) {
          this.#router.navigate(['/error-404'], {
            skipLocationChange: true,
          });
        } else {
          this.#toastService.handleError(response);
        }
        return EMPTY;
      }),
      tap((response) => {
        gallery.applyResponse(response);
      }),
    );
  }

  protected navigateToIndex(index: number, gallery: Gallery): void {
    const item = gallery.getItemByIndex(index);
    if (item) {
      this.#router.navigate(this.galleryPrefix().concat([item.identity]));
      return;
    }

    const page = gallery.getGalleryPageNumberByIndex(index);
    this.loadPage$(page, gallery).subscribe(() => {
      const sitem = gallery.getItemByIndex(index);
      if (sitem) {
        this.#router.navigate(this.galleryPrefix().concat([sitem.identity]));
      }
    });
  }
}
