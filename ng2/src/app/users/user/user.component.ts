import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { ContactsService } from '../../services/contacts';
import { MessageDialogService } from '../../services/message-dialog';
import { ACLService } from '../../services/acl.service';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { UserService, APIUser } from '../../services/user';
import { Subscription } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { PictureService, APIPicture } from '../../services/picture';
import { CommentService, APIComment } from '../../services/comment';
import { APIIP } from '../../services/ip';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-users-user',
  templateUrl: './user.component.html'
})
@Injectable()
export class UsersUserComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public user: APIUser;
  public banPeriods = [
    { value: 1, name: 'ban/period/hour' },
    { value: 2, name: 'ban/period/2-hours' },
    { value: 4, name: 'ban/period/4-hours' },
    { value: 8, name: 'ban/period/8-hours' },
    { value: 16, name: 'ban/period/16-hours' },
    { value: 24, name: 'ban/period/day' },
    { value: 48, name: 'ban/period/2-days' }
  ];
  public banPeriod = 1;
  public banReason: string | null = null;
  public ip: APIIP;
  public inContacts = false;
  public comments: APIComment[];
  public pictures: APIPicture[];
  public canDeleteUser = false;
  public isMe = false;
  public canBeInContacts = false;
  public canViewIp = false;
  public canBan = false;
  public isModer = false;

  constructor(
    private http: HttpClient,
    private Contacts: ContactsService,
    private messageDialogService: MessageDialogService,
    private acl: ACLService,
    private router: Router,
    private userService: UserService,
    private route: ActivatedRoute,
    private auth: AuthService,
    private pictureService: PictureService,
    private commentService: CommentService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    console.log('123');
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    this.routeSub = this.route.params.subscribe(params => {
      const result = params.identity.match(/^user([0-9]+)$/);

      const fields =
        'identity,gravatar_hash,photo,renames,is_moder,reg_date,last_online,accounts,pictures_added,pictures_accepted_count,last_ip';

      if (result) {
        this.userService
          .getUser(result[1], {
            fields: fields
          })
          .then(
            user => {
              this.user = user;
              this.init();
            },
            response => Notify.response(response)
          );
      } else {
        this.userService
          .get({
            identity: params.identity,
            limit: 1,
            fields: fields
          })
          .subscribe(
            response => {
              if (response.items.length <= 0) {
                this.router.navigate(['/error-404']);
                return;
              }
              this.user = response.items[0];
              this.init();
            },
            response => Notify.response(response)
          );
      }
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public init() {
    if (this.user.deleted) {
      this.router.navigate(['/error-404']);
      return;
    }

    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/62/name',
      pageId: 62,
      args: {
        USER_NAME: this.user.name,
        USER_IDENTITY: this.user.identity
          ? this.user.identity
          : 'user' + this.user.id
      }
    });

    this.acl
      .isAllowed('user', 'ip')
      .then(
        allow => (this.canViewIp = !!allow),
        () => (this.canViewIp = false)
      );

    this.acl
      .isAllowed('user', 'ban')
      .then(allow => (this.canBan = !!allow), () => (this.canBan = false));

    this.isMe = this.auth.user && this.auth.user.id === this.user.id;
    this.canBeInContacts = this.auth.user && !this.user.deleted && !this.isMe;

    if (this.auth.user && !this.isMe) {
      this.Contacts.isInContacts(this.user.id).then(
        inContacts => {
          this.inContacts = inContacts;
        },
        response => {
          Notify.response(response);
        }
      );
    }

    this.acl.isAllowed('user', 'delete').then(
      allow => {
        this.canDeleteUser = !!allow;
      },
      () => {
        this.canDeleteUser = false;
      }
    );

    this.pictureService
      .getPictures({
        owner_id: this.user.id,
        limit: 12,
        order: 1,
        fields: 'url,name_html'
      })
      .subscribe(
        response => {
          this.pictures = response.pictures;
        },
        response => {
          Notify.response(response);
        }
      );

    if (!this.user.deleted) {
      this.commentService
        .getComments({
          user_id: this.user.id,
          limit: 15,
          order: 'date_desc',
          fields: 'preview,url'
        })
        .subscribe(
          response => {
            this.comments = response.items;
          },
          response => {
            Notify.response(response);
          }
        );
    }

    if (this.user.last_ip) {
      this.loadBan(this.user.last_ip);
    }
  }

  private loadBan(ip: string) {
    this.http
      .get<APIIP>('/api/ip/' + ip, {
        params: {
          fields: 'blacklist,rights'
        }
      })
      .subscribe(
        response => {
          this.ip = response;
        },
        response => {
          if (response.status === 404) {
            this.ip = null;
          } else {
            Notify.response(response);
          }
        }
      );
  }

  public openMessageForm() {
    this.messageDialogService.showDialog(this.user.id);
    return false;
  }

  public toggleInContacts() {
    this.http
      .request<void>(
        this.inContacts ? 'DELETE' : 'PUT',
        '/api/contacts/' + this.user.id,
        {
          observe: 'response'
        }
      )
      .subscribe(
        response => {
          switch (response.status) {
            case 204:
              this.inContacts = false;
              break;
            case 200:
              this.inContacts = true;
              break;
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public deletePhoto() {
    if (!window.confirm('Are you sure?')) {
      return;
    }

    this.http.delete<void>('/api/user/' + this.user.id + '/photo').subscribe(
      response => {
        this.user.photo = null;
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public deleteUser() {
    if (!window.confirm('Are you sure?')) {
      return;
    }
    this.http
      .put<void>('/api/user/' + this.user.id, {
        deleted: true
      })
      .subscribe(
        response => {
          this.user.deleted = true;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public unban() {
    this.http
      .delete<void>('/api/traffic/blacklist/' + this.ip.address)
      .subscribe(
        response => {},
        response => {
          Notify.response(response);
        }
      );
  }

  public removeFromBlacklist() {
    this.http
      .delete<void>('/api/traffic/blacklist/' + this.ip.address)
      .subscribe(
        response => {
          this.ip.blacklist = null;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public addToBlacklist() {
    this.http
      .post<void>('/api/traffic/blacklist', {
        ip: this.ip.address,
        period: this.banPeriod,
        reason: this.banReason
      })
      .subscribe(
        response => {
          this.loadBan(this.user.last_ip);
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
