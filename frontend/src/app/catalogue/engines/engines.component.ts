import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
  Pages,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {convertChildsCounts} from '../catalogue-service';

@Component({
  selector: 'app-catalogue-engines',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './engines.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueEnginesComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly brand$ = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        return EMPTY;
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
              this.#router.navigate(['/error-404'], {
                skipLocationChange: true,
              });
              return EMPTY;
            }
            return of(response.items[0]);
          }),
        );
    }),
    tap((brand) => {
      this.#pageEnv.set({
        pageId: 208,
        title: $localize`${brand.nameOnly} Engines`,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$: Observable<{items: CatalogueListItem[]; paginator: Pages | undefined}> = combineLatest([
    this.brand$,
    this.#page$,
  ]).pipe(
    switchMap(([brand, page]) =>
      combineLatest([
        this.#itemsClient.getItemParents(
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
        ),
        of(brand),
      ]),
    ),
    map(([response, brand]) => {
      const items: CatalogueListItem[] = (response.items || []).map((item): CatalogueListItem => {
        const largeFormat = !!item.item?.previewPictures?.largeFormat;

        const routerLink = ['/', brand.catname, item.catname];

        const pictures: CatalogueListItemPicture[] = (item.item?.previewPictures?.pictures || []).map(
          (picture, idx) => {
            let thumb = null;
            if (picture.picture) {
              thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
            }
            return {
              picture: picture?.picture ? picture.picture : null,
              routerLink: picture?.picture ? routerLink.concat(['pictures', picture.picture.identity]) : [],
              thumb,
            };
          },
        );

        return {
          acceptedPicturesCount: item.item?.acceptedPicturesCount,
          canEditSpecs: !!item.item?.canEditSpecs,
          categories: item.item?.categories || undefined,
          childsCounts: item.item?.childsCounts ? convertChildsCounts(item.item.childsCounts) : null,
          description: item.item?.description || null,
          design: undefined,
          details: {
            count: item.item?.childsCount || 0,
            routerLink,
          },
          engineVehicles: item.item?.engineVehicles,
          hasText: !!item.item?.hasText,
          id: item.item?.id || '',
          itemTypeId: item.item?.itemTypeId || 0,
          nameDefault: item.item?.nameDefault || '',
          nameHtml: item.item?.nameHtml || '',
          picturesRouterLink: routerLink.concat(['pictures']),
          previewPictures: {
            largeFormat: !!item.item?.previewPictures?.largeFormat,
            pictures,
          },
          produced: item.item?.produced?.value,
          producedExactly: item.item?.producedExactly || null,
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

  protected readonly title$ = this.brand$.pipe(map((brand) => $localize`${brand.nameOnly} Engines`));
}
