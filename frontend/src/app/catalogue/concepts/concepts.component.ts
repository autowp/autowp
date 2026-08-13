import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Item,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  Pages,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {Observable, of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-catalogue-concepts',
  imports: [RouterLink, PaginatorComponent, CatalogueListItemComponent],
  templateUrl: './concepts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueConceptsComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  // Missing catname / empty list response are both surfaced as a NOT_FOUND resource error rather
  // than an imperative Router.navigate() inside the stream - see the constructor effect() below,
  // which is the single place that navigates off this resource's error() signal.
  protected readonly brandResource = rxResource({
    id: `catalogue-concepts-brand-${this.#catname() ?? ''}`,
    params: () => this.#catname(),
    stream: ({params: catname}): Observable<Item> => {
      if (!catname) {
        return notFoundError();
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
            }),
          }),
        )
        .pipe(
          switchMap((response) => {
            if (!response.items || response.items.length <= 0) {
              return notFoundError();
            }
            return of(response.items[0]);
          }),
        );
    },
  });

  protected readonly title = computed<string | undefined>(() => {
    const brand = this.brandResource.hasValue() ? this.brandResource.value() : undefined;
    return brand ? $localize`${brand.nameOnly} concepts & prototypes` : undefined;
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const title = this.title();
      if (title === undefined) {
        return;
      }

      this.#pageEnv.set({
        pageId: 37,
        title,
      });
    });
  }

  protected readonly dataResource = rxResource({
    id: `catalogue-concepts-data-${this.#catname() ?? ''}`,
    params: () => ({brand: this.brandResource.value(), page: this.#page()}),
    stream: ({
      params: {brand, page},
    }): Observable<undefined | {items: CatalogueListItem[]; paginator: Pages | undefined}> => {
      if (!brand) {
        return of(undefined);
      }

      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              acceptedPicturesCount: true,
              canEditSpecs: true,
              categories: new ItemsRequest({
                fields: new ItemFields({nameHtml: true}),
              }),
              childsCount: true,
              description: true,
              design: true,
              engineVehicles: new ItemsRequest({
                fields: new ItemFields({nameHtml: true, route: true}),
              }),
              hasText: true,
              nameDefault: true,
              nameHtml: true,
              previewPictures: new PreviewPicturesRequest({
                pictures: new PicturesRequest({
                  options: new PictureListOptions({
                    pictureItem: new PictureItemListOptions({typeId: PictureItemType.PICTURE_ITEM_CONTENT}),
                    status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                  }),
                }),
              }),
              route: true,
              routeBrandId: brand.id,
              specsRoute: true,
              twins: new ItemsRequest(),
            }),
            language: this.#languageService.language,
            limit: 7,
            options: new ItemListOptions({
              ancestor: new ItemParentCacheListOptions({parentId: brand.id}),
              isConcept: true,
              isNotConceptInherited: true,
            }),
            order: ItemsRequest.Order.AGE,
            page,
          }),
        )
        .pipe(
          map((response) => {
            const items: CatalogueListItem[] = (response.items || []).map((item) => {
              const largeFormat = !!item.previewPictures?.largeFormat;

              const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map(
                (picture, idx) => {
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture?.picture ? picture.picture : null,
                    routerLink:
                      item.route && picture?.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.acceptedPicturesCount,
                canEditSpecs: item.canEditSpecs,
                categories: item.categories,
                childsCounts: null,
                description: item.description,
                design: item.design,
                details: {
                  count: item.childsCount,
                  routerLink: item.route,
                },
                engineVehicles: item.engineVehicles,
                hasText: item.hasText,
                id: item.id,
                itemTypeId: item.itemTypeId,
                nameDefault: item.nameDefault,
                nameHtml: item.nameHtml,
                picturesRouterLink: item.route.length ? item.route.concat(['pictures']) : null,
                previewPictures: {
                  largeFormat: !!item.previewPictures?.largeFormat,
                  pictures,
                },
                produced: item.produced?.value,
                producedExactly: item.producedExactly,
                specsRouterLink: null,
              };
            });

            return {
              items,
              paginator: response.paginator,
            };
          }),
        );
    },
  });
}
