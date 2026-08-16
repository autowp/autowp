import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CanonicalRouteRequest,
  CommentsType,
  PictureFields,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of} from 'rxjs';

import {CommentsComponent} from '../comments/comments/comments.component';
import {PictureComponent} from './picture.component';

@Component({
  selector: 'app-picture-page',
  imports: [RouterLink, CommentsComponent, PictureComponent],
  templateUrl: './picture-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PicturePageComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // Chained off a signal rather than a raw Observable pipe with debounceTime(10): Angular's actual
  // SSR whenStable() (used by platform-server's renderApplication) tracks only
  // PendingTasksInternal, not zone macrotasks, so a setTimeout-based delay before this chain's
  // first HTTP call isn't tracked as pending by anything — if some other resource on the page
  // happened to resolve during that window, SSR could serialize before this chain, and everything
  // depending on it (the whole page), had even started. distinctUntilChanged already prevents
  // redundant refetches for the same identity.
  protected readonly canonicalResource = rxResource({
    // Suffixed with the identity read once at construction time - a static id would let a
    // second instance of this component, created by navigating away and to a different
    // picture's page before Angular's whenStable() ever resolves, match TransferState's
    // still-present entry from the first picture and seed itself with the wrong data.
    id: `picture-page-canonical-${this.#identity() ?? ''}`,
    params: () => this.#identity(),
    stream: ({params: identity}) => {
      if (!identity) {
        return notFoundError();
      }
      return this.#picturesClient.getCanonicalRoute(new CanonicalRouteRequest({identity}));
    },
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so pictureResource's params() below doesn't blow up on a non-NOT_FOUND
  // canonicalResource error (surfaced generically by the template instead).
  protected readonly canonicalData = computed(() =>
    this.canonicalResource.hasValue() ? this.canonicalResource.value() : undefined,
  );

  // Only fetches once the canonical route has resolved to *this* page (an empty route) - while
  // canonicalResource is still loading, or resolved to a redirect elsewhere, this stays idle so
  // the picture is never fetched (and never briefly flashed) under the wrong URL.
  protected readonly pictureResource = rxResource({
    id: `picture-page-${this.#identity() ?? ''}`,
    params: () => ({canonical: this.canonicalData(), identity: this.#identity()}),
    stream: ({params: {identity, canonical}}) => {
      if (!identity || !canonical || canonical.route.length > 0) {
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

  constructor() {
    // Router.navigate() is fire-and-forget here (not folded into either resource's stream): it
    // runs outside both resources' own pending-task lifecycles, so there's no window where a
    // resource can settle and let SSR's whenStable() serialize before the redirect it triggered
    // has actually registered.
    effect(() => {
      if (isNotFoundError(this.canonicalResource.error()) || isNotFoundError(this.pictureResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.canonicalResource.hasValue()) {
        return;
      }

      const canonical = this.canonicalResource.value();
      if (canonical.route.length > 0) {
        void this.#router.navigate(canonical.route, {replaceUrl: true});
        return;
      }

      if (!this.pictureResource.hasValue()) {
        return;
      }

      const picture = this.pictureResource.value();
      this.#meta.updateTag({property: 'og:title', content: picture.nameText});
      if (picture.previewLarge) {
        this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
      }
      this.#pageEnv.set({
        pageId: 187,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }

  protected readonly errorMessage = errorMessage;
}
