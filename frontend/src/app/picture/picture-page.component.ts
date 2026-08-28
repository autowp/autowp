import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {CommentsType, PictureFields, PictureListOptions, PictureModerVoteRequest, PicturesRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';
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
  readonly #notFound = inject(NotFoundService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // The canonical-route redirect runs in pictureCanonicalGuard, so by the time this component is
  // constructed the URL is already the canonical one and the picture can be fetched straight off
  // the identity.
  protected readonly pictureResource = rxResource({
    id: `picture-page-${this.#identity() ?? ''}`,
    params: () => this.#identity(),
    stream: ({params: identity}) => {
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

  constructor() {
    // NOT_FOUND is reported to NotFoundService (AppComponent renders <app-page-not-found> in place
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

  protected readonly errorMessage = errorMessage;
}
