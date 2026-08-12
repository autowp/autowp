import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentsType,
  ItemFields,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

import {CommentsComponent} from '../comments/comments/comments.component';
import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {MuseumMapComponent} from './map/museum-map.component';

@Component({
  selector: 'app-museum',
  imports: [RouterLink, CommentsComponent, ThumbnailComponent, RemarkModule, MuseumMapComponent, AsyncPipe],
  templateUrl: './museum.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MuseumComponent {
  readonly #auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly museumModer$ = this.#auth.hasRole$(Role.CARS_MODER);

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  protected readonly itemResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `museum-item-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: id}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              description: true,
              location: true,
              nameHtml: true,
              nameText: true,
            }),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(switchMap((item) => (item.itemTypeId === ItemType.ITEM_TYPE_MUSEUM ? of(item) : notFoundError()))),
  });

  protected readonly linksResource = rxResource({
    id: `museum-links-${this.#itemID()}`,
    params: () => this.itemResource.value()?.id,
    stream: ({params: itemId}) =>
      this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId})})),
  });

  protected readonly picturesResource = rxResource({
    id: `museum-pictures-${this.#itemID()}`,
    params: () => this.itemResource.value()?.id,
    stream: ({params: itemId}) =>
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
          limit: 20,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({itemId}),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_LIKES,
          paginator: false,
        }),
      ),
  });

  protected readonly CommentsType = CommentsType;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const item = this.itemResource.value();
      if (item) {
        this.#pageEnv.set({
          pageId: 159,
          title: item.nameText,
        });
      }
    });
  }
}
