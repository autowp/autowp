import type {Item, Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {isPlatformBrowser} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject, PLATFORM_ID} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CommentsType,
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {CommentsComponent} from 'app/comments/comments/comments.component';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {PictureComponent} from 'app/picture/picture.component';
import {map, of, switchMap} from 'rxjs';

import type {BrandPerspectivePageData} from '../../catalogue.module';

@Component({
  selector: 'app-catalogue-mixed-picture',
  imports: [RouterLink, CommentsComponent, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMixedPictureComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #notFound = inject(NotFoundService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #auth = inject(AuthService);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  // undefined means "not resolved yet", not "anonymous" - authenticated$ only ever emits once
  // Keycloak reaches a definite Ready/AuthSuccess/etc. state, and NotFoundService.report() can't
  // be undone short of a navigation, so treating the SSR-only default as a real "anonymous"
  // answer would permanently 404 a logged-in visitor whose browser just hasn't caught up yet.
  readonly #authenticated = toSignal(this.#auth.authenticated$, {initialValue: undefined});

  protected readonly CommentsType = CommentsType;

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // Static per-route config (mixed/other/logotypes each declare their own `data`), not a resolver
  // that changes without a fresh component instance, so requireSync is safe here.
  protected readonly data = toSignal(this.#route.data as Observable<BrandPerspectivePageData>, {requireSync: true});

  // Missing catname/identity, or a not-found brand, are all surfaced as a NOT_FOUND resource
  // error rather than an imperative Router.navigate() inside the stream — see the constructor
  // effect() below, which is the single place that reports not-found off this resource's error() signal.
  //
  // `id` is suffixed with data().catname (mixed/other/logotypes all share this component) and the
  // brand catname read once at construction time — see the identical note on
  // CatalogueMixedComponent.brandResource in ../mixed.component.ts.
  protected readonly brandResource = rxResource({
    id: `catalogue-mixed-picture-brand-${this.data().catname}-${this.#catname() ?? ''}`,
    params: () => ({catname: this.#catname(), identity: this.identity()}),
    stream: ({params: {catname, identity}}): Observable<Item> => {
      if (!catname || !identity) {
        return notFoundError();
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so downstream consumers below don't blow up on a non-NOT_FOUND
  // brandResource error (surfaced generically by the template instead).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  // Only fetches once brandResource has resolved - while it's still loading or in an error state,
  // this stays idle so the picture is never fetched (and never briefly flashed) under the wrong
  // brand.
  protected readonly pictureResource = rxResource({
    id: `catalogue-mixed-picture-${this.data().catname}-${this.#catname() ?? ''}-${this.identity() ?? ''}`,
    params: () => this.brandData(),
    stream: ({params: brand}): Observable<Picture> => {
      const identity = this.identity();
      if (!identity) {
        return notFoundError();
      }

      const data = this.data();

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
                  excludePerspectiveId: data.perspective_exclude_id ?? [],
                  itemId: brand.id,
                  perspectiveId: data.perspective_id,
                  typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                }),
              }),
              order: PicturesRequest.Order.ORDER_RESOLUTION_DESC,
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
              excludePerspectiveId: data.perspective_exclude_id ?? [],
              itemId: brand.id,
              perspectiveId: data.perspective_id,
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
        }),
      );
    },
  });

  constructor() {
    // NOT_FOUND is reported to NotFoundService (AppComponent renders <app-page-not-found> in place
    // of the outlet) rather than via Router.navigate(['/error-404']): SSR doesn't honour an
    // imperative navigation fired mid-render - whenStable() can serialize a blank outlet before it
    // registers.
    effect(() => {
      if (isNotFoundError(this.brandResource.error()) || isNotFoundError(this.pictureResource.error())) {
        this.#notFound.report();
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.pictureResource.hasValue()) {
        return;
      }

      const picture = this.pictureResource.value();

      // The backend serves inbox pictures to every caller, including an anonymous SSR render,
      // since it has no way to tell an anonymous request from a logged-in visitor's first,
      // not-yet-hydrated one - so an inbox picture is only hidden from a definitely-anonymous
      // browser (isBrowser guards SSR, since it can never resolve #authenticated to false).
      if (this.#isBrowser && this.#authenticated() === false && picture.status === PictureStatus.PICTURE_STATUS_INBOX) {
        this.#notFound.report();

        return;
      }

      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: this.data().picture_page.id,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }

  protected readonly errorMessage = errorMessage;
}
