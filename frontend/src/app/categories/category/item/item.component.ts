import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
  Pages,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ToastsService} from '../../../toasts/toasts.service';
import {CategoriesListItemComponent} from '../../list-item.component';
import {CategoriesService} from '../../service';

interface PictureRoute {
  picture: Picture;
  route: string[];
}

@Component({
  selector: 'app-categories-category-item',
  imports: [MarkdownComponent, CategoriesListItemComponent, RouterLink, PaginatorComponent, AsyncPipe],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoriesCategoryItemComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #categoriesService = inject(CategoriesService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #categoryData$ = this.#categoriesService.categoryPipe$(this.#route.parent!).pipe(
    tap(({current}) => {
      this.#pageEnv.set({
        pageId: 22,
        title: current?.nameText || '',
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((query) => parseInt(query.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly itemParents$: Observable<{
    items: {item: ItemParent; parentRouterLink: string[]}[];
    paginator: Pages | undefined;
  }> = combineLatest([this.#categoryData$, this.#page$]).pipe(
    switchMap(([{category, current, pathCatnames}, page]) => {
      if (!current) {
        return EMPTY;
      }

      return this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                acceptedPicturesCount: true,
                canEditSpecs: true,
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
                specsRoute: true,
                twins: new ItemsRequest(),
              }),
            }),
            language: this.#languageService.language,
            limit: 10,
            options: new ItemParentListOptions({
              parentId: current.id,
            }),
            order: ItemParentsRequest.Order.CATEGORIES_FIRST,
            page,
          }),
        )
        .pipe(
          map((response) => ({
            items: (response.items || []).map((itemParent) => ({
              item: itemParent,
              parentRouterLink: [
                '/category',
                ...(itemParent.item?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY
                  ? [itemParent.item.catname]
                  : // eslint-disable-next-line sonarjs/no-nested-conditional
                    category
                    ? [category.catname, ...pathCatnames, itemParent.catname]
                    : []),
              ],
            })),
            paginator: response.paginator,
          })),
        );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly pictures$: Observable<PictureRoute[]> = combineLatest([
    this.#categoryData$,
    this.itemParents$,
  ]).pipe(
    switchMap(([{category, current, pathCatnames}, itemParents]) => {
      if (!current) {
        return EMPTY;
      }

      if (current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY || itemParents.items.length <= 0) {
        return of([]);
      }

      return this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({nameText: true, thumbMedium: true}),
            language: this.#languageService.language,
            limit: 4,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemId: current.id,
              }),
            }),
          }),
        )
        .pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) =>
            (response.items || []).map((picture) => ({
              picture,
              route: [
                '/category',
                category ? category.catname : '',
                ...(current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY ? [] : pathCatnames),
                'pictures',
                picture.identity,
              ],
            })),
          ),
        );
    }),
  );

  protected readonly currentRouterLinkPrefix$ = this.#categoryData$.pipe(
    map(({category, current, pathCatnames}) => {
      if (!category || !current) {
        return null;
      }

      if (current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
        return ['/category', current.catname];
      }

      return ['/category', category.catname, ...pathCatnames];
    }),
  );

  protected readonly item$: Observable<APIItem | null> = combineLatest([this.#categoryData$, this.itemParents$]).pipe(
    switchMap(([{current}, itemParents]) => {
      if (!current) {
        return EMPTY;
      }

      if (current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY || itemParents.items.length > 0) {
        return of(null);
      }

      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            canEditSpecs: true,
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
            specsRoute: true,
            twins: new ItemsRequest(),
          }),
          id: current.id,
          language: this.#languageService.language,
        }),
      );
    }),
  );

  protected readonly current$: Observable<APIItem | undefined> = this.#categoryData$.pipe(
    map(({current}) => current),
    shareReplay({bufferSize: 1, refCount: false}),
  );
}
