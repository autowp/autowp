import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
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
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

import {PaginatorComponent} from '../../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-twins-group-pictures-list',
  imports: [PaginatorComponent, ThumbnailComponent],
  templateUrl: './list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class TwinsGroupPicturesListComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #id = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('group') ?? '')),
    {
      requireSync: true,
    },
  );

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
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
    params: () => ({id: this.#id(), page: this.#page()}),
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

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
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

  protected readonly errorMessage = errorMessage;
}
