import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ContactsService } from '../../services/contacts';
import { MessageDialogService } from '../../services/message-dialog';
import { ACLService } from '../../services/acl.service';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { UserService, APIUser } from '../../services/user';
import {
  Subscription,
  empty,
  of,
  forkJoin,
  Observable,
  combineLatest
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
  switchMap,
  switchMapTo
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
  private aclSub: Subscription;

  constructor(
    private http: HttpClient,
    private contacts: ContactsService,
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
    this.aclSub = this.acl
      .inheritsRole('moder')
      .subscribe(isModer => (this.isModer = isModer));

    const fields =
      'identity,gravatar_hash,photo,renames,is_moder,reg_date,last_online,accounts,pictures_added,pictures_accepted_count,last_ip';

    this.routeSub = this.auth
      .getUser()
      .pipe(
        switchMapTo(this.route.params, (currentUser, params) => ({
          currentUser,
          params
        })),
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          data =>
            combineLatest(
              this.userService
                .getByIdentity(data.params.identity, { fields: fields })
                .pipe(
                  catchError((err, caught) => {
                    Notify.response(err);
                    return empty();
                  })
                ),
              this.acl.isAllowed('user', 'ip'),
              this.acl.isAllowed('user', 'ban'),
              this.acl.isAllowed('user', 'delete')
            ),
          (data, user) => ({
            currentUser: data.currentUser,
            user: user[0],
            canViewIp: user[1],
            canBan: user[2],
            canDeleteUser: user[3]
          })
        ),
        tap(data => {
          if (!data.user) {
            this.router.navigate(['/error-404']);
            return;
          }

          this.canViewIp = data.canViewIp;
          this.canBan = data.canBan;
          this.canDeleteUser = data.canDeleteUser;

          setTimeout(
            () =>
              this.pageEnv.set({
                layout: {
                  needRight: false
                },
                name: 'page/62/name',
                pageId: 62,
                args: {
                  USER_NAME: data.user.name,
                  USER_IDENTITY: data.user.identity
                    ? data.user.identity
                    : 'user' + data.user.id
                }
              }),
            0
          );

          this.user = data.user;
          this.isMe = data.currentUser && data.currentUser.id === data.user.id;
          this.canBeInContacts =
            data.currentUser && !data.user.deleted && !this.isMe;

          if (data.currentUser && !this.isMe) {
            this.contacts
              .isInContacts(data.user.id)
              .subscribe(
                inContacts => (this.inContacts = inContacts),
                response => Notify.response(response)
              );
          }
        }),
        switchMap(data => {
          const pictures = this.pictureService.getPictures({
            owner_id: data.user.id,
            limit: 12,
            order: 1,
            fields: 'url,name_html'
          });

          let comments = of(null);
          if (!data.user.deleted) {
            comments = this.commentService.getComments({
              user_id: data.user.id,
              limit: 15,
              order: 'date_desc',
              fields: 'preview,url'
            });
          }

          let ip = of(null);
          if (data.user.last_ip) {
            ip = this.loadBan(data.user.last_ip);
          }

          return forkJoin(pictures, comments, ip).pipe(
            tap(data2 => {
              this.pictures = data2[0].pictures;
              this.comments = data2[1].items;
              this.ip = data2[2];
            })
          );
        })
      )
      .subscribe();
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.aclSub.unsubscribe();
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
