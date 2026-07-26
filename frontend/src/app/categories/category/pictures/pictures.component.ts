import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {
  ItemParentCacheListOptions,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {chunkBy} from 'app/chunk';
import {PaginatorComponent} from 'app/paginator/paginator/paginator.component';
import {ThumbnailComponent} from 'app/thumbnail/thumbnail/thumbnail.component';
import {of} from 'rxjs';
import {map} from 'rxjs/operators';

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

  protected readonly categoryResource = rxResource({
    stream: () => this.#categoriesService.categoryPipe$(this.#route.parent!.parent!),
  });

  protected readonly picturesResource = rxResource({
    params: () => ({categoryData: this.categoryResource.value(), page: this.#page()}),
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
            const pics: PictureRoute[] = (items || []).map((pic) => ({
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
      if (this.picturesResource.value()) {
        this.#pageEnv.set({pageId: 22});
      }
    });
  }
}
