import {AsyncPipe, DatePipe, DecimalPipe, DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {Router, RouterLink} from '@angular/router';
import {
  CommentsSubscribeRequest,
  CommentsType,
  CommentsUnSubscribeRequest,
  Item,
  ItemFields,
  ItemLink,
  ItemLinkListOptions,
  ItemLinksRequest,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentListOptions,
  ItemsRequest,
  ItemType,
  Picture,
  PictureItem,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureStatus,
  PicturesViewRequest,
  PicturesVoteRequest,
  UpdatePictureItemRequest,
  UpdatePictureRequest,
  User,
} from '@grpc/spec.pb';
import {CommentsClient, ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbProgressbar, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {NgDatePipesModule, NgMathPipesModule} from 'ngx-pipes';
import {RemarkModule} from 'ngx-remark';
import {EMPTY, Observable} from 'rxjs';
import {catchError, filter, map, shareReplay, switchMap} from 'rxjs/operators';

import {ModerPicturesPerspectivePickerComponent} from '../moder/pictures/perspective-picker/perspective-picker.component';
import {PictureModerVoteComponent} from '../picture-moder-vote/picture-moder-vote/picture-moder-vote.component';
import {ShareComponent} from '../share/share.component';
import {ToastsService} from '../toasts/toasts.service';
import {UserComponent} from '../user/user/user.component';
import {PicturePaginatorComponent} from './paginator.component';

@Component({
  selector: 'app-picture',
  imports: [
    RouterLink,
    ShareComponent,
    UserComponent,
    NgbTooltip,
    NgbDropdown,
    NgbDropdownToggle,
    NgbDropdownMenu,
    NgbProgressbar,
    ModerPicturesPerspectivePickerComponent,
    AsyncPipe,
    DecimalPipe,
    DatePipe,
    NgMathPipesModule,
    NgDatePipesModule,
    TimeAgoPipe,
    PicturePaginatorComponent,
    PictureModerVoteComponent,
    RemarkModule,
  ],
  templateUrl: './picture.component.html',
  styleUrl: './picture.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureComponent {
  readonly #auth = inject(AuthService);
  readonly #router = inject(Router);
  readonly #commentsGrpc = inject(CommentsClient);
  readonly #userService = inject(UserService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #document = inject(DOCUMENT);

  readonly prefix = input.required<string[]>();
  readonly galleryRoute = input.required<string[]>();
  readonly h2 = input(false);
  readonly changed = output<boolean>();

  readonly picture = input.required<Picture>();
  readonly picture$ = toObservable(this.picture);

  protected readonly osmURL = computed(() => {
    const point = this.picture().point;
    if (!point) {
      return null;
    }

    return (
      'https://www.openstreetmap.org/?mlat=' +
      point.latitude +
      '&mlon=' +
      point.longitude +
      '#map=6/' +
      point.latitude +
      '/' +
      point.longitude
    );
  });

  protected readonly owner$: Observable<null | User> = this.picture$.pipe(
    switchMap((picture) => this.#userService.getUser$(picture?.ownerId)),
  );

  protected readonly moderVotes$ = this.picture$.pipe(
    map((picture) =>
      (picture?.pictureModerVotes?.items || []).map((vote) => ({
        reason: vote.reason,
        user$: this.#userService.getUser$(vote.userId),
        vote: vote.vote,
      })),
    ),
  );

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly canEditSpecs$ = this.#auth.authenticated$;
  protected readonly showShareDialog = signal(false);
  protected readonly location = this.#document.defaultView?.location;
  protected readonly statusLoading = signal(false);

  protected readonly authenticated$ = this.#auth.authenticated$;

  constructor() {
    effect(() => {
      this.#picturesClient.view(new PicturesViewRequest({pictureId: this.picture().id})).subscribe();
    });
  }

  protected savePerspective(perspectiveID: null | number, item: PictureItem) {
    this.#picturesClient
      .updatePictureItem(
        new UpdatePictureItemRequest({
          pictureItem: new PictureItem({
            itemId: item.itemId,
            perspectiveId: perspectiveID ?? undefined,
            pictureId: item.pictureId,
            type: item.type,
          }),
          updateMask: new FieldMask({paths: ['perspective_id']}),
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe();
  }

  protected pictureVoted() {
    this.changed.emit(true);
  }

  protected toggleShareDialog(): false {
    this.showShareDialog.set(!this.showShareDialog());
    return false;
  }

  protected setSubscribed(picture: Picture, value: boolean) {
    (value
      ? this.#commentsGrpc.subscribe(
          new CommentsSubscribeRequest({
            itemId: picture.id,
            typeId: CommentsType.PICTURES_TYPE_ID,
          }),
        )
      : this.#commentsGrpc.unSubscribe(
          new CommentsUnSubscribeRequest({
            itemId: picture.id,
            typeId: CommentsType.PICTURES_TYPE_ID,
          }),
        )
    ).subscribe(() => {
      picture.subscribed = value;
      this.#cdr.markForCheck();
    });
  }

  protected vote(picture: Picture, value: number) {
    this.#picturesClient
      .vote(
        new PicturesVoteRequest({
          pictureId: picture.id,
          value,
        }),
      )
      .subscribe((votes) => {
        picture.votes = votes;
        this.#cdr.markForCheck();
      });
    return false;
  }

  protected openSource(picture: Picture) {
    if (picture.image && this.#document.defaultView) {
      this.#document.defaultView.open(picture.image.src);
    }
  }

  protected openGallery(picture: Picture, $event: Event) {
    if (($event instanceof KeyboardEvent || $event instanceof MouseEvent) && $event.ctrlKey) {
      this.openSource(picture);
      return;
    }
    this.#router.navigate(this.galleryRoute());
  }

  private setPictureStatus(picture: Picture, status: PictureStatus) {
    this.statusLoading.set(true);
    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          picture: new Picture({id: picture.id, status}),
          updateMask: new FieldMask({paths: ['status']}),
        }),
      )
      .pipe(
        catchError((err: unknown) => {
          this.#toastService.handleError(err);
          return EMPTY;
        }),
      )
      .subscribe({
        complete: () => {
          this.statusLoading.set(false);
        },
        next: () => {
          this.changed.emit(true);
        },
      });
  }

  protected unacceptPicture(picture: Picture) {
    this.setPictureStatus(picture, PictureStatus.PICTURE_STATUS_INBOX);
  }

  protected acceptPicture(picture: Picture) {
    this.setPictureStatus(picture, PictureStatus.PICTURE_STATUS_ACCEPTED);
  }

  protected deletePicture(picture: Picture) {
    this.setPictureStatus(picture, PictureStatus.PICTURE_STATUS_REMOVING);
  }

  protected restorePicture(picture: Picture) {
    this.setPictureStatus(picture, PictureStatus.PICTURE_STATUS_INBOX);
  }

  protected readonly factories$ = this.picture$.pipe(
    filter((picture) => !!picture),
    switchMap((picture) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({pictureId: picture.id}),
            }),
            typeId: ItemType.ITEM_TYPE_FACTORY,
          }),
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  protected readonly categories$ = this.picture$.pipe(
    filter((picture) => !!picture),
    switchMap((picture) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            child: new ItemParentListOptions({
              item: new ItemListOptions({
                typeIds: [ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_ENGINE],
              }),
              itemParentCacheItemByChild: new ItemParentCacheListOptions({
                pictureItemsByItemId: new PictureItemListOptions({pictureId: picture.id}),
              }),
            }),
            typeId: ItemType.ITEM_TYPE_CATEGORY,
          }),
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  protected readonly twins$ = this.picture$.pipe(
    filter((picture) => !!picture),
    switchMap((picture) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({pictureId: picture.id}),
            }),
            typeId: ItemType.ITEM_TYPE_TWINS,
          }),
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  protected readonly brands$: Observable<Item[]> = this.picture$.pipe(
    filter((picture) => !!picture),
    switchMap((picture) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({pictureId: picture.id}),
            }),
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  protected readonly pictureItems$: Observable<PictureItem[]> = this.picture$
    .pipe(
      filter((picture) => !!picture),
      switchMap((picture) =>
        this.#picturesClient.getPictureItems(
          new PictureItemsRequest({
            fields: new PictureItemFields({
              item: new ItemsRequest({
                fields: new ItemFields({
                  altNames: true,
                  description: true,
                  design: true,
                  hasSpecs: true,
                  hasText: true,
                  nameHtml: true,
                  route: true,
                  specsRoute: true,
                }),
              }),
            }),
            language: this.#languageService.language,
            options: new PictureItemListOptions({pictureId: picture.id}),
          }),
        ),
      ),
    )
    .pipe(
      map((response) => response.items || []),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  protected readonly contentItems$ = this.pictureItems$.pipe(
    map((items) => items.filter((item) => item.type === PictureItemType.PICTURE_ITEM_CONTENT)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly links$: Observable<ItemLink[]> = this.picture$.pipe(
    filter((picture) => !!picture),
    switchMap((picture) =>
      this.#itemsClient.getItemLinks(
        new ItemLinksRequest({
          options: new ItemLinkListOptions({
            itemParentCacheDescendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({pictureId: picture.id}),
            }),
            type: 'official',
          }),
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  protected readonly takenDate$: Observable<null | {date: Date; format: string}> = this.picture$.pipe(
    filter((picture) => !!picture),
    map((picture) => {
      const date = picture.takenDate;
      if (!date) {
        return null;
      }

      if (date.year) {
        const resDate = new Date();
        resDate.setFullYear(date.year, 0, 1);
        let format = 'yyyy';
        if (date.month) {
          resDate.setFullYear(date.year, date.month - 1, 1);
          format = 'MM.yyyy';
          if (date.day) {
            resDate.setFullYear(date.year, date.month - 1, date.day);
            format = 'dd.MM.yyyy';
          }
        }
        return {date: resDate, format};
      }

      return null;
    }),
  );

  protected readonly PictureItemType = PictureItemType;
  protected readonly PictureStatus = PictureStatus;
  protected readonly ItemType = ItemType;
}
