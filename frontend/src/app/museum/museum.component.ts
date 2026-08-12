import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentsType,
  Item,
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
import {RemarkModule} from 'ngx-remark';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../comments/comments/comments.component';
import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../toasts/toasts.service';
import {MuseumMapComponent} from './map/museum-map.component';

@Component({
  selector: 'app-museum',
  imports: [RouterLink, CommentsComponent, AsyncPipe, ThumbnailComponent, RemarkModule, MuseumMapComponent],
  templateUrl: './museum.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MuseumComponent {
  readonly #auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly museumModer$ = this.#auth.hasRole$(Role.CARS_MODER);

  readonly #itemID$ = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly links$ = this.#itemID$.pipe(
    switchMap((itemID) =>
      this.#itemsClient.getItemLinks(
        new ItemLinksRequest({
          options: new ItemLinkListOptions({itemId: itemID}),
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return of(null);
    }),
  );

  protected readonly pictures$ = this.#itemID$.pipe(
    switchMap((itemID) =>
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
            pictureItem: new PictureItemListOptions({
              itemId: itemID,
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_LIKES,
          paginator: false,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
  );

  protected readonly item$: Observable<Item> = this.#itemID$.pipe(
    switchMap((id) =>
      this.#itemsClient.item(
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
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    switchMap((item) => {
      if (!item || item.itemTypeId !== ItemType.ITEM_TYPE_MUSEUM) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(item);
    }),
    tap((item) => {
      this.#pageEnv.set({
        pageId: 159,
        title: item.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly CommentsType = CommentsType;
}
