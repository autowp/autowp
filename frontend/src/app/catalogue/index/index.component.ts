import type {Item, ItemLink} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  GetBrandSectionsRequest,
  ItemFields,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {getCatalogueSectionsTranslation} from '@utils/translations';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {catchError, forkJoin, map, of, switchMap} from 'rxjs';

import {chunk} from '../../chunk';
import {CatalogueIndexPicturesComponent} from './pictures/pictures.component';

// Keep in sync with ACCEPTED_IN_DAYS in ../recent/recent.component.ts.
const RECENT_PICTURES_ACCEPTED_IN_DAYS = 365;

@Component({
  selector: 'app-catalogue-index',
  imports: [RouterLink, RemarkModule, CatalogueIndexPicturesComponent, NgbTooltip],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueIndexComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #notFound = inject(NotFoundService);
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
  // hydration, avoiding a loading-state blink.
  //
  // Every `id` below is suffixed with the catname read once at construction time (component
  // construction happens per brand, since a route-param-only change reuses the instance and
  // never re-reads `id`). A static id would let a *second* CatalogueIndexComponent instance -
  // created by navigating SSR /bmw -> away -> /toyota, all before Angular's whenStable() ever
  // resolves - match the TransferState entry BMW's SSR pass already wrote under the same static
  // key, and seed itself with BMW's data instead of fetching Toyota's.
  //
  // Missing catname / empty list response are both surfaced as a NOT_FOUND resource error rather
  // than an imperative Router.navigate() inside the stream — see the constructor effect() below,
  // which is the single place that reports not-found or toasts off this resource's
  // error()/value() signals.
  //
  // brandResource's `id` also folds in isModer() *as read once at construction*, which matters for
  // one specific race: isModer starts as its toSignal initialValue (false) and flips once Keycloak
  // resolves (app.config.ts's provideKeycloakInAppInitializer blocks bootstrap on it, browser-only -
  // it's always false during SSR). For a session that's already authenticated, Keycloak can resolve
  // *before* this component ever constructs, so isModer can already read true on that very first
  // read. Hydration's TransferState lookup matches purely on the `id` string, with no check that the
  // params which produced the cached value still match the current ones - so without isModer in
  // `id`, that fast-resolving-moderator case would silently adopt the server's always-false snapshot
  // (which never requested inboxPicturesCount/commentsAttentionsCount) and then never notice, since
  // nothing about isModer changes *afterwards* to trigger a refetch. Folding isModer into `id` here
  // makes that id deliberately mismatch the server's in that one case, so the resource falls through
  // to a normal fresh fetch instead of getting stuck. The much more common case - isModer still false
  // at construction, flipping true only after Keycloak actually finishes - isn't affected by this at
  // all: `id` is read once and already matches the server's, so hydration proceeds as usual, and the
  // later isModer change is picked up through the ordinary params()-changed reactive refetch instead.
  protected readonly brandResource = rxResource({
    id: `catalogue-brand-${this.#catname() ?? ''}${this.isModer() ? '-moder' : ''}`,
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
      // The backend rejects these two outright for a non-moderator caller
      // (items-grpc.go requestsModeratorOnlyFields), so they can't be requested unconditionally.
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
    // NOT_FOUND is reported to NotFoundService (AppComponent renders <app-page-not-found> in place
    // of the outlet) rather than via Router.navigate(['/error-404']): SSR doesn't honour an
    // imperative navigation fired mid-render - whenStable() can serialize a blank outlet before it
    // registers. Non-NOT_FOUND errors aren't toasted here - the template already renders
    // `errorMessage(brandResource.error())` inline, so a toast would just duplicate it.
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        this.#notFound.report();
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
        pageId: PageId.CATALOGUE_INDEX,
        title: brand.nameText,
      });
    });
  }

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the chained resources below don't blow up on a non-NOT_FOUND
  // brandResource error (surfaced generically by the template instead).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  // "New pictures" nav link only makes sense while such pictures (accepted within the last year)
  // actually exist - otherwise it's a dead end to an empty recent.component.ts page. The count also
  // feeds the link's badge, so this fetches the paginator's totalItemCount rather than just a
  // boolean presence check.
  protected readonly recentPicturesCountResource = rxResource({
    id: `catalogue-brand-recent-pictures-count-${this.#catname() ?? ''}`,
    params: () => this.brandData(),
    stream: ({params: brand}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            language: this.#languageService.language,
            limit: 1,
            options: new PictureListOptions({
              acceptedInDays: RECENT_PICTURES_ACCEPTED_IN_DAYS,
              pictureItem: new PictureItemListOptions({
                itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brand.id}),
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            paginator: true,
          }),
        )
        .pipe(map((response) => response.paginator?.totalItemCount ?? 0)),
  });

  protected readonly linksResource = rxResource({
    id: `catalogue-brand-links-${this.#catname() ?? ''}`,
    params: () => this.brandData(),
    stream: ({params: brand}) =>
      this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId: brand.id})})).pipe(
        map((response) => {
          const official: ItemLink[] = [];
          const club: ItemLink[] = [];
          const other: ItemLink[] = [];
          (response.items ?? []).forEach((item) => {
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
    params: () => this.brandData(),
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
            const items = response.items ?? [];
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
    params: () => this.brandData(),
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
            (response.sections ?? []).map((section) => ({
              halfChunks: chunk(section.groups ?? [], 2).map((halfChunk) => chunk(halfChunk, 2)),
              name: getCatalogueSectionsTranslation(section.name),
              routerLink: section.routerLink,
            })),
          ),
        ),
  });

  protected readonly errorMessage = errorMessage;
}
