import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {
  APIItem,
  APIItemLink,
  ItemFields,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesList,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../../toasts/toasts.service';

@Component({
  selector: 'app-persons-person-info',
  imports: [PaginatorComponent, AsyncPipe, ThumbnailComponent, RemarkModule],
  templateUrl: './info.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonInfoComponent {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #itemsClient = inject(ItemsClient);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #itemID$: Observable<string> = this.#route.parent!.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly item$: Observable<APIItem> = this.#itemID$.pipe(
    switchMap((id) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            description: true,
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
      if (item.itemTypeId !== ItemType.ITEM_TYPE_PERSON) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      return of(item);
    }),
    tap((item) => {
      this.#pageEnv.set({
        pageId: 213,
        title: item.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly links$: Observable<APIItemLink[]> = this.#itemID$.pipe(
    switchMap((itemID) =>
      this.#itemsClient.getItemLinks(
        new ItemLinksRequest({
          options: new ItemLinkListOptions({itemId: itemID}),
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return of({items: []});
    }),
    map((response) => (response.items ? response.items : [])),
  );

  protected readonly authorPictures$: Observable<null | PicturesList> = combineLatest([
    this.#itemID$,
    this.#page$,
  ]).pipe(
    switchMap(([itemID, page]) =>
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
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return of(null);
    }),
  );

  protected readonly contentPictures$: Observable<null | PicturesList> = combineLatest([
    this.#itemID$,
    this.#page$,
  ]).pipe(
    switchMap(([itemID, page]) =>
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
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return of(null);
    }),
  );
}
