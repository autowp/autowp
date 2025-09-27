import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIImage,
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemRequest,
  ItemsRequest,
  ItemType,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-factory-items',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './items.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FactoryItemsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly factory$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((id) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id,
          language: this.#languageService.language,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    switchMap((factory) => {
      if (!factory || factory.itemTypeId !== ItemType.ITEM_TYPE_FACTORY) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      this.#pageEnv.set({pageId: 182});

      return of(factory);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly items$ = combineLatest([this.#page$, this.factory$]).pipe(
    switchMap(([page, factory]) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            canEditSpecs: true,
            categories: new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
            }),
            childsCount: true,
            description: true,
            design: true,
            engineVehicles: new ItemsRequest({
              fields: new ItemFields({nameHtml: true, route: true}),
            }),
            hasChildSpecs: true,
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
            route: true,
            specsRoute: true,
            twins: new ItemsRequest(),
          }),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            relatedGroupsOf: factory.id,
          }),
          order: ItemsRequest.Order.AGE,
          page,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
    map((data) => ({
      items: (data.items || []).map((item) => {
        const largeFormat = !!item.previewPictures?.largeFormat;

        const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
          let thumb: APIImage | undefined = undefined;
          if (picture.picture) {
            thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
          }
          return {
            picture: picture.picture ? picture.picture : null,
            routerLink: item.route && picture.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
            thumb: thumb,
          };
        });

        return {
          acceptedPicturesCount: item.acceptedPicturesCount,
          canEditSpecs: item.canEditSpecs,
          childsCounts: null,
          description: item.description,
          design: item.design,
          details: {
            count: item.childsCount,
            routerLink: item.route,
          },
          engineVehicles: item.engineVehicles,
          hasText: item.hasText,
          id: item.id,
          itemTypeId: item.itemTypeId,
          nameDefault: item.nameDefault,
          nameHtml: item.nameHtml,
          picturesRouterLink: item.route.length ? item.route.concat(['pictures']) : undefined,
          previewPictures: {
            largeFormat: !!item.previewPictures?.largeFormat,
            pictures,
          },
          produced: item.produced?.value,
          producedExactly: item.producedExactly,
          specsRouterLink:
            (item.hasSpecs || item.hasChildSpecs) && item.route.length ? item.route.concat(['specifications']) : null,
        } as CatalogueListItem;
      }),
      paginator: data.paginator,
    })),
  );
}
