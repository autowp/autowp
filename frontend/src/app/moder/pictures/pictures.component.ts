import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnDestroy, OnInit, signal} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIGetUserRequest,
  APIItem,
  APIUser,
  APIUsersRequest,
  CommentTopicListOptions,
  DfDistanceListOptions,
  DfDistanceRequest,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  ItemVehicleTypeListOptions,
  Pages,
  Picture,
  PictureFields,
  PictureItemFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteListOptions,
  PicturesRequest,
  PictureStatus,
  SetPictureStatusRequest,
  VehicleType,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient, UsersClient} from '@grpc/spec.pbsc';
import {
  NgbDropdown,
  NgbDropdownMenu,
  NgbDropdownToggle,
  NgbTypeahead,
  NgbTypeaheadSelectItemEvent,
} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {parseStringToGrpcDate} from '@services/utils';
import {VehicleTypeService} from '@services/vehicle-type';
import {getPerspectiveTranslation, getVehicleTypeTranslation} from '@utils/translations';
import {BehaviorSubject, EMPTY, forkJoin, Observable, of, Subscription} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {APIPerspectiveService} from '../../api/perspective/perspective.service';
import {APIPictureModerVoteTemplateService} from '../../api/picture-moder-vote-template/picture-moder-vote-template.service';
import {chunkBy} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';

interface PerspectiveInList {
  name: string;
  value: 'null' | null | number;
}

interface VehicleTypeInPictures {
  deep: number;
  name: string;
  value: null | string;
}

function toPlainVehicleTypes(options: VehicleType[], deep: number): VehicleTypeInPictures[] {
  const result: VehicleTypeInPictures[] = [];
  for (const item of options) {
    result.push({
      deep,
      name: getVehicleTypeTranslation(item.name),
      value: item.id,
    });
    for (const subitem of toPlainVehicleTypes(item.childs ? item.childs : [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

@Component({
  selector: 'app-moder-pictures',
  imports: [
    RouterLink,
    NgbDropdown,
    NgbDropdownToggle,
    NgbDropdownMenu,
    NgbTypeahead,
    FormsModule,
    PaginatorComponent,
    AsyncPipe,
    ThumbnailComponent,
    ReactiveFormsModule,
  ],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesComponent implements OnDestroy, OnInit {
  readonly #perspectiveService = inject(APIPerspectiveService);
  readonly #moderVoteService = inject(PictureModerVoteService);
  readonly #vehicleTypeService = inject(VehicleTypeService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #moderVoteTemplateService = inject(APIPictureModerVoteTemplateService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #usersClient = inject(UsersClient);
  readonly #picturesClient = inject(PicturesClient);

  protected readonly hasSelectedItem = signal(false);
  #selected: string[] = [];

  protected readonly status = signal<null | string>(null);
  protected readonly statusOptions = [
    {
      name: $localize`any`,
      value: null,
    },
    {
      name: $localize`inbox`,
      value: 'inbox',
    },
    {
      name: $localize`accepted`,
      value: 'accepted',
    },
    {
      name: $localize`in delete queue`,
      value: 'removing',
    },
    {
      name: $localize`all, except removing`,
      value: 'custom1',
    },
  ];

  readonly #defaultVehicleTypeOptions: VehicleTypeInPictures[] = [
    {
      deep: 0,
      name: $localize`any`,
      value: null,
    },
  ];
  protected readonly vehicleTypeID = signal<null | string>(null);

  readonly #defaultPerspectiveOptions: PerspectiveInList[] = [
    {
      name: $localize`any`,
      value: null,
    },
    {
      name: $localize`empty`,
      value: 'null',
    },
  ];
  protected readonly perspectiveOptions$ = this.#perspectiveService.getPerspectives$().pipe(
    map((perspectives) =>
      this.#defaultPerspectiveOptions.slice(0).concat(
        perspectives.map((perspective) => ({
          name: getPerspectiveTranslation(perspective.name),
          value: perspective.id,
        })),
      ),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly perspectiveID = signal<'null' | null | number>(null);

  protected readonly commentsOptions = [
    {
      name: $localize`not matters`,
      value: null,
    },
    {
      name: $localize`has`,
      value: true,
    },
    {
      name: $localize`has no`,
      value: false,
    },
  ];
  protected readonly comments = signal<boolean | null>(null);

  protected readonly replaceOptions = [
    {
      name: $localize`not matters`,
      value: null,
    },
    {
      name: $localize`replaces`,
      value: true,
    },
    {
      name: $localize`without replaces`,
      value: false,
    },
  ];
  protected readonly replace = signal<boolean | null>(null);

  protected readonly requestsOptions = [
    {
      name: $localize`not matters`,
      value: null,
    },
    {
      name: $localize`none`,
      value: 0,
    },
    {
      name: $localize`has accept votes`,
      value: 1,
    },
    {
      name: $localize`has delete votes`,
      value: 2,
    },
    {
      name: $localize`has any`,
      value: 3,
    },
  ];
  protected readonly requests = signal<null | number>(null);

  protected readonly orderOptions: {name: string; value: PicturesRequest.Order}[] = [
    {
      name: $localize`Add date (new)`,
      value: PicturesRequest.Order.ORDER_ADD_DATE_DESC,
    },
    {
      name: $localize`Add date (old)`,
      value: PicturesRequest.Order.ORDER_ADD_DATE_ASC,
    },
    {
      name: $localize`Resolution (large)`,
      value: PicturesRequest.Order.ORDER_RESOLUTION_DESC,
    },
    {
      name: $localize`Resolution (small)`,
      value: PicturesRequest.Order.ORDER_RESOLUTION_ASC,
    },
    {
      name: $localize`Filesize (large)`,
      value: PicturesRequest.Order.ORDER_FILESIZE_DESC,
    },
    {
      name: $localize`Filesize (small)`,
      value: PicturesRequest.Order.ORDER_FILESIZE_ASC,
    },
    {
      name: $localize`Commented`,
      value: PicturesRequest.Order.ORDER_COMMENTS,
    },
    {
      name: $localize`Views`,
      value: PicturesRequest.Order.ORDER_VIEWS,
    },
    {
      name: $localize`Moderator votes`,
      value: PicturesRequest.Order.ORDER_MODER_VOTES,
    },
    {
      name: $localize`Removing date`,
      value: PicturesRequest.Order.ORDER_REMOVING_DATE,
    },
    {
      name: $localize`Likes`,
      value: PicturesRequest.Order.ORDER_LIKES,
    },
    {
      name: $localize`Dislikes`,
      value: PicturesRequest.Order.ORDER_DISLIKES,
    },
  ];
  protected readonly order = signal<PicturesRequest.Order>(PicturesRequest.Order.ORDER_NONE);

  protected readonly similar = signal(false);
  protected readonly gps = signal<boolean | null>(null);
  protected readonly lost = signal(false);
  protected readonly specialName = signal(false);

  protected readonly ownerID = signal('');
  protected readonly ownerQuery = new FormControl<string>('', {nonNullable: true});
  protected readonly ownersDataSource: (text$: Observable<string>) => Observable<APIUser[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        if (query.startsWith('#')) {
          return this.#usersClient.getUser(new APIGetUserRequest({userId: query.substring(1) || ''})).pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((user) => [user]),
          );
        }

        return this.#usersClient
          .getUsers(
            new APIUsersRequest({
              limit: '10',
              search: query,
            }),
          )
          .pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((response) => response.items || []),
          );
      }),
    );

  protected readonly itemID = signal('');
  protected readonly itemQuery = new FormControl<string>('', {nonNullable: true});
  protected readonly itemsDataSource: (text$: Observable<string>) => Observable<APIItem[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '') {
          return of([]);
        }

        const params = new ItemsRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          language: this.#languageService.language,
          limit: 10,
        });
        const options = new ItemListOptions();
        if (query.startsWith('#')) {
          const id = parseInt(query.substring(1), 10);
          if (id) {
            options.id = '' + id;
          }
        } else {
          options.name = '%' + query + '%';
        }
        params.options = options;

        return this.#itemsClient.list(params).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => (response.items ? response.items : [])),
        );
      }),
    );

  protected readonly vehicleTypeOptions$ = this.#vehicleTypeService.getTypes$().pipe(
    map((types) => this.#defaultVehicleTypeOptions.concat(toPlainVehicleTypes(types, 0))),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly addedFrom = new FormControl<string>('', {nonNullable: true});

  #addedFromSub?: Subscription = undefined;

  protected readonly excludeItemID = signal('');
  protected readonly excludeItemQuery = new FormControl<string>('', {nonNullable: true});

  protected readonly data$: Observable<{
    chunks: Picture[][];
    paginator: Pages | undefined;
    pictures: Picture[];
  }> = this.#route.queryParamMap.pipe(
    distinctUntilChanged(),
    debounceTime(10),
    // eslint-disable-next-line sonarjs/cognitive-complexity
    switchMap((params) => {
      this.addedFrom.setValue(params.get('added_from') ?? '');
      this.status.set(params.get('status') ? params.get('status') : null);
      this.vehicleTypeID.set(params.get('vehicle_type_id') ? (params.get('vehicle_type_id') ?? '') : null);
      const perspectiveID = params.get('perspective_id');
      this.perspectiveID.set(perspectiveID === 'null' ? 'null' : parseInt(perspectiveID ?? '', 10) || null);
      this.itemID.set(params.get('item_id') ? (params.get('item_id') ?? '') : '');
      if (this.itemID() && !this.itemQuery.value) {
        this.itemQuery.setValue('#' + this.itemID());
      }
      this.excludeItemID.set(params.get('exclude_item_id') ? (params.get('exclude_item_id') ?? '') : '');
      if (this.excludeItemID() && !this.excludeItemQuery.value) {
        this.excludeItemQuery.setValue('#' + this.excludeItemID());
      }
      switch (params.get('comments')) {
        case 'false':
          this.comments.set(false);
          break;
        case 'true':
          this.comments.set(true);
          break;
        default:
          this.comments.set(null);
          break;
      }
      this.ownerID.set(params.get('owner_id') ?? '');
      if (this.ownerID() && !this.ownerQuery.value) {
        this.ownerQuery.setValue('#' + this.ownerID());
      }
      switch (params.get('replace')) {
        case 'false':
          this.replace.set(false);
          break;
        case 'true':
          this.replace.set(true);
          break;
        default:
          this.replace.set(null);
          break;
      }
      this.requests.set(params.get('requests') ? parseInt(params.get('requests') ?? '', 10) : null);
      this.specialName.set(!!params.get('special_name'));
      this.lost.set(!!params.get('lost'));
      this.gps.set(params.get('gps') ? true : null);
      this.similar.set(!!params.get('similar'));
      this.order.set(params.get('order') ? parseInt(params.get('order') ?? '', 10) : 1);
      this.addedFrom.setValue(params.get('added_from') ?? '');

      this.#selected = [];
      this.hasSelectedItem.set(false);

      const statuses = this.convertStatuses(this.status());
      const vehicleTypeID = this.vehicleTypeID();
      const perspectiveIDVal = this.perspectiveID();

      const qParams = new PicturesRequest({
        fields: new PictureFields({
          commentsCount: true,
          dfDistance: this.similar() ? new DfDistanceRequest({limit: 1}) : undefined,
          moderVote: true,
          nameHtml: true,
          nameText: true,
          pictureItem: new PictureItemsRequest({
            fields: new PictureItemFields({
              item: new ItemsRequest({
                fields: new ItemFields({nameHtml: true}),
              }),
            }),
            options: new PictureItemListOptions({
              item: new ItemListOptions({
                typeIds: [ItemType.ITEM_TYPE_VEHICLE, ItemType.ITEM_TYPE_BRAND, ItemType.ITEM_TYPE_PERSON],
              }),
              typeId: PictureItemType.PICTURE_ITEM_CONTENT,
            }),
          }),
          thumbMedium: true,
          views: true,
          votes: true,
        }),
        language: this.#languageService.language,
        limit: 18,
        options: new PictureListOptions({
          addedFrom: this.addedFrom.value ? parseStringToGrpcDate(this.addedFrom.value) : undefined,
          commentTopic: this.comments() === true ? new CommentTopicListOptions({messagesGtZero: true}) : undefined,
          dfDistance: this.similar()
            ? new DfDistanceListOptions({
                dstPicture: statuses ? new PictureListOptions({statuses}) : undefined,
              })
            : undefined,
          hasNoComments: this.comments() === false,
          hasNoPictureItem: this.lost(),
          hasNoPictureModerVote: this.requests() === 0,
          hasNoReplacePicture: this.replace() === false,
          hasPoint: this.gps() === true,
          hasSpecialName: this.specialName(),
          ownerId: this.ownerID() ?? undefined,
          pictureItem:
            vehicleTypeID || this.excludeItemID() || this.itemID() || perspectiveIDVal
              ? new PictureItemListOptions({
                  excludeAncestorOrSelfId: this.excludeItemID(),
                  hasNoPerspectiveId: perspectiveIDVal === 'null',
                  itemParentCacheAncestor: this.itemID()
                    ? new ItemParentCacheListOptions({parentId: this.itemID()})
                    : undefined,
                  itemVehicleType: vehicleTypeID
                    ? new ItemVehicleTypeListOptions({vehicleTypeId: vehicleTypeID})
                    : undefined,
                  perspectiveId: perspectiveIDVal && perspectiveIDVal !== 'null' ? perspectiveIDVal : undefined,
                })
              : undefined,
          pictureModerVote:
            this.requests() === 1 ||
            this.requests() === 2 ||
            this.requests() === 3 ||
            this.order() === PicturesRequest.Order.ORDER_MODER_VOTES
              ? new PictureModerVoteListOptions({
                  voteGtZero: this.requests() === 1,
                  voteLteZero: this.requests() === 2,
                })
              : undefined,
          replacePicture: this.replace() === true ? new PictureListOptions({}) : undefined,
          statuses: statuses,
        }),
        order: this.similar() ? PicturesRequest.Order.ORDER_DF_DISTANCE_SIMILARITY : this.order(),
        page: parseInt(params.get('page') ?? '', 10),
        paginator: true,
      });

      return this.#change$.pipe(
        switchMap(() => this.#picturesClient.getPictures(qParams)),
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      );
    }),
    map((response) => ({
      chunks: chunkBy<Picture>(response.items || [], 3),
      paginator: response.paginator,
      pictures: response.items || [],
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly moderVoteTemplateOptions$ = this.#moderVoteTemplateService
    .getTemplates$()
    .pipe(shareReplay({bufferSize: 1, refCount: false}));

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 73,
    });

    this.#addedFromSub = this.addedFrom.valueChanges
      .pipe(distinctUntilChanged(), debounceTime(30))
      .subscribe((value) => {
        this.#router.navigate([], {
          queryParams: {
            added_from: value ? value : null,
          },
          queryParamsHandling: 'merge',
        });
      });
  }

  ngOnDestroy(): void {
    if (this.#addedFromSub) {
      this.#addedFromSub.unsubscribe();
    }
  }

  private convertStatuses(status: null | string): PictureStatus[] | undefined {
    switch (status) {
      case 'accepted':
        return [PictureStatus.PICTURE_STATUS_ACCEPTED];
      case 'custom1':
        return [PictureStatus.PICTURE_STATUS_INBOX, PictureStatus.PICTURE_STATUS_ACCEPTED];
      case 'inbox':
        return [PictureStatus.PICTURE_STATUS_INBOX];
      case 'removing':
        return [PictureStatus.PICTURE_STATUS_REMOVING];
    }

    return undefined;
  }

  protected onPictureSelect(active: boolean, picture: Picture) {
    if (active) {
      this.#selected.push(picture.id);
    } else {
      const index = this.#selected.indexOf(picture.id);
      if (index > -1) {
        this.#selected.splice(index, 1);
      }
    }
    this.hasSelectedItem.set(this.#selected.length > 0);
  }

  protected itemFormatter(x: APIItem) {
    return x.nameText;
  }

  protected ownerFormatter(x: APIUser) {
    return x.name;
  }

  protected itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        item_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected excludeItemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        exclude_item_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearItem(): void {
    this.itemQuery.setValue('');
    this.#router.navigate([], {
      queryParams: {
        item_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearExcludeItem(): void {
    this.excludeItemQuery.setValue('');
    this.#router.navigate([], {
      queryParams: {
        exclude_item_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected ownerOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        owner_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearOwner(): void {
    this.ownerQuery.setValue('');
    this.#router.navigate([], {
      queryParams: {
        owner_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected votePictures(pictures: Picture[], vote: number, reason: string) {
    for (const id of this.#selected) {
      const promises: Observable<Empty>[] = [];
      for (const picture of pictures) {
        if (picture.id === id) {
          const q$ = this.#moderVoteService.vote$(picture.id, vote, reason);
          promises.push(q$);
        }
      }

      forkJoin(promises).subscribe(() => {
        this.#change$.next();
      });
    }
    this.#selected = [];
    this.hasSelectedItem.set(false);
  }

  protected acceptPictures(pictures: Picture[]) {
    for (const id of this.#selected) {
      const promises: Observable<void>[] = [];
      for (const picture of pictures) {
        if (picture.id === id) {
          promises.push(
            this.#picturesClient
              .setPictureStatus(
                new SetPictureStatusRequest({id: picture.id, status: PictureStatus.PICTURE_STATUS_ACCEPTED}),
              )
              .pipe(
                catchError((err: unknown) => {
                  this.#toastService.handleError(err);
                  return EMPTY;
                }),
                tap(() => (picture.status = PictureStatus.PICTURE_STATUS_ACCEPTED)),
                map(() => void 0),
              ),
          );
        }
      }

      forkJoin(promises).subscribe(() => {
        this.#change$.next();
      });
    }
    this.#selected = [];
    this.hasSelectedItem.set(false);
  }

  protected readonly PicturesRequest = PicturesRequest;
}
