import {isPlatformBrowser} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, PLATFORM_ID} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CommentsType,
  PictureFields,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {map, of} from 'rxjs';

import {Comments} from '../comments/comments/comments';
import {PictureDetails} from './picture-details';
@Component({
  selector: 'app-picture-page',
  imports: [RouterLink, Comments, PictureDetails],
  templateUrl: './picture-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PicturePage {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #notFound = inject(NotFoundService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #auth = inject(AuthService);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // Wait for a definite browser auth state before loading per-user data or reporting 404.
  // SSR is explicitly anonymous; pending browser initialization remains undefined.
  readonly #authenticated = this.#auth.authenticated;

  // The canonical-route redirect runs in pictureCanonicalGuard, so by the time this component is
  // constructed the URL is already the canonical one and the picture can be fetched straight off
  // the identity.
  // #authenticated is folded into id and params: the picture carries per-user fields (subscribed,
  // the caller's own vote, moder rights). A server-side render is anonymous; without this the
  // client would keep that anonymous snapshot after auth resolves. See CatalogueIndex.
  protected readonly pictureResource = rxResource({
    id: `picture-page-${this.#identity() ?? ''}${this.#authenticated() ? '-auth' : ''}`,
    params: () => {
      if (this.#authenticated() === undefined) return undefined;
      return {authenticated: !!this.#authenticated(), identity: this.#identity()};
    },
    stream: ({params: {identity}}) => {
      if (!identity) {
        return of(undefined);
      }

      return this.#picturesClient.getPicture(
        new PicturesRequest({
          fields: new PictureFields({
            copyrights: true,
            image: true,
            moderVoted: true,
            nameHtml: true,
            nameText: true,
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
          options: new PictureListOptions({identity}),
        }),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

  protected readonly errorMessage = errorMessage;

  constructor() {
    // NOT_FOUND is reported to NotFoundService (App renders <app-page-not-found> in place
    // of the outlet, with a 404 status on SSR) rather than via Router.navigate(['/error-404']): SSR
    // doesn't honour an imperative navigation fired mid-render - whenStable() can serialize a blank
    // outlet before it registers.
    effect(() => {
      if (isNotFoundError(this.pictureResource.error())) {
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
      // browser (isBrowser keeps the anonymous SSR render visible).
      if (this.#isBrowser && this.#authenticated() === false && picture.status === PictureStatus.PICTURE_STATUS_INBOX) {
        this.#notFound.report();

        return;
      }

      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: PageId.PICTURE,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }
}
