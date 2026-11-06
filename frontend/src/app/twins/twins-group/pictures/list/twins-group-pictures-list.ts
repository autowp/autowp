import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, effect, inject, input} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {
  ItemFields,
  ItemParentCacheListOptions,
  ItemRequest,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {Paginator} from '../../../../paginator/paginator/paginator';
import {Thumbnail} from '../../../../thumbnail/thumbnail/thumbnail';

@Component({
  selector: 'app-twins-group-pictures-list',
  imports: [Paginator, Thumbnail],
  templateUrl: './twins-group-pictures-list.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupPicturesList {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #notFound = inject(NotFoundService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #id = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('group') ?? '')),
    {
      requireSync: true,
    },
  );

  readonly page = input(NaN, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10),
  });

  protected readonly groupResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `twins-group-pictures-list-group-${this.#id()}`,
    params: () => this.#id(),
    stream: ({params: id}) =>
      id
        ? this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({
                nameHtml: true,
                nameText: true,
              }),
              id,
              language: this.#languageService.language,
            }),
          )
        : notFoundError(),
  });

  protected readonly picturesResource = rxResource({
    id: `twins-group-pictures-list-data-${this.#id()}`,
    params: () => ({id: this.#id(), page: this.page()}),
    stream: ({params: {id, page}}) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 24,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: id}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_PERSPECTIVES,
          page,
          paginator: true,
        }),
      ),
  });

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        this.#notFound.report();
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.groupResource.hasValue()) {
        return;
      }

      const group = this.groupResource.value();
      this.#pageEnv.set({
        pageId: PageId.TWINS_GROUP_PICTURES,
        title: $localize`All pictures of ${group.nameText}`,
      });
    });
  }
}
