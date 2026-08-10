import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  GetBrandSectionsRequest,
  Item,
  ItemFields,
  ItemLink,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturePathRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {getCatalogueSectionsTranslation} from '@utils/translations';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {forkJoin, Observable, of} from 'rxjs';
import {catchError, map, switchMap} from 'rxjs/operators';

import {chunk, chunkBy} from '../../chunk';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {CatalogueService} from '../catalogue-service';

interface PictureRoute {
  picture: Picture;
  route: null | string[];
}

@Component({
  selector: 'app-catalogue-index',
  imports: [RouterLink, ThumbnailComponent, RemarkModule],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueIndexComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #router = inject(Router);
  readonly #catalogue = inject(CatalogueService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #picturesClient = inject(PicturesClient);

  protected readonly ItemType = ItemType;

  protected readonly isModer = toSignal(this.#auth.hasRole$(Role.MODER), {initialValue: false});

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  // Chained resources (below) register their pending task through Angular's reactive graph
  // (an effect scheduled at construction) rather than lazy template `| async` subscription, so
  // they don't race Angular's SSR whenStable() check the way a raw Observable stored on an object
  // and subscribed later by the template would (see the Articles list author-lookup fix for the
  // full explanation). `id` also seeds each resource as already-resolved from TransferState on
  // hydration — notably useful here since isModer depends on client-side Keycloak
  // initialization, which can take a real amount of time; without `id` the whole page would
  // blank-and-reload on every hydration waiting for it, even though SSR already has the data.
  //
  // Every `id` below is suffixed with the catname read once at construction time (component
  // construction happens per brand, since a route-param-only change reuses the instance and
  // never re-reads `id`). A static id would let a *second* CatalogueIndexComponent instance -
  // created by navigating SSR /bmw -> away -> /toyota, all before Angular's whenStable() ever
  // resolves (isActive stays true the whole time isModer's Keycloak init is pending) - match the
  // TransferState entry BMW's SSR pass already wrote under the same static key, and seed itself
  // with BMW's data instead of fetching Toyota's.
  //
  // Missing catname / empty list response are both surfaced as a NOT_FOUND resource error rather
  // than an imperative Router.navigate() inside the stream (which raced SSR's whenStable() the
  // same way the picture-page canonicalResource did) — see the constructor effect() below, which
  // is the single place that navigates or toasts off this resource's error()/value() signals.
  protected readonly brandResource = rxResource({
    id: `catalogue-brand-${this.#catname() ?? ''}`,
    params: () => ({catname: this.#catname(), isModer: this.isModer()}),
    stream: ({params: {catname, isModer}}): Observable<Item> => {
      if (!catname) {
        return notFoundError();
      }

      const fields = new ItemFields({
        descendantTwinsGroupsCount: true,
        description: true,
        fullName: true,
        logo120: true,
        mostsActive: true,
        nameOnly: true,
        nameText: true,
      });
      if (isModer) {
        fields.inboxPicturesCount = true;
        fields.commentsAttentionsCount = true;
      }

      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields,
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
            }),
          }),
        )
        .pipe(
          switchMap((response) => {
            if (!response.items || response.items.length <= 0) {
              return notFoundError();
            }

            return of(response.items[0]);
          }),
        );
    },
  });

  constructor() {
    // Router.navigate() is fire-and-forget here (not folded into the resource stream): it runs
    // outside brandResource's own pending-task lifecycle, so there's no window where the resource
    // can settle and let SSR's whenStable() serialize before the redirect it triggered has
    // actually registered - matching the pattern in ArticlesArticleComponent/PicturePageComponent.
    // Non-NOT_FOUND errors aren't toasted here - the template already renders
    // `brandResource.error().message` inline, so a toast would just duplicate it.
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that.
      if (!this.brandResource.hasValue()) {
        return;
      }

      const brand = this.brandResource.value();
      this.#meta.updateTag({property: 'og:title', content: brand.nameText});
      if (brand.logo120) {
        this.#meta.updateTag({property: 'og:image', content: brand.logo120.src});
      }

      this.#pageEnv.set({
        pageId: 10,
        title: brand.nameText,
      });
    });
  }

  protected readonly picturesResource = rxResource({
    id: `catalogue-brand-pictures-${this.#catname() ?? ''}`,
    params: () => this.brandResource.value(),
    stream: ({params: brand}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({
              commentsCount: true,
              moderVote: true,
              nameHtml: true,
              nameText: true,
              path: new PicturePathRequest({parentId: brand.id}),
              thumbMedium: true,
              views: true,
              votes: true,
            }),
            language: this.#languageService.language,
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brand.id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_LIKES,
          }),
        )
        .pipe(
          map((response) => {
            const pictures: PictureRoute[] = (response.items || []).map((pic) => ({
              picture: pic,
              route: this.#catalogue.picturePathToRoute(pic),
            }));

            return chunkBy(pictures, 4);
          }),
        ),
  });

  protected readonly linksResource = rxResource({
    id: `catalogue-brand-links-${this.#catname() ?? ''}`,
    params: () => this.brandResource.value(),
    stream: ({params: brand}) =>
      this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId: brand.id})})).pipe(
        map((response) => {
          const official: ItemLink[] = [];
          const club: ItemLink[] = [];
          const other: ItemLink[] = [];
          (response.items ? response.items : []).forEach((item) => {
            switch (item.type) {
              case 'club':
                club.push(item);
                break;
              case 'official':
                official.push(item);
                break;
              default:
                other.push(item);
                break;
            }
          });
          return {club, official, other};
        }),
      ),
  });

  // Fetches each factory's picture with forkJoin inside the same stream, rather than storing a
  // per-item Observable for the template to subscribe lazily via `| async` (the previous shape
  // here, which raced SSR's whenStable() check the same way the Articles list author lookup did).
  protected readonly factoriesResource = rxResource({
    id: `catalogue-brand-factories-${this.#catname() ?? ''}`,
    params: () => this.brandResource.value(),
    stream: ({params: brand}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 4,
            options: new ItemListOptions({
              descendant: new ItemParentCacheListOptions({
                itemParentCacheAncestorByItemId: new ItemParentCacheListOptions({
                  itemsByParentId: new ItemListOptions({id: brand.id}),
                }),
              }),
              pictureItems: new PictureItemListOptions({
                pictures: new PictureListOptions({
                  status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                }),
              }),
              typeId: ItemType.ITEM_TYPE_FACTORY,
            }),
          }),
        )
        .pipe(
          switchMap((response) => {
            const items = response.items || [];
            if (items.length === 0) {
              return of([]);
            }

            return forkJoin(
              items.map((item) =>
                this.#picturesClient
                  .getPicture(
                    new PicturesRequest({
                      fields: new PictureFields({thumbMedium: true}),
                      language: this.#languageService.language,
                      options: new PictureListOptions({
                        pictureItem: new PictureItemListOptions({itemId: item.id}),
                        status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                      }),
                      order: PicturesRequest.Order.ORDER_LIKES,
                    }),
                  )
                  .pipe(
                    map((picture) => ({item, picture})),
                    // A factory without an accepted picture yet shouldn't take down every other
                    // factory's picture in the same batch.
                    catchError(() => of({item, picture: null})),
                  ),
              ),
            );
          }),
        ),
  });

  protected readonly sectionsResource = rxResource({
    id: `catalogue-brand-sections-${this.#catname() ?? ''}`,
    params: () => this.brandResource.value(),
    stream: ({params: brand}) =>
      this.#itemsClient
        .getBrandSections(
          new GetBrandSectionsRequest({
            itemId: brand.id,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          map((response) =>
            (response.sections || []).map((section) => ({
              halfChunks: chunk(section.groups || [], 2).map((halfChunk) => chunk(halfChunk, 2)),
              name: getCatalogueSectionsTranslation(section.name),
              routerLink: section.routerLink,
            })),
          ),
        ),
  });
}
