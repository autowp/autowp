import type {OnInit, ResourceRef} from '@angular/core';
import type {Item, ItemLink, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {DatePipe, DecimalPipe} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  effect,
  inject,
  Injector,
  input,
  output,
  signal,
} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Router, RouterLink} from '@angular/router';
import {
  CommentsSubscribeRequest,
  CommentsType,
  CommentsUnSubscribeRequest,
  ContentReportEntityType,
  ItemFields,
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
} from '@grpc/spec.pb';
import {CommentsClient, ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbProgressbar, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {browserWindow} from '@utils/browser-window';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {NgDatePipesModule, NgMathPipesModule} from 'ngx-pipes';
import {RemarkModule} from 'ngx-remark';
import {catchError, EMPTY, finalize, map, of} from 'rxjs';

import {ModerPicturesPerspectivePickerComponent} from '../moder/pictures/perspective-picker/perspective-picker.component';
import {PictureModerVoteComponent} from '../picture-moder-vote/picture-moder-vote/picture-moder-vote.component';
import {ReportButtonComponent} from '../report/report-button.component';
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
    DecimalPipe,
    DatePipe,
    NgMathPipesModule,
    NgDatePipesModule,
    TimeAgoPipe,
    PicturePaginatorComponent,
    PictureModerVoteComponent,
    ReportButtonComponent,
    RemarkModule,
  ],
  templateUrl: './picture.component.html',
  styleUrl: './picture.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PictureComponent implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #router = inject(Router);
  readonly #commentsGrpc = inject(CommentsClient);
  readonly #userService = inject(UserService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #injector = inject(Injector);
  readonly #window = browserWindow();

  readonly prefix = input.required<string[]>();
  readonly galleryRoute = input.required<string[]>();
  readonly h2 = input(false);
  readonly changed = output<boolean>();

  readonly picture = input.required<Picture>();

  // Uses timestampToDate() rather than picture().createTime?.toDate() directly in the template:
  // after this component is recreated from a TransferState-seeded resource on hydration (see the
  // ngOnInit() note below), `picture()` is a plain JSON-shaped object, not a real Picture class
  // instance, so createTime has no .toDate() method even though it still has seconds/nanos.
  protected readonly createdDate = computed(() => timestampToDate(this.picture().createTime));

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

  // Every resource below is chained off the `picture` input signal directly (not a raw Observable
  // stored on an object and subscribed lazily by the template via `| async`, the previous shape
  // here) — that pattern races Angular's SSR whenStable() check the same way the Articles list
  // author lookup did. resource() registers its pending task through Angular's reactive graph (an
  // effect scheduled at construction) instead, so it doesn't race.
  //
  // All of them are constructed in ngOnInit() (with an explicit injector, since ngOnInit isn't an
  // injection context), not as field initializers: `picture` is a *required* input, and Angular's
  // compiler forbids reading a required input's value before the class is fully constructed - it
  // isn't bound yet at field-initializer/constructor time. ngOnInit runs after Angular has bound
  // inputs, so `picture()` is safe to read there for the one-time `id` string.
  //
  // Every `id` is suffixed with `picture().id`. This component is recreated whenever its host
  // re-renders the `@else if (pictureResource.value(); as picture)` block it sits behind (e.g.
  // PicturePageComponent) for a *different* picture - a static id would let that new instance
  // match a still-present TransferState entry from the previous picture (while Angular's
  // whenStable() hasn't resolved yet) and seed itself with the wrong picture's data.
  protected ownerResource!: ResourceRef<null | undefined | User>;
  protected moderVoteUsersResource!: ResourceRef<Record<string, User> | undefined>;

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that. None of the resources on this component have an inline error slot in the
  // template, so a transient error just leaves that section empty instead of throwing.
  protected readonly ownerData = computed(() =>
    this.ownerResource.hasValue() ? this.ownerResource.value() : undefined,
  );

  protected readonly moderVotes = computed(() => {
    const usersById = (this.moderVoteUsersResource.hasValue() ? this.moderVoteUsersResource.value() : undefined) ?? {};

    return (this.picture().pictureModerVotes?.items ?? []).map((vote) => ({
      reason: vote.reason,
      user: usersById[vote.userId] ?? null,
      vote: vote.vote,
    }));
  });

  protected readonly isModer = toSignal(this.#auth.hasRole$(Role.MODER), {initialValue: false});
  protected readonly authenticated = toSignal(this.#auth.authenticated$, {initialValue: false});
  protected readonly showShareDialog = signal(false);
  protected readonly location = this.#window?.location;
  protected readonly statusLoading = signal(false);

  constructor() {
    effect(() => {
      this.#picturesClient.view(new PicturesViewRequest({pictureId: this.picture().id})).subscribe();
    });
  }

  ngOnInit(): void {
    const pictureId = this.picture().id;

    this.ownerResource = rxResource({
      id: `picture-owner-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().ownerId,
      stream: ({params: ownerId}) => this.#userService.getUser$(ownerId),
    });

    this.moderVoteUsersResource = rxResource({
      id: `picture-moder-vote-users-${pictureId}`,
      injector: this.#injector,
      // A comma-joined string rather than the id array: a resource compares params by identity,
      // and a fresh array every recomputation makes it restart on hydration - throwing away the
      // value that arrived over TransferState and blanking every author on the page until the
      // refetch lands (or for good, if it comes back empty).
      params: () => [...new Set((this.picture().pictureModerVotes?.items ?? []).map((vote) => vote.userId))].join(','),
      // A plain object rather than a Map: TransferState round-trips resource values through
      // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
      // enumerable properties, no toJSON), losing all entries.
      stream: ({params: userIds}): Observable<Record<string, User>> => {
        if (userIds.length === 0) {
          return of({});
        }
        return this.#userService.getUserMap$(userIds.split(',')).pipe(
          map((userMap) => Object.fromEntries(userMap)),
          // getUserMap$ leaves out users the backend doesn't return (deleted or anonymous), so
          // this only catches a genuine RPC failure - degrade to showing no user rather than
          // erroring the whole resource over it.
          catchError(() => of({})),
        );
      },
    });

    this.factoriesResource = rxResource({
      id: `picture-factories-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}) =>
        this.#itemsClient
          .list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
              language: this.#languageService.language,
              limit: 10,
              options: new ItemListOptions({
                descendant: new ItemParentCacheListOptions({
                  pictureItemsByItemId: new PictureItemListOptions({pictureId}),
                }),
                typeId: ItemType.ITEM_TYPE_FACTORY,
              }),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
    });

    this.categoriesResource = rxResource({
      id: `picture-categories-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}) =>
        this.#itemsClient
          .list(
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
                    pictureItemsByItemId: new PictureItemListOptions({pictureId}),
                  }),
                }),
                typeId: ItemType.ITEM_TYPE_CATEGORY,
              }),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
    });

    this.twinsResource = rxResource({
      id: `picture-twins-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}) =>
        this.#itemsClient
          .list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
              language: this.#languageService.language,
              limit: 10,
              options: new ItemListOptions({
                descendant: new ItemParentCacheListOptions({
                  pictureItemsByItemId: new PictureItemListOptions({pictureId}),
                }),
                typeId: ItemType.ITEM_TYPE_TWINS,
              }),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
    });

    this.brandsResource = rxResource({
      id: `picture-brands-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}): Observable<Item[]> =>
        this.#itemsClient
          .list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
              language: this.#languageService.language,
              limit: 10,
              options: new ItemListOptions({
                descendant: new ItemParentCacheListOptions({
                  pictureItemsByItemId: new PictureItemListOptions({pictureId}),
                }),
                typeId: ItemType.ITEM_TYPE_BRAND,
              }),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
    });

    this.pictureItemsResource = rxResource({
      id: `picture-items-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}): Observable<PictureItem[]> =>
        this.#picturesClient
          .getPictureItems(
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
              options: new PictureItemListOptions({pictureId}),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
    });

    this.linksResource = rxResource({
      id: `picture-links-${pictureId}`,
      injector: this.#injector,
      params: () => this.picture().id,
      stream: ({params: pictureId}): Observable<ItemLink[]> =>
        this.#itemsClient
          .getItemLinks(
            new ItemLinksRequest({
              options: new ItemLinkListOptions({
                itemParentCacheDescendant: new ItemParentCacheListOptions({
                  pictureItemsByItemId: new PictureItemListOptions({pictureId}),
                }),
                type: 'official',
              }),
            }),
          )
          .pipe(map((response) => response.items ?? [])),
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
    )
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe(() => {
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
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe((votes) => {
        picture.votes = votes;
        this.#cdr.markForCheck();
      });
    return false;
  }

  protected openSource(picture: Picture) {
    if (picture.image && this.#window) {
      this.#window.open(picture.image.src);
    }
  }

  protected openGallery(picture: Picture, $event: Event) {
    if (($event instanceof KeyboardEvent || $event instanceof MouseEvent) && $event.ctrlKey) {
      this.openSource(picture);
      return;
    }
    void this.#router.navigate(this.galleryRoute());
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
        // finalize, not the subscribe `complete` callback: on an error catchError swaps in EMPTY,
        // and the spinner must clear on that path too (and on unsubscribe).
        finalize(() => {
          this.statusLoading.set(false);
        }),
      )
      .subscribe(() => {
        this.changed.emit(true);
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

  protected factoriesResource!: ResourceRef<Item[] | undefined>;
  protected categoriesResource!: ResourceRef<Item[] | undefined>;
  protected twinsResource!: ResourceRef<Item[] | undefined>;
  protected brandsResource!: ResourceRef<Item[] | undefined>;
  protected pictureItemsResource!: ResourceRef<PictureItem[] | undefined>;

  // Same reasoning as ownerData above.
  protected readonly factoriesData = computed(() =>
    this.factoriesResource.hasValue() ? this.factoriesResource.value() : undefined,
  );
  protected readonly categoriesData = computed(() =>
    this.categoriesResource.hasValue() ? this.categoriesResource.value() : undefined,
  );
  protected readonly twinsData = computed(() =>
    this.twinsResource.hasValue() ? this.twinsResource.value() : undefined,
  );
  protected readonly brandsData = computed(() =>
    this.brandsResource.hasValue() ? this.brandsResource.value() : undefined,
  );
  protected readonly pictureItemsData = computed(() =>
    this.pictureItemsResource.hasValue() ? this.pictureItemsResource.value() : undefined,
  );

  protected readonly contentItems = computed(() =>
    (this.pictureItemsData() ?? []).filter((item) => item.type === PictureItemType.PICTURE_ITEM_CONTENT),
  );

  protected linksResource!: ResourceRef<ItemLink[] | undefined>;

  // Same reasoning as ownerData above.
  protected readonly linksData = computed(() =>
    this.linksResource.hasValue() ? this.linksResource.value() : undefined,
  );

  protected readonly takenDate = computed<null | {date: Date; format: string}>(() => {
    const date = this.picture().takenDate;
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
  });

  protected readonly PictureItemType = PictureItemType;
  protected readonly PictureStatus = PictureStatus;
  protected readonly ContentReportEntityType = ContentReportEntityType;
  protected readonly ItemType = ItemType;
}
