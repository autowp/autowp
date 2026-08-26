import type {Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {
  ItemParentCacheListOptions,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {requireRouteParent} from '@utils/require-route-parent';
import {chunkBy} from 'app/chunk';
import {errorMessage} from 'app/grpc';
import {PaginatorComponent} from 'app/paginator/paginator/paginator.component';
import {ThumbnailComponent} from 'app/thumbnail/thumbnail/thumbnail.component';
import {map, of} from 'rxjs';

import {CategoriesService} from '../../service';

interface PictureRoute {
  picture: Picture;
  route: string[];
}

@Component({
  selector: 'app-categories-category-pictures',
  imports: [PaginatorComponent, ThumbnailComponent],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CategoriesCategoryPicturesComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #categoriesService = inject(CategoriesService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  // Read once at construction time purely to scope the resources' TransferState ids below -
  // categoryResource itself re-derives these reactively from the route inside categoryPipe$.
  readonly #category = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('category'))),
    {
      requireSync: true,
    },
  );
  readonly #path = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('path'))),
    {
      requireSync: true,
    },
  );

  protected readonly categoryResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the ancestor category identity read once at construction time - a static id
    // would let a second instance of this component, created by navigating away and to a
    // different category's pictures URL before Angular's whenStable() ever resolves, match
    // TransferState's still-present entry from the first category and seed itself with the wrong
    // data.
    id: `categories-category-pictures-category-${this.#category() ?? ''}-${this.#path() ?? ''}`,
    stream: () => this.#categoriesService.categoryPipe$(requireRouteParent(requireRouteParent(this.#route))),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so picturesResource's params() below doesn't blow up on a
  // categoryResource error (surfaced generically by the template instead).
  protected readonly categoryData = computed(() =>
    this.categoryResource.hasValue() ? this.categoryResource.value() : undefined,
  );

  protected readonly picturesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `categories-category-pictures-${this.#category() ?? ''}-${this.#path() ?? ''}`,
    params: () => ({categoryData: this.categoryData(), page: this.#page()}),
    stream: ({params: {categoryData, page}}) => {
      if (!categoryData?.category || !categoryData.current) {
        return of(undefined);
      }
      const {category, current, pathCatnames} = categoryData;

      return this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              commentsCount: true,
              moderVote: true,
              nameHtml: true,
              nameText: true,
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 20,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: current.id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_PERSPECTIVES,
            page,
            paginator: true,
          }),
        )
        .pipe(
          map(({items, paginator}) => {
            const pics: PictureRoute[] = (items ?? []).map((pic) => ({
              picture: pic,
              route: ['/category', category.catname, ...pathCatnames, 'pictures', pic.identity],
            }));

            return {
              paginator,
              pictures: chunkBy(pics, 4),
            };
          }),
        );
    },
  });

  constructor() {
    effect(() => {
      if (this.picturesResource.hasValue()) {
        this.#pageEnv.set({pageId: 22});
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
