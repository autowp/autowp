import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { MessageService, APIMessage } from '../../services/message';
import { MessageDialogService } from '../../services/message-dialog';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-account-messages',
  templateUrl: './messages.component.html'
})
@Injectable()
export class AccountMessagesComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public folder: string;
  public items: APIMessage[] = [];
  public paginator: APIPaginator | null;
  private userId = 0;
  private page = 1;

  constructor(
    private messageService: MessageService,
    private messageDialogService: MessageDialogService,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.folder = params.folder || 'inbox';
      this.page = params.page || 1;
      let pageId = null;
      let pageName = null;

      switch (this.folder) {
        case 'inbox':
          pageId = 128;
          pageName = 'page/128/name';
          break;
        case 'sent':
          pageId = 80;
          pageName = 'page/80/name';
          break;
        case 'system':
          pageId = 81;
          pageName = 'page/81/name';
          break;
        case 'dialog':
          pageId = 49;
          pageName = 'page/49/name';
          this.userId = params.user_id;
          break;
      }

      this.pageEnv.set({
        layout: {
          needRight: false
        },
        name: pageName,
        pageId: pageId
      });

      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  private load() {
    this.messageService
      .getMessages({
        folder: this.folder,
        page: this.page,
        fields: 'author.avatar,author.gravatar',
        user_id: this.userId ? this.userId : 0
      })
      .subscribe(
        response => {
          this.items = response.items;
          this.paginator = response.paginator;

          let newFound = false;
          for (const message of this.items) {
            if (message.is_new) {
              newFound = true;
            }
          }

          if (newFound) {
            this.messageService.refreshNewMessagesCount();
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public deleteMessage(id: number) {
    this.messageService.deleteMessage(id).then(
      (response: any) => {
        for (let i = 0; i < this.items.length; i++) {
          if (this.items[i].id === id) {
            this.items.splice(i, 1);
            break;
          }
        }
      },
      response => {
        Notify.response(response);
      }
    );

    return false;
  }

  public clearFolder(folder: string) {
    this.messageService.clearFolder(folder).then(
      () => {
        if (this.folder === folder) {
          this.items = [];
          this.paginator = null;
        }
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public openMessageForm(userId: number) {
    this.messageDialogService.showDialog(userId, () => {
      switch (this.folder) {
        case 'sent':
        case 'dialog':
          this.load();
          break;
      }
    });
  }
}
