import type {Item, Pages, Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemParentCacheListOptions,
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
import {errorMessage, isNotFoundError} from 'app/grpc';
import {map} from 'rxjs';

import type {Breadcrumbs, CatalogueData} from '../../catalogue-service';

import {chunkBy} from '../../../chunk';
import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';
import {CatalogueService, convertChildsCounts} from '../../catalogue-service';
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

  protected readonly canAcceptPicture$ = this.#auth.hasRole$(Role.PICTURES_MODER);
  protected readonly canAddItem$ = this.#auth.hasRole$(Role.CARS_MODER);
  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });
  readonly #pathParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('path'))), {
    requireSync: true,
  });
  readonly #typeParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('type'))), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  readonly #exact = toSignal(this.#route.data.pipe(map((data) => !!data['exact'])), {requireSync: true});

  // Missing/unresolvable brand or path segments are surfaced by resolveCatalogue$ itself as a
  // NOT_FOUND resource error - see the constructor effect() below, which is the single place that
  // navigates off this resource's error() signal.
  //
  // `id` is suffixed with the brand/path/type route params read once at construction time - see
  // the identical note on CatalogueVehiclesComponent.catalogueResource in ../vehicles.component.ts.
  protected readonly catalogueResource = rxResource({
    id: `catalogue-vehicles-pictures-catalogue-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    stream: (): Observable<CatalogueData> => this.#catalogueService.resolveCatalogue$(this.#route),
  });

  // Reading a resource's value() while it's in an error state throws - every other computed()
  // and resource params() below that needs catalogueResource's data reads it through this signal
  // instead of the resource directly, so a real (non-NOT_FOUND) failure here degrades the rest of
  // the page to its "no data yet" branches instead of taking the whole component down. The error
  // itself is shown inline in the template (pictures.component.html), not swallowed here.
  protected readonly catalogueData = computed(() =>
    this.catalogueResource.hasValue() ? this.catalogueResource.value() : undefined,
  );

  protected readonly brand = computed(() => this.catalogueData()?.brand);

  protected readonly breadcrumbs = computed<Breadcrumbs[] | undefined>(() => {
    const data = this.catalogueData();
    return data ? CatalogueService.pathToBreadcrumbs(data.brand, data.path) : undefined;
  });

  protected readonly routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueData();
    return data ? ['/', data.brand.catname, ...data.path.map((node) => node.catname)] : undefined;
  });

  protected readonly picturesRouterLink = computed(() => {
    const routerLink = this.routerLink();
    return routerLink ? [...routerLink, ...(this.#exact() ? ['exact'] : []), 'pictures'] : undefined;
  });

  protected readonly item = computed<Item | undefined>(() => {
    const data = this.catalogueData();
    const item = data?.path[data.path.length - 1].item;
    return item ?? undefined;
  });

  // `id` includes `exact` on top of the brand/path/type route params: `.../pictures` and
  // `.../exact/pictures` are sibling route configs sharing this component (so switching between
  // them - e.g. via the "all pictures" link on the vehicle page - recreates the instance), and
  // `exact` materially changes the query below (itemId vs itemParentCacheAncestor). Without it the
  // new instance would seed from the other variant's TransferState entry and stick with it, since
  // params() never changes afterwards.
  protected readonly picturesResource = rxResource({
    id: `catalogue-vehicles-pictures-list-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}-${this.#exact() ? 'exact' : ''}`,
    params: () => {
      const item = this.item();
      return item ? {exact: this.#exact(), item, page: this.#page()} : undefined;
    },
    stream: ({params: {exact, item, page}}): Observable<{paginator: Pages | undefined; pictures: Picture[][]}> =>
      this.#picturesClient
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
                itemId: exact ? item.id : undefined,
                itemParentCacheAncestor: exact ? undefined : new ItemParentCacheListOptions({parentId: item.id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_PERSPECTIVES,
            page: page,
            paginator: true,
          }),
        )
        .pipe(
          map((response) => ({
            paginator: response.paginator,
            pictures: chunkBy(response.items ?? [], 4),
          })),
        ),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const item = this.item();
      if (!item) {
        return;
      }

      this.#pageEnv.set({
        pageId: 34,
        title: $localize`All pictures of ${item.nameText}`,
      });
    });
  }

  protected getItemTypeTranslation(id: number, type: string) {
    return getItemTypeTranslation(id, type);
  }

  protected readonly convertChildsCounts = convertChildsCounts;
  protected readonly errorMessage = errorMessage;
}
