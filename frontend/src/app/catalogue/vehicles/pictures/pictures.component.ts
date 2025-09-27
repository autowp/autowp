import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemParent,
  ItemParentCacheListOptions,
  Pages,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {ItemHeaderComponent} from '@utils/item-header/item-header.component';
import {getItemTypeTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, filter, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {chunkBy} from '../../../chunk';
import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../../toasts/toasts.service';
import {Breadcrumbs, CatalogueService, convertChildsCounts} from '../../catalogue-service';
import {CatalogueItemMenuComponent} from '../../item-menu/item-menu.component';

@Component({
  selector: 'app-catalogue-vehicles-pictures',
  imports: [
    RouterLink,
    ItemHeaderComponent,
    CatalogueItemMenuComponent,
    PaginatorComponent,
    AsyncPipe,
    ThumbnailComponent,
  ],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesPicturesComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogueService = inject(CatalogueService);
  readonly #auth = inject(AuthService);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  protected readonly canAcceptPicture$ = this.#auth.hasRole$(Role.PICTURES_MODER);
  protected readonly canAddItem$ = this.#auth.hasRole$(Role.CARS_MODER);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #catalogue$: Observable<{brand: APIItem; path: ItemParent[]; type: string}> = this.#catalogueService
    .resolveCatalogue$(this.#route)
    .pipe(
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

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #exact$ = this.#route.data.pipe(
    map((params) => !!params['exact']),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly brand$: Observable<APIItem> = this.#catalogue$.pipe(map(({brand}) => brand));

  protected readonly breadcrumbs$: Observable<Breadcrumbs[]> = this.#catalogue$.pipe(
    map(({brand, path}) => CatalogueService.pathToBreadcrumbs(brand, path)),
  );

  protected readonly routerLink$: Observable<string[]> = this.#catalogue$.pipe(
    map(({brand, path}) => ['/', brand.catname, ...path.map((node) => node.catname)]),
  );

  protected readonly picturesRouterLink$: Observable<string[]> = combineLatest([this.routerLink$, this.#exact$]).pipe(
    map(([routerLink, exact]) => [...routerLink, ...(exact ? ['exact'] : []), 'pictures']),
  );

  protected readonly item$: Observable<APIItem> = this.#catalogue$.pipe(
    map(({path}) => path[path.length - 1].item),
    filter((item) => !!item),
    tap((item: APIItem) => {
      this.#pageEnv.set({
        pageId: 34,
        title: $localize`All pictures of ${item.nameText}`,
      });
    }),
  );

  protected readonly pictures$: Observable<{paginator: Pages | undefined; pictures: Picture[][]}> = combineLatest([
    this.#exact$,
    this.item$,
    this.#page$,
  ]).pipe(
    switchMap(([exact, item, page]) =>
      this.#picturesClient.getPictures(
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
              itemId: exact ? item.id : undefined,
              itemParentCacheAncestor: exact ? undefined : new ItemParentCacheListOptions({parentId: item.id}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_PERSPECTIVES,
          page: page,
          paginator: true,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
    map((response) => ({
      paginator: response.paginator,
      pictures: chunkBy(response.items || [], 4),
    })),
  );

  protected getItemTypeTranslation(id: number, type: string) {
    return getItemTypeTranslation(id, type);
  }

  protected readonly convertChildsCounts = convertChildsCounts;
}
