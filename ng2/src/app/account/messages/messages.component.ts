import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { MessageService, APIMessage } from '../../services/message';
import { MessageDialogService } from '../../services/message-dialog';
import Notify from '../../notify';
import { ActivatedRoute } from '@angular/router';
import { Subscription, BehaviorSubject } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

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
  private change$ = new BehaviorSubject<null>(null);

  constructor(
    private messageService: MessageService,
    private messageDialogService: MessageDialogService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          this.folder = params.folder || 'inbox';
          let pageId = null;
          let pageName = null;
          let userID = null;

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
              userID = params.user_id;
              break;
          }

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: pageName,
            pageId: pageId
          });

          return this.change$.pipe(
            switchMap(() =>
              this.messageService.getMessages({
                folder: this.folder,
                page: params.page || 1,
                fields: 'author.avatar,author.gravatar',
                user_id: userID ? userID : 0
              })
            )
          );
        })
      )
      .subscribe(
        response => {
          this.items = response.items;
          this.paginator = response.paginator;

          this.messageService.seen(response.items);
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public deleteMessage(id: number) {
    this.messageService.deleteMessage(id).subscribe(
      () => {
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
    this.messageService.clearFolder(folder).subscribe(
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
          this.change$.next(null);
          break;
      }
    });
    return false;
  }
}
