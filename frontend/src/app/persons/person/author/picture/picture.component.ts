import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
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
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {CommentsComponent} from '../../../../comments/comments/comments.component';
import {PictureComponent} from '../../../../picture/picture.component';

@Component({
  selector: 'app-persons-person-author-picture',
  imports: [CommentsComponent, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonAuthorPictureComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  readonly #personID = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('id') ?? '')),
    {
      requireSync: true,
    },
  );

  protected readonly picturesRouterLink = computed(() => ['/persons', this.#personID(), 'author']);

  protected readonly galleryRouterLink = computed(() => [
    '/persons',
    this.#personID(),
    'author',
    'gallery',
    this.#identity() ?? '',
  ]);

  protected readonly pictureResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `persons-person-author-picture-${this.#personID()}-${this.#identity() ?? ''}`,
    params: () => ({identity: this.#identity(), itemID: this.#personID()}),
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
                  typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
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
              typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
            }),
          }),
        }),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

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

  protected readonly errorMessage = errorMessage;
}
