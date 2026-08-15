import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink, RouterOutlet} from '@angular/router';
import {ForumsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth.service';
import {MessageService} from '@services/message';
import {PageEnvService} from '@services/page-env.service';
import {PictureService} from '@services/picture';
import {combineLatest, map, of, shareReplay, switchMap} from 'rxjs';

interface SidebarItem {
  active?: boolean;
  count?: number;
  icon?: string;
  name: string;
  newCount?: number;
  pageId?: number;
  routerLink?: string[];
  routerLinkParams?: Record<string, string>;
}

@Component({
  selector: 'app-account',
  imports: [RouterLink, RouterOutlet, AsyncPipe],
  templateUrl: './account.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountComponent {
  readonly #messageService = inject(MessageService);
  readonly #auth = inject(AuthService);
  readonly #pictureService = inject(PictureService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #forumsClient = inject(ForumsClient);

  protected readonly items$: Observable<SidebarItem[]> = combineLatest([
    this.#auth.user$,
    this.#auth.authenticated$.pipe(
      switchMap((authenticated) => {
        if (!authenticated) {
          return of(null);
        }
        return this.#forumsClient.getUserSummary(new Empty());
      }),
      shareReplay({bufferSize: 1, refCount: false}),
    ),
    this.#messageService.getSummary$(),
    this.#pictureService.summary$,
  ]).pipe(
    map(([user, forumSummary, messageSummary, picturesSummary]) => {
      if (!user) {
        return [] as SidebarItem[];
      }
      const items: SidebarItem[] = [
        {
          icon: 'bi-person',
          name: $localize`Profile`,
          pageId: 129,
          routerLink: ['/account/profile'],
        },
        {
          icon: 'bi-person-lines-fill',
          name: $localize`Contacts`,
          pageId: 198,
          routerLink: ['/account/contacts'],
        },
        {
          icon: 'bi-envelope-open',
          name: $localize`My e-mail`,
          pageId: 55,
          routerLink: ['/account/email'],
        },
        {
          icon: 'bi-lock',
          name: $localize`Access Control`,
          pageId: 133,
          routerLink: ['/account/access'],
        },
        {
          icon: 'bi-asterisk',
          name: $localize`My accounts`,
          pageId: 123,
          routerLink: ['/account/accounts'],
        },
        {
          count: picturesSummary?.acceptedCount,
          icon: 'bi-grid-3x2-gap-fill',
          name: $localize`My pictures`,
          pageId: 130,
          routerLink: ['/users', user.identity ? user.identity : 'user' + user.id, 'pictures'],
        },
        {
          count: picturesSummary?.inboxCount,
          icon: 'bi-grid-3x2-gap-fill',
          name: $localize`Unmoderated`,
          pageId: 94,
          routerLink: ['/account/inbox-pictures'],
        },
        {
          count: forumSummary ? forumSummary.subscriptionsCount : undefined,
          icon: 'bi-bookmark',
          name: $localize`Forums subscriptions`,
          pageId: 57,
          routerLink: ['/forums/subscriptions'],
        },
        {
          name: $localize`Specifications`,
        },
        {
          icon: 'bi-exclamation-triangle',
          name: $localize`Conflicts`,
          pageId: 188,
          routerLink: ['/account/specs-conflicts'],
        },
        {
          name: $localize`Personal messages`,
        },
        {
          count: messageSummary ? messageSummary.inboxCount : undefined,
          icon: 'bi-chat-text',
          name: $localize`Inbox`,
          newCount: messageSummary ? messageSummary.inboxNewCount : undefined,
          pageId: 128,
          routerLink: ['/account/messages'],
        },
        {
          count: messageSummary ? messageSummary.sentCount : undefined,
          icon: 'bi-chat-text',
          name: $localize`Sent`,
          pageId: 80,
          routerLink: ['/account/messages'],
          routerLinkParams: {folder: 'sent'},
        },
        {
          count: messageSummary ? messageSummary.systemCount : undefined,
          icon: 'bi-chat-text',
          name: $localize`System messages`,
          newCount: messageSummary ? messageSummary.systemNewCount : undefined,
          pageId: 81,
          routerLink: ['/account/messages'],
          routerLinkParams: {folder: 'system'},
        },
      ];

      return items;
    }),
    // Merges each item's active state in reactively instead of subscribing per item inside the
    // map() above: that pattern started a fresh, never-cleaned-up isActive$ subscription per item
    // on every re-emission of the outer combineLatest (a subscription leak), and mutating `active`
    // after the array had already been emitted didn't notify AsyncPipe of anything to re-render.
    switchMap((items) => {
      const withActive$ = items.map((item) =>
        item.pageId ? this.#pageEnv.isActive$(item.pageId).pipe(map((active) => ({...item, active}))) : of(item),
      );
      return withActive$.length > 0 ? combineLatest(withActive$) : of(items);
    }),
  );
}
