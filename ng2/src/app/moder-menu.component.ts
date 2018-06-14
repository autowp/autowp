import { Component, Injectable, OnInit } from '@angular/core';
import { AuthService } from './services/auth.service';
import { ACLService } from './services/acl.service';
import { PictureService } from './services/picture';
import { CommentService } from './services/comment';

interface MenuItem {
  routerLink: string[];
  queryParams?: { [key: string]: string };
  label: string;
  count?: number;
  icon: string;
}

@Component({
  selector: 'app-moder-menu',
  templateUrl: './moder-menu.component.html'
})
@Injectable()
export class ModerMenuComponent implements OnInit {
  public items: MenuItem[] = [];

  constructor(
    public auth: AuthService,
    public acl: ACLService,
    private pictureService: PictureService,
    private commentService: CommentService
  ) {}

  ngOnInit() {
    this.auth.getUser().subscribe(value => {
      this.loadItems();
    });
  }

  private loadItems() {
    this.acl.inheritsRole('moder').then(
      isModer => {
        this.acl.inheritsRole('pages-moder').then(
          isPagesModer => {
            this.items = [];
            if (isModer) {
              const inboxItem = {
                routerLink: ['/moder/pictures'],
                queryParams: {
                  order: '1',
                  status: 'inbox'
                },
                label: 'moder-menu/inbox',
                count: 0,
                icon: 'fa fa-th'
              };
              this.items.push(inboxItem);

              this.loadInboxSize(inboxItem);

              const attentionItem = {
                routerLink: ['/moder/comments'],
                queryParams: {
                  moderator_attention: '1'
                },
                label: 'page/110/name',
                count: 0,
                icon: 'fa fa-comment'
              };
              this.items.push(attentionItem);

              this.loadCommentsCount(attentionItem);

              if (isPagesModer) {
                this.items.push({
                  routerLink: ['/moder/pages'],
                  label: 'page/68/name',
                  icon: 'fa fa-book'
                });
              }

              this.items.push({
                routerLink: ['/moder/items'],
                label: 'page/131/name',
                icon: 'fa fa-car'
              });
            }
          },
          error => {
            this.items = [];
            console.log(error);
          }
        );
      },
      error => {
        this.items = [];
        console.log(error);
      }
    );
  }

  private loadInboxSize(item: MenuItem) {
    this.pictureService
      .getPictures({
        status: 'inbox',
        limit: 0
      })
      .subscribe(
        response => {
          item.count = response.paginator.totalItemCount;
        },
        response => {
          console.log(response);
        }
      );
  }

  private loadCommentsCount(item: MenuItem) {
    this.commentService
      .getComments({
        moderator_attention: '1',
        limit: 0
      })
      .subscribe(
        response => {
          item.count = response.paginator.totalItemCount;
        },
        response => {
          console.log(response);
        }
      );
  }
}
