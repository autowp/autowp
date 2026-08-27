import type {Image} from '@grpc/spec.pb';
import type {CatalogueListItem, CatalogueListItemPicture} from '@utils/list-item/list-item.component';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
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
import {PageId} from '@services/page-id';
import {CatalogueListItemComponent} from '@utils/list-item/list-item.component';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-factory-items',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './items.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class FactoryItemsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly factoryResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `factory-items-factory-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: id}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          switchMap((factory) => (factory.itemTypeId === ItemType.ITEM_TYPE_FACTORY ? of(factory) : notFoundError())),
        ),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so downstream consumers below don't blow up on a non-NOT_FOUND
  // factoryResource error (surfaced generically by the template instead).
  protected readonly factoryData = computed(() =>
    this.factoryResource.hasValue() ? this.factoryResource.value() : undefined,
  );

  protected readonly itemsResource = rxResource({
    id: `factory-items-list-${this.#itemID()}`,
    params: () => {
      const factory = this.factoryData();

      return factory ? {factoryID: factory.id, page: this.#page()} : undefined;
    },
    stream: ({params: {factoryID, page}}) =>
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
            relatedGroupsOf: factoryID,
          }),
          order: ItemsRequest.Order.AGE,
          page,
        }),
      ),
  });

  protected readonly items = computed(() => {
    const data = this.itemsResource.value();
    if (!data) {
      return null;
    }

    return {
      items: (data.items ?? []).map((item): CatalogueListItem => {
        const largeFormat = !!item.previewPictures?.largeFormat;

        const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures ?? []).map((picture, idx) => {
          let thumb: Image | undefined = undefined;
          if (picture.picture) {
            thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
          }
          return {
            picture: picture.picture ?? null,
            routerLink: picture.picture ? item.route.concat(['pictures', picture.picture.identity]) : [],
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
          picturesRouterLink: item.route.length ? item.route.concat(['pictures']) : null,
          previewPictures: {
            largeFormat: !!item.previewPictures?.largeFormat,
            pictures,
          },
          produced: item.produced?.value,
          producedExactly: item.producedExactly,
          specsRouterLink:
            (item.hasSpecs || item.hasChildSpecs) && item.route.length ? item.route.concat(['specifications']) : null,
        };
      }),
      paginator: data.paginator,
    };
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.factoryResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      if (this.factoryData()) {
        this.#pageEnv.set({pageId: PageId.FACTORY_ITEMS});
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
