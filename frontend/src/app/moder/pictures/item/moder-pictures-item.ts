import type {DfDistance, IP, Item, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {disabled, form, FormField, maxLength, required, submit, validate} from '@angular/forms/signals';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  CreatePictureItemRequest,
  CreateTrafficBlacklistItemRequest,
  DeletePictureItemRequest,
  DeleteSimilarRequest,
  DeleteTrafficBlacklistItemRequest,
  DfDistanceFields,
  DfDistanceRequest,
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
  PictureLicense,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
  PictureStatus,
  SetPictureItemItemIDRequest,
  UpdatePictureItemRequest,
  UpdatePictureRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient, TrafficClient} from '@grpc/spec.pbsc';
import {
  NgbDropdown,
  NgbDropdownMenu,
  NgbDropdownToggle,
  NgbModal,
  NgbProgressbar,
  NgbTooltip,
} from '@ng-bootstrap/ng-bootstrap';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {IpService} from '@services/ip';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {browserWindow} from '@utils/browser-window';
import {pictureLicenseOptions} from '@utils/license-badge/license-badge';
import {LicenseHelpModal} from '@utils/license-help-modal/license-help-modal';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {getPictureLicenseTranslation, pictureLicenseRequiresSourceUrl} from '@utils/translations';
import {isNotFoundError} from 'app/grpc';
import {MarkdownEdit} from 'app/markdown-edit/markdown-edit/markdown-edit';
import {PictureModerVote} from 'app/picture-moder-vote/picture-moder-vote/picture-moder-vote';
import {ToastsService} from 'app/toasts/toasts.service';
import {UserLink} from 'app/user/user/user-link';
import {NgDatePipesModule, NgMathPipesModule} from 'ngx-pipes';
import {RemarkModule} from 'ngx-remark';
import {
  BehaviorSubject,
  catchError,
  combineLatest,
  distinctUntilChanged,
  EMPTY,
  finalize,
  firstValueFrom,
  map,
  of,
  shareReplay,
  switchMap,
  tap,
  throwError,
} from 'rxjs';
import {sprintf} from 'sprintf-js';

import {ModerPicturesPerspectivePicker} from '../perspective-picker/moder-pictures-perspective-picker';

interface LastItemInfo {
  hasItem: boolean;
  item: Item | null;
}

@Component({
  selector: 'app-moder-pictures-item',
  imports: [
    FormField,
    RouterLink,
    NgbTooltip,
    ModerPicturesPerspectivePicker,
    NgbDropdown,
    NgbDropdownToggle,
    NgbDropdownMenu,
    NgbProgressbar,
    MarkdownEdit,
    UserLink,
    AsyncPipe,
    DatePipe,
    NgMathPipesModule,
    NgDatePipesModule,
    TimeAgoPipe,
    PictureModerVote,
    RemarkModule,
  ],
  templateUrl: './moder-pictures-item.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItem {
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #ipService = inject(IpService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #userService = inject(UserService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #trafficClient = inject(TrafficClient);
  readonly #modalService = inject(NgbModal);
  readonly #window = browserWindow();

  protected readonly replaceLoading = signal(false);
  protected readonly pictureItemLoading = signal(false);
  protected readonly similarLoading = signal(false);
  protected readonly repairLoading = signal(false);
  protected readonly statusLoading = signal(false);
  protected readonly copyrightsLoading = signal(false);
  protected readonly specialNameLoading = signal(false);
  protected readonly licenseLoading = signal(false);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly id$ = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    tap((id) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: PageId.MODER_PICTURE,
        title: $localize`Picture №${id}`,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #settingsModel = signal({year: '', month: '0', day: '0', specialName: ''});
  protected readonly settingsForm = form(this.#settingsModel, (path) => {
    maxLength(path.specialName, 255);
    maxLength(path.year, 4);
    validate(path.year, ({value}) =>
      value() !== '' && (!/^\d{4}$/.test(value()) || Number(value()) < 1800 || Number(value()) > 2030)
        ? {kind: 'year'}
        : undefined,
    );
    disabled(path, {when: () => this.specialNameLoading()});
  });
  readonly #licenseModel = signal({license: '0', sourceUrl: ''});
  protected readonly licenseForm = form(this.#licenseModel, (path) => {
    maxLength(path.sourceUrl, 500);
    disabled(path, {when: () => this.licenseLoading()});
  });
  protected readonly selectedLicense = computed(() =>
    numericEnumValue(PictureLicense, Number(this.#licenseModel().license), PictureLicense.PICTURE_LICENSE_UNKNOWN),
  );

  readonly #copyrightsModel = signal('');
  protected readonly copyrightsForm = form(this.#copyrightsModel, (path) => {
    maxLength(path, 65536);
    disabled(path, {when: () => this.copyrightsLoading()});
  });

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
      this.#notFound.report();
      return EMPTY;
    }),
    tap((picture) => {
      this.#copyrightsModel.set(picture.copyrights);
      this.#settingsModel.set({
        year: picture.takenDate?.year ? String(picture.takenDate.year) : '',
        month: String(picture.takenDate?.month ?? 0),
        day: String(picture.takenDate?.day ?? 0),
        specialName: picture.specialName,
      });
      this.#licenseModel.set({license: String(picture.license), sourceUrl: picture.sourceUrl});
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly changeStatusUser$: Observable<null | User> = this.picture$.pipe(
    // Authenticated lookups on this screen (and for the owner below): it is a moderation tool,
    // where an admin is meant to see a deleted account's name rather than the stub the cacheable
    // anonymous lookup returns.
    switchMap((picture) => this.#userService.getUser$(picture.changeStatusUserId, {authenticated: true})),
  );

  protected readonly owner$: Observable<null | User> = this.picture$.pipe(
    switchMap((picture) => this.#userService.getUser$(picture.ownerId, {authenticated: true})),
  );

  protected readonly ip$: Observable<IP | null> = this.picture$.pipe(
    switchMap((picture) => {
      if (!picture.ipAddress) {
        return of(null);
      }
      return this.#ipService.getIp$(picture.ipAddress, ['blacklist', 'rights']).pipe(catchError(() => of(null)));
    }),
  );

  protected readonly lastItem$: Observable<LastItemInfo> = this.picture$.pipe(
    switchMap((picture) => {
      const localStorage = this.#window?.localStorage;
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
            if (isNotFoundError(error)) {
              return of(null);
            }
            console.error(error);
            return throwError(() => error);
          }),
          map((item) => ({hasItem: item ? this.hasItem(picture.pictureItems?.items ?? [], item.id) : false, item})),
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
  readonly #banModel = signal({period: '1', reason: ''});
  protected readonly banForm = form(this.#banModel, (path) => {
    required(path.period);
    maxLength(path.reason, 50);
  });

  protected readonly licenseOptions: {label: string; value: PictureLicense}[] = pictureLicenseOptions.map((value) => ({
    label: getPictureLicenseTranslation(value.toString()),
    value,
  }));

  protected readonly monthOptions: {
    name: string;
    value: null | number;
  }[];
  protected readonly dayOptions: {
    name: string;
    value: null | number;
  }[];

  protected readonly ItemType = ItemType;
  protected readonly PictureItemType = PictureItemType;
  protected readonly PictureStatus = PictureStatus;

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

  protected getUser$(id: string): Observable<null | User> {
    return this.#userService.getUser$(id, {authenticated: true});
  }

  protected savePerspective(perspectiveId: null | number, item: PictureItem) {
    this.#picturesClient
      .updatePictureItem(
        new UpdatePictureItemRequest({
          pictureItem: new PictureItem({
            itemId: item.itemId,
            perspectiveId: perspectiveId ?? undefined,
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

  protected addItem(id: string, item: Item, type: PictureItemType) {
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

  protected moveItem(id: string, type: PictureItemType, srcItemId: string, dstItemId: string) {
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
    if (this.specialNameLoading() || this.settingsForm().invalid()) return;
    const model = this.#settingsModel();
    const takenDate = {year: Number(model.year), month: Number(model.month), day: Number(model.day)};
    const patch = new Picture({id: picture.id, specialName: model.specialName, takenDate});
    this.specialNameLoading.set(true);
    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          picture: patch,
          updateMask: new FieldMask({paths: ['special_name', 'taken_date']}),
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
          picture.specialName = patch.specialName;
          picture.takenDate = patch.takenDate;
          this.specialNameLoading.set(false);
        },
      });
  }

  protected saveCopyrights(picture: Picture) {
    if (this.copyrightsLoading() || this.copyrightsForm().invalid()) return;
    const copyrights = this.#copyrightsModel();
    this.copyrightsLoading.set(true);

    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          picture: new Picture({copyrights, id: picture.id}),
          updateMask: new FieldMask({paths: ['copyrights']}),
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
          picture.copyrights = copyrights;
          this.copyrightsLoading.set(false);
        },
      });
  }

  protected licenseNeedsSource(license: PictureLicense, sourceUrl: string): boolean {
    return pictureLicenseRequiresSourceUrl(license.toString()) && sourceUrl.trim() === '';
  }

  protected showLicenseHelp(): void {
    this.#modalService.open(LicenseHelpModal, {centered: true, size: 'lg'});
  }

  protected saveLicense(picture: Picture) {
    if (
      this.licenseLoading() ||
      this.licenseForm().invalid() ||
      this.licenseNeedsSource(this.selectedLicense(), this.#licenseModel().sourceUrl)
    ) {
      return;
    }

    const license = this.selectedLicense();
    const sourceUrl = this.#licenseModel().sourceUrl;
    this.licenseLoading.set(true);

    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          picture: new Picture({id: picture.id, license, sourceUrl}),
          updateMask: new FieldMask({paths: ['license', 'source_url']}),
        }),
      )
      .pipe(
        catchError((error: unknown) => {
          this.licenseLoading.set(false);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          picture.license = license;
          picture.sourceUrl = sourceUrl;
          this.licenseLoading.set(false);
        },
      });
  }

  private setPictureStatus(id: string, status: PictureStatus) {
    this.statusLoading.set(true);
    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({picture: new Picture({id, status}), updateMask: new FieldMask({paths: ['status']})}),
      )
      .pipe(
        catchError((err: unknown) => {
          this.#toastService.handleError(err);
          return EMPTY;
        }),
        // finalize, not error/next: catchError turns a failed call into a completion, so a
        // subscribe without a `complete` handler would otherwise leave the spinner stuck.
        finalize(() => {
          this.statusLoading.set(false);
        }),
      )
      .subscribe(() => {
        this.#change$.next();
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
      .deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ipAddress: ip}))
      .pipe(
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.#change$.next();
        },
      });
  }

  protected addToBlacklist(ip: string) {
    void submit(this.banForm, async () => {
      try {
        await firstValueFrom(
          this.#trafficClient.createTrafficBlacklistItem(
            new CreateTrafficBlacklistItemRequest({
              item: {
                ipAddress: ip,
                period: Number(this.#banModel().period),
                reason: this.#banModel().reason,
              },
            }),
          ),
        );
        this.#change$.next();
      } catch (error: unknown) {
        this.#toastService.handleError(error);
      }
    });
  }
}
