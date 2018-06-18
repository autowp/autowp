import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { MessageService } from '../../services/message';
import { ForumService } from '../../services/forum';
import { PageService } from '../../services/page';
import Notify from '../../notify';
import { AuthService } from '../../services/auth.service';
import { tap } from 'rxjs/operators';
import { combineLatest, Subscription } from 'rxjs';
import { PictureService } from '../../services/picture';
import { PageEnvService } from '../../services/page-env.service';

interface SidebarItem {
  pageId?: number;
  routerLink?: string[];
  icon?: string;
  name: string;
  count?: number;
  newCount?: number;
  routerLinkParams?: any;
  active?: boolean;
}

@Component({
  selector: 'app-account-sidebar',
  templateUrl: './sidebar.component.html'
})
@Injectable()
export class AccountSidebarComponent implements OnInit, OnDestroy {
  public items: SidebarItem[];
  private sub: Subscription;

  constructor(
    private messageService: MessageService,
    private forumService: ForumService,
    private pageService: PageService,
    private auth: AuthService,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.sub = combineLatest(
      this.auth.getUser(),
      this.forumService.getUserSummary(),
      this.messageService.getSummary(),
      this.pictureService.getSummary(),
      (user, forumSummary, messageSummary, picturesSummary) => ({
        user,
        forumSummary,
        messageSummary,
        picturesSummary
      })
    )
      .pipe(
        tap(data => {
          if (!data.user) {
            return;
          }
          this.items = [
            {
              pageId: 129,
              routerLink: ['/account/profile'],
              icon: 'user',
              name: 'page/129/name'
            },
            {
              pageId: 198,
              routerLink: ['/account/contacts'],
              icon: 'address-book',
              name: 'page/198/name'
            },
            {
              pageId: 55,
              routerLink: ['/account/email'],
              icon: 'envelope-o',
              name: 'page/55/name'
            },
            {
              pageId: 133,
              routerLink: ['/account/access'],
              icon: 'lock',
              name: 'page/133/name'
            },
            {
              pageId: 123,
              routerLink: ['/account/accounts'],
              icon: 'asterisk',
              name: 'page/123/name'
            },
            {
              pageId: 130,
              routerLink: [
                '/users',
                data.user.identity ? data.user.identity : 'user' + data.user.id,
                'pictures'
              ],
              icon: 'th',
              name: 'page/130/name',
              count: data.picturesSummary
                ? data.picturesSummary.acceptedCount
                : null
            },
            {
              pageId: 94,
              routerLink: ['/account/inbox-pictures'],
              icon: 'th',
              name: 'page/94/name',
              count: data.picturesSummary
                ? data.picturesSummary.inboxCount
                : null
            },
            {
              pageId: 57,
              routerLink: ['/forums/subscriptions'],
              icon: 'bookmark',
              name: 'page/57/name',
              count: data.forumSummary
                ? data.forumSummary.subscriptionsCount
                : null
            },
            {
              name: 'catalogue/specifications'
            },
            {
              pageId: 188,
              routerLink: ['/account/specs-conflicts'],
              icon: 'exclamation-triangle',
              name: 'page/188/name'
            },
            {
              name: 'page/49/name'
            },
            {
              pageId: 128,
              routerLink: ['/account/messages'],
              icon: 'comments-o',
              name: 'page/128/name',
              count: data.messageSummary
                ? data.messageSummary.inbox.count
                : null,
              newCount: data.messageSummary
                ? data.messageSummary.inbox.new_count
                : null
            },
            {
              pageId: 80,
              routerLink: ['/account/messages'],
              routerLinkParams: { folder: 'sent' },
              icon: 'comments-o',
              name: 'page/80/name',
              count: data.messageSummary ? data.messageSummary.sent.count : null
            },
            {
              pageId: 81,
              routerLink: ['/account/messages'],
              routerLinkParams: { folder: 'system' },
              icon: 'comments',
              name: 'page/81/name',
              count: data.messageSummary
                ? data.messageSummary.system.count
                : null,
              newCount: data.messageSummary
                ? data.messageSummary.system.new_count
                : null
            }
          ];

          for (const item of this.items) {
            if (item.pageId) {
              this.pageEnv.isActive(item.pageId).subscribe(
                active => {
                  item.active = active;
                },
                response => {
                  Notify.response(response);
                }
              );
            }
          }
        })
      )
      .subscribe();
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }
}
