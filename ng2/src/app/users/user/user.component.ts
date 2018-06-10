import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { ContactsService } from '../../services/contacts';
import { MessageDialogService } from '../../services/message-dialog';
import { ACLService } from '../../services/acl.service';
import Notify from '../../notify';
import { Router, ActivatedRoute, Params } from '@angular/router';
import { UserService, APIUser } from '../../services/user';
import {
  Subscription,
  empty,
  combineLatest,
  from,
  of,
  forkJoin,
  Observable
} from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { PictureService, APIPicture } from '../../services/picture';
import { CommentService, APIComment } from '../../services/comment';
import { APIIP } from '../../services/ip';
import { PageEnvService } from '../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  tap,
  catchError,
  switchMap
} from 'rxjs/operators';

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
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    const fields =
      'identity,gravatar_hash,photo,renames,is_moder,reg_date,last_online,accounts,pictures_added,pictures_accepted_count,last_ip';

    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          return this.userService
            .getByIdentity(params.identity, { fields: fields })
            .pipe(
              catchError((err, caught) => {
                Notify.response(err);
                return empty();
              })
            );
        }),
        tap(user => {
          if (!user) {
            this.router.navigate(['/error-404']);
            return;
          }

          setTimeout(
            () =>
              this.pageEnv.set({
                layout: {
                  needRight: false
                },
                name: 'page/62/name',
                pageId: 62,
                args: {
                  USER_NAME: user.name,
                  USER_IDENTITY: user.identity
                    ? user.identity
                    : 'user' + user.id
                }
              }),
            0
          );

          this.user = user;
          this.isMe = this.auth.user && this.auth.user.id === user.id;
          this.canBeInContacts = this.auth.user && !user.deleted && !this.isMe;

          this.acl
            .isAllowed('user', 'ip')
            .then(
              allow => (this.canViewIp = !!allow),
              () => (this.canViewIp = false)
            );

          this.acl
            .isAllowed('user', 'ban')
            .then(
              allow => (this.canBan = !!allow),
              () => (this.canBan = false)
            );

          this.acl
            .isAllowed('user', 'delete')
            .then(
              allow => (this.canDeleteUser = !!allow),
              () => (this.canDeleteUser = false)
            );

          if (this.auth.user && !this.isMe) {
            this.Contacts.isInContacts(user.id).then(
              inContacts => {
                this.inContacts = inContacts;
              },
              response => {
                Notify.response(response);
              }
            );
          }
        }),
        switchMap(user => {
          const pictures = this.pictureService.getPictures({
            owner_id: user.id,
            limit: 12,
            order: 1,
            fields: 'url,name_html'
          });

          let comments = of(null);
          if (!this.user.deleted) {
            comments = this.commentService.getComments({
              user_id: this.user.id,
              limit: 15,
              order: 'date_desc',
              fields: 'preview,url'
            });
          }

          let ip = of(null);
          if (this.user.last_ip) {
            ip = this.loadBan(this.user.last_ip);
          }

          return forkJoin(pictures, comments, ip).pipe(
            tap(data => {
              this.pictures = data[0].pictures;
              this.comments = data[1].items;
              this.ip = data[2];
            })
          );
        })
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  private loadBan(ip: string): Observable<APIIP> {
    return this.http.get<APIIP>('/api/ip/' + ip, {
      params: {
        fields: 'blacklist,rights'
      }
    });
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
      .pipe(
        catchError((err, caught) => {
          Notify.response(err);
          return empty();
        }),
        switchMap(() => this.loadBan(this.user.last_ip)),
        catchError((err, caught) => {
          Notify.response(err);
          return empty();
        })
      )
      .subscribe(data => {
        this.ip = data;
      });
  }
}
