import type {Item} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, computed, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {
  GetTreeRequest,
  ItemFields,
  ItemParentCacheListOptions,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  SetUserItemSubscriptionRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {getItemTypeTranslation} from '@utils/translations';
import {isNotFoundError} from 'app/grpc';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, defer, of, tap, throwError} from 'rxjs';

import {ModerItemsItemCatalogue} from './catalogue/moder-items-item-catalogue';
import {ModerItemsItemGdpr} from './gdpr/moder-items-item-gdpr';
import {ModerItemsItemLinks} from './links/moder-items-item-links';
import {ModerItemsItemLogo} from './logo/moder-items-item-logo';
import {ModerItemsItemMeta} from './meta/moder-items-item-meta';
import {ModerItemsItemName} from './name/moder-items-item-name';
import {ModerItemsItemPictures} from './pictures/moder-items-item-pictures';
import {ModerItemsItemTree} from './tree/moder-items-item-tree';
import {ModerItemsItemVehicles} from './vehicles/moder-items-item-vehicles';
interface Tab {
  count: number;
  visible: boolean;
}

@Component({
  selector: 'app-moder-items-item',
  imports: [
    ResourceStatus,
    RouterLink,
    ModerItemsItemMeta,
    ModerItemsItemName,
    ModerItemsItemLogo,
    ModerItemsItemCatalogue,
    ModerItemsItemVehicles,
    ModerItemsItemTree,
    ModerItemsItemPictures,
    ModerItemsItemLinks,
    ModerItemsItemGdpr,
  ],
  templateUrl: './moder-items-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItem {
  readonly #auth = inject(AuthService);
  readonly #notFound = inject(NotFoundService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);

  protected readonly specsAllowed = signal(false);
  protected readonly canEditSpecifications = this.#auth.authenticated;

  protected readonly metaTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly nameTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly logoTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly catalogueTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly vehiclesTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly treeTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly picturesTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly linksTab: Tab = {
    count: 0,
    visible: true,
  };
  protected readonly gdprTab: Tab = {
    count: 0,
    visible: true,
  };

  readonly tab = input('meta', {
    // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing -- Empty ?tab= also selects the default tab.
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) || 'meta',
  });

  readonly id = input('', {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? '',
  });

  protected readonly itemResource = rxResource({
    params: () => this.id(),
    stream: ({params: id}) =>
      defer(() =>
        this.#itemsClient.item(
          new ItemRequest({
            fields: new ItemFields({
              childsCount: true,
              engineVehiclesCount: true,
              exactPicturesCount: true,
              fullName: true,
              itemLanguageCount: true,
              linksCount: true,
              location: true,
              logo: true,
              meta: true,
              nameDefault: true,
              nameHtml: true,
              nameText: true,
              parentsCount: true,
              specificationsCount: true,
              subscription: true,
            }),
            id,
            language: this.#languageService.language,
          }),
        ),
      ).pipe(
        catchError((err: unknown) => {
          this.#toastService.handleError(err);
          if (isNotFoundError(err)) this.#notFound.report();
          return throwError(() => err);
        }),
        tap((item) => {
          this.#pageEnv.set({
            layout: {isAdminPage: true},
            pageId: PageId.MODER_ITEM,
            title: item.nameText,
          });

          const typeID = item.itemTypeId;

          this.specsAllowed.set([ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(typeID));

          this.nameTab.count = item.itemLanguageCount;
          this.logoTab.count = item.logo ? 1 : 0;
          this.catalogueTab.count = item.parentsCount + item.childsCount;
          this.vehiclesTab.count = item.engineVehiclesCount;
          this.picturesTab.count = item.exactPicturesCount;
          this.linksTab.count = item.linksCount;

          this.metaTab.visible = true;
          this.nameTab.visible = true;
          this.catalogueTab.visible = ![ItemType.ITEM_TYPE_COPYRIGHT, ItemType.ITEM_TYPE_MUSEUM].includes(typeID);
          this.treeTab.visible = ![ItemType.ITEM_TYPE_COPYRIGHT, ItemType.ITEM_TYPE_MUSEUM].includes(typeID);
          this.linksTab.visible = [
            ItemType.ITEM_TYPE_BRAND,
            ItemType.ITEM_TYPE_MUSEUM,
            ItemType.ITEM_TYPE_PERSON,
          ].includes(typeID);
          this.logoTab.visible = typeID === ItemType.ITEM_TYPE_BRAND;
          this.gdprTab.visible = typeID === ItemType.ITEM_TYPE_PERSON;
          this.vehiclesTab.visible = typeID === ItemType.ITEM_TYPE_ENGINE;
          this.picturesTab.visible = [
            ItemType.ITEM_TYPE_BRAND,
            ItemType.ITEM_TYPE_COPYRIGHT,
            ItemType.ITEM_TYPE_ENGINE,
            ItemType.ITEM_TYPE_FACTORY,
            ItemType.ITEM_TYPE_MUSEUM,
            ItemType.ITEM_TYPE_PERSON,
            ItemType.ITEM_TYPE_VEHICLE,
          ].includes(typeID);
          this.#cdr.markForCheck();
        }),
      ),
  });
  protected readonly item = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly treeResource = rxResource({
    params: () => {
      const item = this.item();
      return this.tab() === 'tree' && this.treeTab.visible ? (item ?? null) : null;
    },
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : defer(() => {
            const item = params;
            return defer(() =>
              this.#itemsClient.getTree(new GetTreeRequest({id: item.id, language: this.#languageService.language})),
            );
          }),
  });
  protected readonly tree = computed(() => (this.treeResource.hasValue() ? this.treeResource.value() : undefined));

  protected readonly randomPictureResource = rxResource({
    params: () => this.item() ?? null,
    stream: ({params}) =>
      params === null
        ? of(undefined)
        : defer(() => {
            const item = params;
            return defer(() =>
              this.#picturesClient.getPicture(
                new PicturesRequest({
                  fields: new PictureFields({thumbMedium: true}),
                  limit: 1,
                  options: new PictureListOptions({
                    pictureItem: new PictureItemListOptions({
                      itemParentCacheAncestor: new ItemParentCacheListOptions({
                        parentId: item.id,
                      }),
                    }),
                  }),
                  order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
                }),
              ),
            ).pipe(
              catchError((error: unknown) => {
                if (isNotFoundError(error)) {
                  return of(undefined);
                }
                console.error(error);
                return throwError(() => error);
              }),
            );
          }),
  });
  protected readonly randomPicture = computed(() =>
    this.randomPictureResource.hasValue() ? this.randomPictureResource.value() : undefined,
  );

  protected toggleSubscription(item: Item) {
    const newValue = !item.subscription;
    this.#itemsClient
      .setUserItemSubscription(
        new SetUserItemSubscriptionRequest({
          itemId: item.id,
          subscribed: newValue,
        }),
      )
      .subscribe({
        error: (error: unknown) => {
          this.#toastService.handleError(error);
        },
        next: () => {
          item.subscription = newValue;
          this.#cdr.markForCheck();
        },
      });
  }

  protected getItemTypeTranslation(id: number, type: string) {
    return getItemTypeTranslation(id, type);
  }
}
