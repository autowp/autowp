import { Component, Injectable, OnDestroy, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { MessageService, MessageCallbackType } from '../../services/message';
import { ForumService } from '../../services/forum';
import { PageService } from '../../services/page';
import Notify from '../../notify';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';

export interface APIPictureUserSummary {
  inboxCount: number;
  acceptedCount: number;
}

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
  private handler: MessageCallbackType;

  constructor(
    private messageService: MessageService,
    private http: HttpClient,
    private forumService: ForumService,
    private pageService: PageService,
    private auth: AuthService,
    private router: Router
  ) {
    if (!this.auth.user) {
      // TODO: use guard
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
          this.auth.user.identity
            ? this.auth.user.identity
            : 'user' + this.auth.user.id,
          'pictures'
        ],
        icon: 'th',
        name: 'page/130/name'
      },
      {
        pageId: 94,
        routerLink: ['/account/inbox-pictures'],
        icon: 'th',
        name: 'page/94/name'
      },
      {
        pageId: 57,
        routerLink: ['/forums/subscriptions'],
        icon: 'bookmark',
        name: 'page/57/name'
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
        name: 'page/128/name'
      },
      {
        pageId: 80,
        routerLink: ['/account/messages'],
        routerLinkParams: { folder: 'sent' },
        icon: 'comments-o',
        name: 'page/80/name'
      },
      {
        pageId: 81,
        routerLink: ['/account/messages'],
        routerLinkParams: { folder: 'system' },
        icon: 'comments',
        name: 'page/81/name'
      }
    ];

    this.loadMessageSummary();

    this.forumService.getUserSummary().then(
      data => {
        this.items[7].count = data.subscriptionsCount;
      },
      response => {
        Notify.response(response);
      }
    );

    this.http.get<APIPictureUserSummary>('/api/picture/user-summary').subscribe(
      response => {
        this.items[6].count = response.inboxCount;
        this.items[5].count = response.acceptedCount;
      },
      response => {
        Notify.response(response);
      }
    );

    for (const item of this.items) {
      if (item.pageId) {
        this.pageService.isActive(item.pageId).then(
          (active: boolean) => {
            item.active = active;
          },
          response => {
            Notify.response(response);
          }
        );
      }
    }

    this.handler = () => {
      this.loadMessageSummary();
    };
  }

  ngOnInit(): void {
    this.messageService.bind('sent', this.handler);
    this.messageService.bind('deleted', this.handler);
  }
  ngOnDestroy(): void {
    this.messageService.unbind('sent', this.handler);
    this.messageService.unbind('deleted', this.handler);
  }

  private loadMessageSummary() {
    this.messageService.getSummary().then(
      data => {
        this.items[11].count = data.inbox.count;
        this.items[11].newCount = data.inbox.new_count;

        this.items[12].count = data.sent.count;

        this.items[13].count = data.system.count;
        this.items[13].newCount = data.system.new_count;
      },
      response => {
        Notify.response(response);
      }
    );
  }
}
