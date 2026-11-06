import {isPlatformBrowser} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject, PLATFORM_ID} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute} from '@angular/router';
import {
  CommentsType,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
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
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {Comments} from '../../../comments/comments/comments';
import {PictureDetails} from '../../../picture/picture-details';
@Component({
  selector: 'app-persons-person-picture',
  imports: [Comments, PictureDetails],
  templateUrl: './persons-person-picture.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonPicture {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #auth = inject(AuthService);
  readonly #isBrowser = isPlatformBrowser(inject(PLATFORM_ID));

  // Wait for a definite browser auth state before loading per-user data or reporting 404.
  // SSR is explicitly anonymous; pending browser initialization remains undefined.
  readonly #authenticated = this.#auth.authenticated;

  protected readonly itemID = toSignal(
    requireRouteParent(this.#route).paramMap.pipe(map((params) => params.get('id') ?? '')),
    {
      requireSync: true,
    },
  );

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  protected readonly pictureResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `persons-person-picture-${this.itemID()}-${this.identity() ?? ''}${this.#authenticated() ? '-auth' : ''}`,
    params: () => {
      if (this.#authenticated() === undefined) return undefined;
      return {authenticated: !!this.#authenticated(), identity: this.identity(), itemID: this.itemID()};
    },
    stream: ({params: {identity, itemID}}) => {
      if (!identity) {
        return notFoundError();
      }

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
                  itemId: itemID,
                  typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                }),
              }),
              order: PicturesRequest.Order.ORDER_LIKES,
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
              itemId: itemID,
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
        }),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.pictureResource.error())) {
        this.#notFound.report();
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that.
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
        pageId: PageId.PICTURES,
        title: picture.nameText,
      });
    });
  }

  protected reloadPicture() {
    this.pictureResource.reload();
  }
}
