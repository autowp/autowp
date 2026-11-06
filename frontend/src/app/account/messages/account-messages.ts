import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, computed, effect, inject, input, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {MessagingGetMessagesRequest} from '@grpc/spec.pb';
import {MessagingClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth-service';
import {MessageService} from '@services/message';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {PastTimeIndicator} from '@utils/past-time-indicator/past-time-indicator';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {UserText} from '@utils/user-text/user-text';
import {MessageDialogService} from 'app/message-dialog/message-dialog.service';
import {Paginator} from 'app/paginator/paginator/paginator';
import {ToastsService} from 'app/toasts/toasts.service';
import {UserLink} from 'app/user/user/user-link';
import {map, of, switchMap, tap, timer} from 'rxjs';

@Component({
  selector: 'app-account-messages',
  imports: [UserLink, RouterLink, UserText, PastTimeIndicator, Paginator, ResourceStatus],
  templateUrl: './account-messages.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountMessages {
  readonly #messageService = inject(MessageService);
  readonly #messageDialogService = inject(MessageDialogService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #messagingClient = inject(MessagingClient);
  readonly #userService = inject(UserService);

  readonly #auth = inject(AuthService);
  readonly #revision = signal(0);
  readonly folder = input('inbox', {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? 'inbox',
  });
  readonly page = input(1, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 1,
  });
  readonly userId = input<string | undefined, RouteParam>(undefined, {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'user_id',
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value),
  });
  readonly #pageInfo = computed(() => {
    switch (this.folder()) {
      case 'dialog':
        return {pageId: PageId.ACCOUNT_MESSAGES_DIALOG, title: $localize`Personal messages`};
      case 'sent':
        return {pageId: PageId.ACCOUNT_MESSAGES_SENT, title: $localize`Sent`};
      case 'system':
        return {pageId: PageId.ACCOUNT_MESSAGES_SYSTEM, title: $localize`System messages`};
      default:
        return {pageId: PageId.ACCOUNT_MESSAGES, title: $localize`Inbox`};
    }
  });
  protected readonly pageName = computed(() => this.#pageInfo().title);
  protected readonly messagesResource = rxResource({
    params: () => {
      const authenticated = this.#auth.authenticated();
      if (authenticated === undefined) return undefined;
      // A defined inactive param cancels an in-flight authenticated read on logout.
      if (!authenticated) return null;
      return {
        folder: this.folder(),
        page: this.page(),
        userId: this.userId(),
        revision: this.#revision(),
        changed: this.#messageService.changed(),
      };
    },
    stream: ({params}) => {
      if (!params) return of(undefined);
      // Preserve the existing coalescing window for route changes, mutations and WebSocket echoes.
      // Resource cancellation also cancels the timer, before an obsolete RPC can start.
      return timer(50).pipe(
        switchMap(() =>
          this.#messagingClient.getMessages(
            new MessagingGetMessagesRequest({
              userId: params.userId,
              folder: params.folder,
              page: params.page,
            }),
          ),
        ),
        tap((response) => {
          this.#messageService.seen(response.items ?? []);
        }),
        switchMap((response) =>
          this.#userService.getUserMap$((response.items ?? []).map((message) => message.authorId)).pipe(
            map((authors) => ({
              items: (response.items ?? []).map((message) => ({message, author: authors.get(message.authorId)})),
              paginator: response.paginator,
              sendingBlocked: response.sendingBlocked,
            })),
          ),
        ),
      );
    },
  });
  protected readonly messages = computed(() =>
    this.messagesResource.hasValue() ? this.messagesResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      this.#pageEnv.set(this.#pageInfo());
    });
  }

  protected deleteMessage(id: string) {
    this.#messageService.deleteMessage$(id).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#revision.update((value) => value + 1);
      },
    });

    return false;
  }

  protected clearFolder(folder: string) {
    this.#messageService.clearFolder$(folder).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#revision.update((value) => value + 1);
      },
    });
  }

  protected openMessageForm(folder: string, userId: string) {
    this.#messageDialogService.showDialog(userId, () => {
      switch (folder) {
        case 'dialog':
        case 'sent':
          this.#revision.update((value) => value + 1);
          break;
      }
    });
    return false;
  }
}
