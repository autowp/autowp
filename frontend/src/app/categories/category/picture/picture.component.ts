import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router} from '@angular/router';
import {
  CommentsType,
  ItemParentCacheListOptions,
  ItemType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, combineLatest, EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../../../comments/comments/comments.component';
import {PictureComponent} from '../../../picture/picture.component';
import {CategoriesService} from '../../service';

@Component({
  selector: 'app-category-picture',
  imports: [CommentsComponent, AsyncPipe, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CategoryPictureComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #router = inject(Router);
  readonly #categoriesService = inject(CategoriesService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #changed$ = new BehaviorSubject<void>(void 0);

  readonly #identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    switchMap((identity) => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(identity);
    }),
  );

  readonly #categoryData$ = this.#categoriesService.categoryPipe$(this.#route.parent!.parent!).pipe(
    switchMap((data) => {
      if (!data.current) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(data);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly currentRouterLinkPrefix$ = this.#categoryData$.pipe(
    map(({category, current, pathCatnames}) => {
      if (!category) {
        return null;
      }

      if (current?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
        return ['/category', current.catname, 'pictures'];
      }

      return ['/category', category.catname, ...pathCatnames, 'pictures'];
    }),
  );

  protected readonly picture$: Observable<Picture> = combineLatest([
    this.#categoryData$,
    this.#identity$,
    this.#changed$,
  ]).pipe(
    switchMap(([{current}, identity]) => {
      if (!current) {
        return EMPTY;
      }

      return this.#picturesClient.getPicture(
        new PicturesRequest({
          fields: new PictureFields({
            copyrights: true,
            image: true,
            moderVoted: true,
            nameHtml: true,
            nameText: true,
            paginator: new PicturesRequest({
              options: new PictureListOptions({
                pictureItem: new PictureItemListOptions({
                  itemParentCacheAncestor: new ItemParentCacheListOptions({
                    parentId: current.id,
                  }),
                  typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                }),
              }),
              order: PicturesRequest.Order.ORDER_PERSPECTIVES,
            }),
            pictureModerVotes: new PictureModerVoteRequest(),
            previewLarge: true,
            replaceable: new PicturesRequest({
              fields: new PictureFields({nameHtml: true}),
            }),
            rights: true,
            subscribed: true,
            votes: true,
          }),
          language: this.#languageService.language,
          options: new PictureListOptions({
            identity,
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({
                parentId: current.id,
              }),
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
        }),
      );
    }),
    switchMap((picture) => {
      if (!picture) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(picture);
    }),
    tap((picture) => {
      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: 187,
        title: picture.nameText,
      });
    }),
  );

  protected readonly currentRouterLinkGallery$ = combineLatest([this.#categoryData$, this.#identity$]).pipe(
    map(([{category, current, pathCatnames}, identity]) => {
      if (!category || !identity) {
        return null;
      }

      if (current?.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
        return ['/category', current.catname, 'gallery', identity];
      }

      return ['/category', category.catname, ...pathCatnames, 'gallery', identity];
    }),
  );

  protected readonly CommentsType = CommentsType;

  protected reloadPicture() {
    this.#changed$.next();
  }
}
