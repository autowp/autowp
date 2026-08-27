import type {OnInit} from '@angular/core';
import type {DfDistance, Item, Picture} from '@grpc/spec.pb';
import type {NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  DeleteSimilarRequest,
  DfDistanceFields,
  DfDistanceListOptions,
  DfDistanceRequest,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbTypeahead} from '@ng-bootstrap/ng-bootstrap';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';
import {PaginatorComponent} from 'app/paginator/paginator/paginator.component';
import {ThumbnailComponent} from 'app/thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, debounceTime, EMPTY, map, of, switchMap} from 'rxjs';

const nonRemovingStatuses = [PictureStatus.PICTURE_STATUS_ACCEPTED, PictureStatus.PICTURE_STATUS_INBOX];

const similarPictureFields = () =>
  new PictureFields({
    nameHtml: true,
    replaceable: new PicturesRequest({fields: new PictureFields({thumbMedium: true})}),
    thumbMedium: true,
  });

const unresolvedSimilarPictureListOptions = () =>
  new PictureListOptions({
    dfDistance: new DfDistanceListOptions({dstPicture: new PictureListOptions({statuses: nonRemovingStatuses})}),
    statuses: nonRemovingStatuses,
  });

@Component({
  selector: 'app-moder-pictures-similar',
  imports: [PaginatorComponent, RouterLink, ThumbnailComponent, NgbTypeahead, FormsModule, ReactiveFormsModule],
  templateUrl: './similar.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerPicturesSimilarComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly brandID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('brand_id') ?? '')), {
    requireSync: true,
  });

  protected readonly brandQuery = new FormControl<string>('', {nonNullable: true});

  protected readonly hideLoadingKey = signal<null | string>(null);

  protected readonly brandsDataSource: (text$: Observable<string>) => Observable<Item[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        return this.#itemsClient
          .list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true, nameText: true}),
              limit: 10,
              options: new ItemListOptions({
                autocomplete: query,
                descendant: new ItemParentCacheListOptions({
                  pictureItemsByItemId: new PictureItemListOptions({
                    pictures: unresolvedSimilarPictureListOptions(),
                  }),
                }),
                typeId: ItemType.ITEM_TYPE_BRAND,
              }),
            }),
          )
          .pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((response) => response.items ?? []),
          );
      }),
    );

  protected readonly picturesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the brand id read once at construction time - a static id would let a second
    // instance of this component, created by navigating away and back with a different
    // `?brand_id=` before Angular's whenStable() ever resolves, match TransferState's
    // still-present entry from the first brand and seed itself with the wrong data.
    id: `moder-pictures-similar-${this.brandID()}`,
    params: () => ({brandID: this.brandID(), page: this.#page()}),
    stream: ({params: {brandID, page}}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: Object.assign(similarPictureFields(), {
              dfDistance: new DfDistanceRequest({
                fields: new DfDistanceFields({
                  dstPicture: new PicturesRequest({fields: similarPictureFields()}),
                }),
                limit: 5,
              }),
            }),
            limit: 12,
            options: Object.assign(unresolvedSimilarPictureListOptions(), {
              pictureItem: brandID
                ? new PictureItemListOptions({
                    itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brandID}),
                  })
                : undefined,
            }),
            order: PicturesRequest.Order.ORDER_DF_DISTANCE_SIMILARITY,
            page,
            paginator: true,
          }),
        )
        .pipe(
          map((response) => ({
            paginator: response.paginator,
            pictures: response.items ?? [],
          })),
        ),
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_PICTURES_SIMILAR,
      title: $localize`Similar pictures`,
    });

    if (this.brandID() && !this.brandQuery.value) {
      this.brandQuery.setValue('#' + this.brandID());
    }
  }

  protected brandFormatter(x: Item) {
    return x.nameText;
  }

  protected brandOnSelect(e: NgbTypeaheadSelectItemEvent<Item>): void {
    void this.#router.navigate([], {
      queryParams: {brand_id: e.item.id},
      queryParamsHandling: 'merge',
    });
  }

  protected clearBrand(): void {
    this.brandQuery.setValue('');
    void this.#router.navigate([], {
      queryParams: {brand_id: null},
      queryParamsHandling: 'merge',
    });
  }

  protected hideSimilar(picture: Picture, dfDistance: DfDistance) {
    const key = `${picture.id}-${dfDistance.dstPictureId}`;
    this.hideLoadingKey.set(key);

    this.#picturesClient
      .deleteSimilar(new DeleteSimilarRequest({id: picture.id, similarPictureId: dfDistance.dstPictureId}))
      .pipe(
        catchError((error: unknown) => {
          this.hideLoadingKey.set(null);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.hideLoadingKey.set(null);
          this.picturesResource.reload();
        },
      });
  }

  protected readonly errorMessage = errorMessage;
}
