import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {
  ItemFields,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
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
import {catchError, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-persons-person-info',
  imports: [PaginatorComponent, ThumbnailComponent, RemarkModule],
  templateUrl: './info.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonInfoComponent {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #itemsClient = inject(ItemsClient);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #itemID = toSignal(this.#route.parent!.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly itemResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `persons-person-info-item-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: id}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              description: true,
              nameText: true,
            }),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(switchMap((item) => (item.itemTypeId === ItemType.ITEM_TYPE_PERSON ? of(item) : notFoundError()))),
  });

  protected readonly linksResource = rxResource({
    id: `persons-person-info-links-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId: itemID})})).pipe(
        map((response) => (response.items ? response.items : [])),
        catchError(() => of([])),
      ),
  });

  protected readonly authorPicturesResource = rxResource({
    id: `persons-person-info-author-pictures-${this.#itemID()}`,
    params: () => ({itemID: this.#itemID(), page: this.#page()}),
    stream: ({params: {itemID, page}}) =>
      this.#picturesClient
        .getPictures(
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
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemId: itemID,
                typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_LIKES,
            page,
            paginator: true,
          }),
        )
        .pipe(catchError(() => of(null))),
  });

  protected readonly contentPicturesResource = rxResource({
    id: `persons-person-info-content-pictures-${this.#itemID()}`,
    params: () => ({itemID: this.#itemID(), page: this.#page()}),
    stream: ({params: {itemID, page}}) =>
      this.#picturesClient
        .getPictures(
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
            limit: 12,
            options: new PictureListOptions({
              pictureItem: new PictureItemListOptions({
                itemId: itemID,
                typeId: PictureItemType.PICTURE_ITEM_CONTENT,
              }),
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_LIKES,
            page,
            paginator: true,
          }),
        )
        .pipe(catchError(() => of(null))),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const item = this.itemResource.value();
      if (item) {
        this.#pageEnv.set({
          pageId: 213,
          title: item.nameText,
        });
      }
    });
  }
}
