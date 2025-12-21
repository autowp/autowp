import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  CommentsType,
  ItemParentCacheListOptions,
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
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../../../../comments/comments/comments.component';
import {PictureComponent} from '../../../../picture/picture.component';
import {ToastsService} from '../../../../toasts/toasts.service';
import {CatalogueService} from '../../../catalogue-service';

@Component({
  selector: 'app-catalogue-vehicles-pictures-picture',
  imports: [RouterLink, CommentsComponent, AsyncPipe, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesPicturesPictureComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogueService = inject(CatalogueService);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  readonly #changed$ = new BehaviorSubject<void>(void 0);

  readonly #identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    debounceTime(10),
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

  readonly #exact$ = this.#route.data.pipe(
    map((params) => !!params['exact']),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #catalogue$ = this.#catalogueService.resolveCatalogue$(this.#route).pipe(
    switchMap((data) => {
      if (!data?.brand || !data.path || data.path.length <= 0) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(data);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #routerLink$ = combineLatest([this.#catalogue$, this.#exact$]).pipe(
    map(([{brand, path}, exact]) => [
      '/',
      brand.catname,
      ...path.map((node) => node.catname),
      ...(exact ? ['exact'] : []),
    ]),
  );

  protected readonly CommentsType = CommentsType;

  protected readonly brand$: Observable<APIItem> = this.#catalogue$.pipe(map(({brand}) => brand));

  protected readonly breadcrumbs$ = this.#catalogue$.pipe(
    map(({brand, path}) => CatalogueService.pathToBreadcrumbs(brand, path)),
  );

  protected readonly picturesRouterLink$ = this.#routerLink$.pipe(map((routerLink) => [...routerLink, 'pictures']));

  protected readonly galleryPictureRouterLink$ = combineLatest([this.#identity$, this.#routerLink$]).pipe(
    map(([identity, routerLink]) => [...routerLink, 'gallery', identity]),
  );

  protected readonly picture$: Observable<Picture> = combineLatest([
    this.#catalogue$,
    this.#identity$,
    this.#changed$,
  ]).pipe(
    switchMap(([{path}, identity]) => {
      const itemID = path[path.length - 1].itemId;

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
                    parentId: itemID,
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
                parentId: itemID,
              }),
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
        }),
      );
    }),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
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
        pageId: 34,
        title: picture.nameText,
      });
    }),
  );

  protected reloadPicture() {
    this.#changed$.next();
  }
}
