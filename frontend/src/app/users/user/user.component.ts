import type {User} from '@grpc/spec.pb';

import {AsyncPipe, DatePipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CommentMessageFields,
  CreateContactRequest,
  CreateTrafficBlacklistItemRequest,
  DeleteContactRequest,
  DeleteTrafficBlacklistItemRequest,
  DeleteUserPhotoRequest,
  DeleteUserRequest,
  GetMessagesRequest,
  GetUserAchievementsRequest,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
  UserFields,
  UserPreferencesRequest,
} from '@grpc/spec.pb';
import {
  AchievementsClient,
  CommentsClient,
  ContactsClient,
  PicturesClient,
  TrafficClient,
  UsersClient,
} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {AppContactsService} from '@services/contacts';
import {IpService} from '@services/ip';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {getAchievementDescriptionTranslation, getAchievementTranslation} from '@utils/translations';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {catchError, map, of, switchMap} from 'rxjs';

import {MessageDialogService} from '../../message-dialog/message-dialog.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-users-user',
  imports: [RouterLink, NgbTooltip, UserComponent, FormsModule, AsyncPipe, DatePipe, TimeAgoPipe],
  templateUrl: './user.component.html',
  styleUrl: './user.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserComponent {
  readonly #appContactsService = inject(AppContactsService);
  readonly #messageDialogService = inject(MessageDialogService);
  readonly #router = inject(Router);
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #ipService = inject(IpService);
  readonly #contactsClient = inject(ContactsClient);
  readonly #usersGrpc = inject(UsersClient);
  readonly #trafficClient = inject(TrafficClient);
  readonly #commentsClient = inject(CommentsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);
  readonly #achievementsClient = inject(AchievementsClient);

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
  protected readonly canDeleteUser$ = this.#auth.hasRole$(Role.ADMIN);
  protected readonly canViewIp$ = this.#auth.hasRole$(Role.MODER);
  protected readonly canBan$ = this.#auth.hasRole$(Role.USERS_MODER);
  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected readonly timestampToDate = timestampToDate;

  readonly #identity = toSignal(this.#route.paramMap.pipe(map((params) => params.get('identity') ?? '')), {
    requireSync: true,
  });

  protected readonly userResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `users-user-${this.#identity()}`,
    params: () => this.#identity(),
    stream: ({params: identity}) =>
      this.#userService
        .getByIdentity$(
          identity,
          new UserFields({
            gravatarLarge: true,
            lastIp: true,
            lastOnline: true,
            photo: true,
            picturesAcceptedCount: true,
            picturesAdded: true,
            regDate: true,
          }),
        )
        .pipe(switchMap((user) => (user ? of(user) : notFoundError()))),
  });

  protected readonly picturesResource = rxResource({
    id: `users-user-pictures-${this.#identity()}`,
    params: () => this.userResource.value()?.id,
    stream: ({params: userId}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: new PictureFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 12,
            options: new PictureListOptions({ownerId: userId}),
            order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
            paginator: false,
          }),
        )
        .pipe(map((response) => response.items ?? [])),
  });

  protected readonly commentsResource = rxResource({
    id: `users-user-comments-${this.#identity()}`,
    params: () => {
      const user = this.userResource.value();

      return user ? {deleted: user.deleted, userId: user.id} : undefined;
    },
    stream: ({params: {deleted, userId}}) => {
      if (deleted) {
        return of([]);
      }

      return this.#commentsClient
        .getMessages(
          new GetMessagesRequest({
            fields: new CommentMessageFields({
              preview: true,
              route: true,
            }),
            limit: 15,
            order: GetMessagesRequest.Order.DATE_DESC,
            userId,
          }),
        )
        .pipe(map((response) => response.items ?? []));
    },
  });

  protected readonly achievementsResource = rxResource({
    id: `users-user-achievements-${this.#identity()}`,
    params: () => this.userResource.value()?.id,
    stream: ({params: userId}) =>
      this.#achievementsClient
        .getUserAchievements(new GetUserAchievementsRequest({userId}))
        .pipe(map((response) => ({items: response.items ?? [], progress: response.progress ?? []}))),
  });

  readonly #authenticated = toSignal(this.#auth.authenticated$, {initialValue: false});
  readonly #currentUser = toSignal(this.#auth.user$, {initialValue: null});

  protected readonly authenticated$ = this.#auth.authenticated$;

  protected readonly isNotMe = computed(() => {
    const user = this.userResource.value();
    const currentUser = this.#currentUser();

    return !user || currentUser?.id !== user.id;
  });

  protected readonly ipResource = rxResource({
    id: `users-user-ip-${this.#identity()}`,
    params: () => this.userResource.value()?.lastIp,
    stream: ({params: lastIp}) => {
      if (!lastIp) {
        return of(null);
      }

      return this.#ipService.getIp$(lastIp, ['blacklist', 'rights']).pipe(catchError(() => of(null)));
    },
  });

  protected readonly inContactsResource = rxResource({
    id: `users-user-in-contacts-${this.#identity()}`,
    params: () => {
      const user = this.userResource.value();

      return user ? {authenticated: this.#authenticated(), isNotMe: this.isNotMe(), userId: user.id} : undefined;
    },
    stream: ({params: {authenticated, isNotMe, userId}}) => {
      if (!authenticated || !isNotMe) {
        return of(false);
      }

      return this.#appContactsService.isInContacts$(userId);
    },
  });

  protected readonly disableCommentsNotificationsResource = rxResource({
    id: `users-user-disable-comments-notifications-${this.#identity()}`,
    params: () => {
      const user = this.userResource.value();

      return user ? {authenticated: this.#authenticated(), isNotMe: this.isNotMe(), userId: user.id} : undefined;
    },
    stream: ({params: {authenticated, isNotMe, userId}}) => {
      if (!authenticated || !isNotMe) {
        return of(false);
      }

      return this.#usersGrpc
        .getUserPreferences(new UserPreferencesRequest({userId}))
        .pipe(map(({disableCommentsNotifications}) => disableCommentsNotifications));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.userResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const user = this.userResource.value();
      if (user) {
        this.#pageEnv.set({
          pageId: 62,
          title: user.name,
        });
      }
    });
  }

  protected openMessageForm(user: User) {
    this.#messageDialogService.showDialog(user.id);
    return false;
  }

  protected setInContacts(user: User, value: boolean) {
    if (value) {
      this.#contactsClient
        .createContact(new CreateContactRequest({contact: {contactUserId: user.id}}))
        .subscribe(() => {
          this.inContactsResource.reload();
        });
      return;
    }

    this.#contactsClient.deleteContact(new DeleteContactRequest({userId: user.id})).subscribe(() => {
      this.inContactsResource.reload();
    });
  }

  protected setCommentNotificationsDisabled(user: User, value: boolean) {
    if (value) {
      this.#usersGrpc.disableUserCommentsNotifications(new UserPreferencesRequest({userId: user.id})).subscribe(() => {
        this.disableCommentsNotificationsResource.reload();
      });
      return;
    }

    this.#usersGrpc.enableUserCommentsNotifications(new UserPreferencesRequest({userId: user.id})).subscribe(() => {
      this.disableCommentsNotificationsResource.reload();
    });
  }

  protected deletePhoto(user: User) {
    if (!this.#document.defaultView?.confirm('Are you sure?')) {
      return;
    }

    this.#usersGrpc.deleteUserPhoto(new DeleteUserPhotoRequest({id: user.id})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        user.photo = undefined;
      },
    });
  }

  protected deleteUser(user: User) {
    if (!this.#document.defaultView?.confirm('Are you sure?')) {
      return;
    }
    this.#usersGrpc
      .deleteUser(
        new DeleteUserRequest({
          userId: user.id,
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          user.deleted = true;
        },
      });
  }

  protected removeFromBlacklist(ip: string) {
    this.#trafficClient.deleteTrafficBlacklistItem(new DeleteTrafficBlacklistItemRequest({ipAddress: ip})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.ipResource.reload();
      },
    });
  }

  protected addToBlacklist(ip: string) {
    this.#trafficClient
      .createTrafficBlacklistItem(
        new CreateTrafficBlacklistItemRequest({
          item: {
            ipAddress: ip,
            period: this.banPeriod,
            reason: this.banReason ?? '',
          },
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.ipResource.reload();
        },
      });
  }

  protected getAchievementTranslation(code: string): string {
    return getAchievementTranslation(code);
  }

  protected getAchievementDescriptionTranslation(code: string): string {
    return getAchievementDescriptionTranslation(code);
  }
}
