import type {Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
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
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {map, of} from 'rxjs';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {CategoriesListItemComponent} from '../../list-item.component';
import {CategoriesService} from '../../service';

interface PictureRoute {
  picture: Picture;
  route: string[];
}

@Component({
  selector: 'app-categories-category-item',
  imports: [CategoriesListItemComponent, RouterLink, PaginatorComponent, RemarkModule],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CategoriesCategoryItemComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #categoriesService = inject(CategoriesService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  // No params function: categoryPipe$'s Observable is itself long-lived and already reacts to
  // route param changes internally (see CategoriesService.categoryPipe$).
  protected readonly categoryDataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'categories-category-item-data',
    stream: () => this.#categoriesService.categoryPipe$(requireRouteParent(this.#route)),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that; this resource's error has no dedicated slot in the template, so every
  // consumer below just degrades to its "no data yet" branch (same as while still loading).
  protected readonly categoryData = computed(() =>
    this.categoryDataResource.hasValue() ? this.categoryDataResource.value() : undefined,
  );

  protected readonly current = computed(() => this.categoryData()?.current);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((query) => parseInt(query.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly itemParentsResource = rxResource({
    id: 'categories-category-item-parents',
    params: () => {
      const data = this.categoryData();

      return data?.current
        ? {category: data.category, current: data.current, page: this.#page(), pathCatnames: data.pathCatnames}
        : undefined;
    },
    stream: ({params: {category, current, page, pathCatnames}}) =>
      this.#itemsClient
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
            items: (response.items ?? []).map((itemParent) => ({
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
        ),
  });

  // Same reasoning as categoryData above.
  protected readonly itemParentsData = computed(() =>
    this.itemParentsResource.hasValue() ? this.itemParentsResource.value() : undefined,
  );

  protected readonly picturesResource = rxResource({
    id: 'categories-category-item-pictures',
    params: () => {
      const data = this.categoryData();
      const itemParents = this.itemParentsData();

      return data?.current && itemParents
        ? {
            category: data.category,
            current: data.current,
            itemParentsCount: itemParents.items.length,
            pathCatnames: data.pathCatnames,
          }
        : undefined;
    },
    stream: ({params: {category, current, itemParentsCount, pathCatnames}}) => {
      if (current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY || itemParentsCount <= 0) {
        return of<PictureRoute[]>([]);
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
          map((response) =>
            (response.items ?? []).map((picture) => ({
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
    },
  });

  protected readonly currentRouterLinkPrefix = computed(() => {
    const data = this.categoryData();
    if (!data?.category || !data.current) {
      return null;
    }

    if (data.current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
      return ['/category', data.current.catname];
    }

    return ['/category', data.category.catname, ...data.pathCatnames];
  });

  protected readonly itemResource = rxResource({
    id: 'categories-category-item-single',
    params: () => {
      const data = this.categoryData();
      const itemParents = this.itemParentsData();

      return data?.current && itemParents
        ? {current: data.current, itemParentsCount: itemParents.items.length}
        : undefined;
    },
    stream: ({params: {current, itemParentsCount}}) => {
      if (current.itemTypeId === ItemType.ITEM_TYPE_CATEGORY || itemParentsCount > 0) {
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
    },
  });

  // Same reasoning as categoryData above.
  protected readonly itemData = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  constructor() {
    effect(() => {
      const current = this.categoryData()?.current;
      this.#pageEnv.set({
        pageId: 22,
        title: current?.nameText ?? '',
      });
    });
  }

  protected readonly errorMessage = errorMessage;
}
