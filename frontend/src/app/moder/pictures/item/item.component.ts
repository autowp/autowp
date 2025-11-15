import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  APIUser,
  CreatePictureItemRequest,
  CreateTrafficBlacklistItemRequest,
  DeletePictureItemRequest,
  DeleteSimilarRequest,
  DeleteTrafficBlacklistItemRequest,
  DfDistance,
  DfDistanceFields,
  DfDistanceRequest,
  IP,
  ItemFields,
  ItemListOptions,
  ItemParentCacheFields,
  ItemParentCacheListOptions,
  ItemParentCacheRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
  Picture,
  PictureFields,
  PictureIDRequest,
  PictureItem,
  PictureItemFields,
  PictureItemsRequest,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
  PictureStatus,
  SetPictureCopyrightsRequest,
  SetPictureItemItemIDRequest,
  SetPictureItemPerspectiveRequest,
  SetPictureStatusRequest,
  UpdatePictureRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient, TrafficClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbProgressbar, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {IpService} from '@services/ip';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {NgDatePipesModule, NgMathPipesModule} from 'ngx-pipes';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, combineLatest, EMPTY, Observable, of, throwError} from 'rxjs';
import {catchError, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';
import {sprintf} from 'sprintf-js';

import {StatusCode} from '../../../../grpc-web-client/statuscode';
import {MarkdownEditComponent} from '../../../markdown-edit/markdown-edit/markdown-edit.component';
import {PictureModerVoteComponent} from '../../../picture-moder-vote/picture-moder-vote/picture-moder-vote.component';
import {ToastsService} from '../../../toasts/toasts.service';
import {UserComponent} from '../../../user/user/user.component';
import {ModerPicturesPerspectivePickerComponent} from '../perspective-picker/perspective-picker.component';

interface LastItemInfo {
  hasItem: boolean;
  item: APIItem | null;
}

@Component({
  selector: 'app-moder-pictures-item',
  imports: [
    RouterLink,
    NgbTooltip,
    ModerPicturesPerspectivePickerComponent,
    NgbDropdown,
    NgbDropdownToggle,
    NgbDropdownMenu,
    NgbProgressbar,
    FormsModule,
    MarkdownEditComponent,
    UserComponent,
    AsyncPipe,
    DatePipe,
    NgMathPipesModule,
    NgDatePipesModule,
    TimeAgoPipe,
    PictureModerVoteComponent,
    RemarkModule,
  ],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #ipService = inject(IpService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #userService = inject(UserService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #trafficClient = inject(TrafficClient);

  protected readonly replaceLoading = signal(false);
  protected readonly pictureItemLoading = signal(false);
  protected readonly similarLoading = signal(false);
  protected readonly repairLoading = signal(false);
  protected readonly statusLoading = signal(false);
  protected readonly copyrightsLoading = signal(false);
  protected readonly specialNameLoading = signal(false);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly id$ = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    tap((id) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: 72,
        title: $localize`Picture №${id}`,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly picture$ = combineLatest([this.id$, this.#change$]).pipe(
    switchMap(([id]) =>
      this.#picturesClient.getPicture(
        new PicturesRequest({
          fields: new PictureFields({
            acceptedCount: true,
            copyrights: true,
            dfDistance: new DfDistanceRequest({
              fields: new DfDistanceFields({
                dstPicture: new PicturesRequest({
                  fields: new PictureFields({thumbMedium: true}),
                }),
              }),
              limit: 1,
            }),
            exif: true,
            image: true,
            isLast: true,
            moderVoted: true,
            pictureItem: new PictureItemsRequest({
              fields: new PictureItemFields({
                item: new ItemsRequest({
                  fields: new ItemFields({
                    nameHtml: true,
                  }),
                }),
                itemParentCacheAncestor: new ItemParentCacheRequest({
                  fields: new ItemParentCacheFields({
                    parentItem: new ItemsRequest({
                      fields: new ItemFields({nameHtml: true}),
                    }),
                  }),
                  options: new ItemParentCacheListOptions({
                    itemsByParentId: new ItemListOptions({
                      typeId: ItemType.ITEM_TYPE_BRAND,
                    }),
                  }),
                }),
              }),
            }),
            pictureModerVotes: new PictureModerVoteRequest(),
            replaceable: new PicturesRequest(),
            rights: true,
            siblings: new PicturesRequest({fields: new PictureFields({nameText: true})}),
            specialName: true,
            thumbMedium: true,
            views: true,
          }),
          language: this.#languageService.language,
          options: new PictureListOptions({id}),
        }),
      ),
    ),
    catchError(() => {
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly changeStatusUser$: Observable<APIUser | null> = this.picture$.pipe(
    switchMap((picture) => this.#userService.getUser$(picture.changeStatusUserId)),
  );

  protected readonly owner$: Observable<APIUser | null> = this.picture$.pipe(
    switchMap((picture) => this.#userService.getUser$(picture.ownerId)),
  );

  protected readonly ip$: Observable<IP | null> = this.picture$.pipe(
    switchMap((picture) => {
      if (!picture.ip) {
        return of(null);
      }
      return this.#ipService.getIp$(picture.ip, ['blacklist', 'rights']).pipe(catchError(() => of(null)));
    }),
  );

  protected readonly lastItem$: Observable<LastItemInfo> = this.picture$.pipe(
    switchMap((picture) => {
      if (!localStorage) {
        return of({hasItem: false, item: null});
      }

      const lastItemId = localStorage.getItem('last_item');
      if (!lastItemId) {
        return of({hasItem: false, item: null});
      }

      return this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({nameHtml: true}),
            id: lastItemId,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          catchError((error: unknown) => {
            if (error instanceof GrpcStatusEvent && error.statusCode == StatusCode.NOT_FOUND) {
              return of(null);
            }
            console.error(error);
            return throwError(() => error);
          }),
          map((item) => ({hasItem: item ? this.hasItem(picture.pictureItems?.items || [], item.id) : false, item})),
        );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly banPeriods = [
    {name: $localize`hour`, value: 1},
    {name: $localize`2 hours`, value: 2},
    {name: $localize`4 hours`, value: 4},
    {name: $localize`8 hours`, value: 8},
    {name: $localize`16 hours`, value: 16},
    {name: $localize`day`, value: 24},
    {name: $localize`2 days`, value: 48},
  ];
  protected banPeriod = 1;
  protected banReason: null | string = null;

  protected readonly monthOptions: {
    name: string;
    value: null | number;
  }[];
  protected readonly dayOptions: {
    name: string;
    value: null | number;
  }[];

  constructor() {
    this.monthOptions = [
      {
        name: '--',
        value: 0,
      },
    ];

    const date = new Date(Date.UTC(2000, 1, 1, 0, 0, 0, 0));
    for (let i = 0; i < 12; i++) {
      date.setMonth(i);
      const language = this.#languageService.language;
      if (language) {
        const month = date.toLocaleString(language, {month: 'long'});
        this.monthOptions.push({
          name: sprintf('%02d - %s', i + 1, month),
          value: i + 1,
        });
      }
    }

    this.dayOptions = [
      {
        name: '--',
        value: 0,
      },
    ];
    for (let i = 1; i <= 31; i++) {
      this.dayOptions.push({
        name: sprintf('%02d', i),
        value: i,
      });
    }
  }

  protected savePerspective(perspectiveId: null | number, item: PictureItem) {
    this.#picturesClient
      .setPictureItemPerspective(
        new SetPictureItemPerspectiveRequest({
          itemId: item.itemId,
          perspectiveId: perspectiveId ?? undefined,
          pictureId: item.pictureId,
          type: item.type,
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
    this.#change$.next();
  }

  private hasItem(items: PictureItem[], itemId: string): boolean {
    let found = false;
    for (const item of items) {
      if (item.itemId === itemId) {
        found = true;
      }
    }

    return found;
  }

  protected addItem(id: string, item: APIItem, type: number) {
    this.pictureItemLoading.set(true);
    this.#picturesClient
      .createPictureItem(
        new CreatePictureItemRequest({
          itemId: item.id,
          pictureId: id,
          type: type,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.pictureItemLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          localStorage.setItem('last_item', item.id);
          this.#change$.next();
          this.pictureItemLoading.set(false);
        },
      });
  }

  protected moveItem(id: string, type: number, srcItemId: string, dstItemId: string) {
    this.pictureItemLoading.set(true);
    this.#picturesClient
      .setPictureItemItemID(
        new SetPictureItemItemIDRequest({
          itemId: srcItemId,
          newItemId: dstItemId,
          pictureId: id,
          type: type,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.pictureItemLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          localStorage.setItem('last_item', dstItemId);
          this.#change$.next();
          this.pictureItemLoading.set(false);
        },
      });
  }

  protected saveSpecialName(picture: Picture) {
    this.specialNameLoading.set(true);
    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          id: picture.id,
          name: picture.specialName,
          takenDate: picture.takenDate,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.specialNameLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.specialNameLoading.set(false);
        },
      });
  }

  protected saveCopyrights(picture: Picture) {
    this.copyrightsLoading.set(true);

    this.#picturesClient
      .setPictureCopyrights(
        new SetPictureCopyrightsRequest({
          copyrights: picture.copyrights,
          id: picture.id,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.copyrightsLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.copyrightsLoading.set(false);
        },
      });
  }

  private setPictureStatus(id: string, status: PictureStatus) {
    this.statusLoading.set(true);
    this.#picturesClient
      .setPictureStatus(new SetPictureStatusRequest({id, status}))
      .pipe(
        catchError((err: unknown) => {
          this.#toastService.handleError(err);
          return EMPTY;
        }),
      )
      .subscribe({
        error: () => {
          this.statusLoading.set(false);
        },
        next: () => {
          this.#change$.next();
          this.statusLoading.set(false);
        },
      });
  }

  protected unacceptPicture(id: string) {
    this.setPictureStatus(id, PictureStatus.PICTURE_STATUS_INBOX);
  }

  protected acceptPicture(id: string) {
    this.setPictureStatus(id, PictureStatus.PICTURE_STATUS_ACCEPTED);
  }

  protected deletePicture(id: string) {
    this.setPictureStatus(id, PictureStatus.PICTURE_STATUS_REMOVING);
  }

  protected restorePicture(id: string) {
    this.setPictureStatus(id, PictureStatus.PICTURE_STATUS_INBOX);
  }

  protected normalizePicture(id: string) {
    this.repairLoading.set(true);
    this.#picturesClient
      .normalize(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.repairLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.repairLoading.set(false);
        },
      });
  }

  protected flopPicture(id: string) {
    this.repairLoading.set(true);
    this.#picturesClient
      .flop(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.repairLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.repairLoading.set(false);
        },
      });
  }

  protected repairPicture(id: string) {
    this.repairLoading.set(true);
    this.#picturesClient
      .repair(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.repairLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.repairLoading.set(false);
        },
      });
  }

  protected correctFileNames(id: string) {
    this.repairLoading.set(true);
    this.#picturesClient
      .correctFileNames(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.repairLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.repairLoading.set(false);
        },
      });
  }

  protected cancelSimilar(dfDistance: DfDistance) {
    this.similarLoading.set(true);
    this.#picturesClient
      .deleteSimilar(new DeleteSimilarRequest({id: dfDistance.srcPictureId, similarPictureId: dfDistance.dstPictureId}))
      .pipe(
        catchError((error: unknown) => {
          this.similarLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.similarLoading.set(false);
        },
      });
  }

  protected deletePictureItem(item: PictureItem) {
    this.pictureItemLoading.set(true);
    this.#picturesClient
      .deletePictureItem(
        new DeletePictureItemRequest({
          itemId: item.itemId,
          pictureId: item.pictureId,
          type: item.type,
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.pictureItemLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.pictureItemLoading.set(false);
        },
      });
  }

  protected cancelReplace(id: string) {
    this.replaceLoading.set(true);

    this.#picturesClient
      .clearReplacePicture(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.replaceLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.replaceLoading.set(false);
          this.#change$.next();
        },
      });
  }

  protected acceptReplace(id: string) {
    this.replaceLoading.set(true);
    this.#picturesClient
      .acceptReplacePicture(new PictureIDRequest({id}))
      .pipe(
        catchError((error: unknown) => {
          this.replaceLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
          this.replaceLoading.set(false);
        },
      });
  }

  protected removeFromBlacklist(ip: string) {
    this.#trafficClient
      .deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ip}))
      .subscribe(() => this.#change$.next());
  }

  protected addToBlacklist(ip: string) {
    this.#trafficClient
      .createTrafficBlacklistItem(
        new CreateTrafficBlacklistItemRequest({
          item: {
            ip: ip,
            period: this.banPeriod,
            reason: this.banReason || '',
          },
        }),
      )
      .subscribe(() => this.#change$.next());
  }

  protected readonly ItemType = ItemType;
  protected readonly PictureItemType = PictureItemType;
  protected readonly PictureStatus = PictureStatus;
}
