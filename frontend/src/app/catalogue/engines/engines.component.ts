import type {Item, Pages} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {convertChildsCounts} from '../catalogue-service';

@Component({
  selector: 'app-catalogue-engines',
  imports: [RouterLink, PaginatorComponent, CatalogueListItemComponent],
  templateUrl: './engines.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueEnginesComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);
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
    id: `catalogue-engines-brand-${this.#catname() ?? ''}`,
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

  // Reading a resource's value() while it's in an error state throws - dataResource's params()
  // below reads brandResource's data through this signal instead of the resource directly, so a
  // real (non-NOT_FOUND) failure here degrades dataResource to its "no data yet" branch instead of
  // taking the whole component down. The error itself is shown inline in the template
  // (engines.component.html), not swallowed here.
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  protected readonly title = computed<string | undefined>(() => {
    const brand = this.brandData();
    return brand ? $localize`${brand.nameOnly} Engines` : undefined;
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        this.#notFound.report();
        return;
      }

      const title = this.title();
      if (title === undefined) {
        return;
      }

      this.#pageEnv.set({
        pageId: PageId.CATALOGUE_ENGINES,
        title,
      });
    });
  }

  protected readonly dataResource = rxResource({
    id: `catalogue-engines-data-${this.#catname() ?? ''}`,
    params: () => ({brand: this.brandData(), page: this.#page()}),
    stream: ({
      params: {brand, page},
    }): Observable<undefined | {items: CatalogueListItem[]; paginator: Pages | undefined}> => {
      if (!brand) {
        return of(undefined);
      }

      return this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                acceptedPicturesCount: true,
                canEditSpecs: true,
                categories: new ItemsRequest({
                  fields: new ItemFields({nameHtml: true}),
                }),
                childsCount: true,
                description: true,
                engineVehicles: new ItemsRequest({
                  fields: new ItemFields({nameHtml: true, route: true}),
                }),
                hasChildSpecs: true,
                hasText: true,
                nameDefault: true,
                nameHtml: true,
                specsRoute: true,
                twins: new ItemsRequest(),
              }),
            }),
            language: this.#languageService.language,
            limit: 7,
            options: new ItemParentListOptions({
              item: new ItemListOptions({
                typeId: ItemType.ITEM_TYPE_ENGINE,
              }),
              parentId: brand.id,
            }),
            order: ItemParentsRequest.Order.AUTO,
            page,
          }),
        )
        .pipe(
          map((response) => {
            const items: CatalogueListItem[] = (response.items ?? []).map((item): CatalogueListItem => {
              const largeFormat = !!item.item?.previewPictures?.largeFormat;

              const routerLink = ['/', brand.catname, item.catname];

              const pictures: CatalogueListItemPicture[] = (item.item?.previewPictures?.pictures ?? []).map(
                (picture, idx) => {
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }
                  return {
                    picture: picture.picture ?? null,
                    routerLink: picture.picture ? routerLink.concat(['pictures', picture.picture.identity]) : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.item?.acceptedPicturesCount,
                canEditSpecs: !!item.item?.canEditSpecs,
                categories: item.item?.categories ?? undefined,
                childsCounts: item.item?.childsCounts ? convertChildsCounts(item.item.childsCounts) : null,
                description: item.item?.description ?? null,
                design: undefined,
                details: {
                  count: item.item?.childsCount ?? 0,
                  routerLink,
                },
                engineVehicles: item.item?.engineVehicles,
                hasText: !!item.item?.hasText,
                id: item.item?.id ?? '',
                itemTypeId: item.item?.itemTypeId ?? 0,
                nameDefault: item.item?.nameDefault ?? '',
                nameHtml: item.item?.nameHtml ?? '',
                picturesRouterLink: routerLink.concat(['pictures']),
                previewPictures: {
                  largeFormat: !!item.item?.previewPictures?.largeFormat,
                  pictures,
                },
                produced: item.item?.produced?.value,
                producedExactly: item.item?.producedExactly ?? null,
                specsRouterLink:
                  item.item?.hasSpecs || item.item?.hasChildSpecs ? routerLink.concat(['specifications']) : null,
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

  protected readonly errorMessage = errorMessage;
}
