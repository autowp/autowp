import {
  Component,
  Injectable,
  ViewChild,
  ElementRef,
  OnInit,
  OnDestroy
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { APIUser } from '../../services/user';
import { FileUploader, FileItem, ParsedResponseHeaders } from 'ng2-file-upload';
import { PageEnvService } from '../../services/page-env.service';
import { combineLatest, empty, of, Subscription } from 'rxjs';
import { switchMapTo, switchMap } from 'rxjs/operators';
import { LanguageService } from '../../services/language';
import { TimezoneService } from '../../services/timezone';

@Component({
  selector: 'app-account-profile',
  templateUrl: './profile.component.html'
})
@Injectable()
export class AccountProfileComponent implements OnInit, OnDestroy {
  @ViewChild('fileInput') fileInput: ElementRef;

  private user: APIUser;
  public profile = {
    name: null
  };
  public profileInvalidParams: any = {};
  public settings = {
    timezone: null,
    language: null
  };
  public settingsInvalidParams: any = {};
  public photoInvalidParams: any = {};
  public votesPerDay: number | null = null;
  public votesLeft: number | null = null;
  public photo: any;
  public timezones: string[];
  public languages: { name: string; value: string }[];
  public file: any;
  public uploader: FileUploader = new FileUploader({
    url: '/api/user/me/photo',
    autoUpload: true
  });
  sub: Subscription;

  constructor(
    private translate: TranslateService,
    private http: HttpClient,
    private router: Router,
    private auth: AuthService,
    private pageEnv: PageEnvService,
    private language: LanguageService,
    private timezone: TimezoneService
  ) {
    this.uploader.onSuccessItem = () => {
      this.http
        .get<APIUser>('/api/user/me', {
          params: {
            fields: 'img'
          }
        })
        .subscribe(
          subresponse => {
            this.photo = subresponse.img;
          },
          subresponse => Notify.response(subresponse)
        );
    };

    this.uploader.onErrorItem = (
      item: FileItem,
      response: string,
      status: number,
      headers: ParsedResponseHeaders
    ) => {
      this.photoInvalidParams = JSON.parse(response).invalid_params;
    };

    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/129/name',
          pageId: 129
        }),
      0
    );
  }

  ngOnInit(): void {
    this.sub = this.auth
      .getUser()
      .pipe(
        switchMap(user => {
          if (!user) {
            this.router.navigate(['/signin']);
            return empty();
          }

          this.user = user;

          return of(user);
        }),
        switchMapTo(
          combineLatest(
            this.http.get<APIUser>('/api/user/me', {
              params: {
                fields: 'name,timezone,language,votes_per_day,votes_left,img'
              }
            }),
            this.timezone.getTimezones(),
            this.language.getLanguages(),
            (user, timezones, languages) => ({ user, timezones, languages })
          )
        )
      )
      .subscribe(
        data => {
          this.profile.name = data.user.name;
          this.settings.timezone = data.user.timezone;
          this.settings.language = data.user.language;
          this.votesPerDay = data.user.votes_per_day;
          this.votesLeft = data.user.votes_left;
          this.photo = data.user.img;

          this.timezones = data.timezones;

          this.languages = [];
          for (const key in data.languages.items) {
            if (data.languages.items.hasOwnProperty(key)) {
              this.languages.push({
                name: data.languages.items[key],
                value: key
              });
            }
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public sendProfile() {
    this.profileInvalidParams = {};

    this.http.put<void>('/api/user/me', this.profile).subscribe(
      () => {
        this.user.name = this.profile.name;

        this.translate
          .get('account/profile/saved')
          .subscribe((translation: string) => {
            Notify.custom(
              {
                icon: 'fa fa-check',
                message: translation
              },
              {
                type: 'success'
              }
            );
          });
      },
      response => {
        if (response.status === 400) {
          this.profileInvalidParams = response.error.invalid_params;
        } else {
          Notify.response(response);
        }
      }
    );
  }

  public sendSettings() {
    this.settingsInvalidParams = {};

    this.http.put<void>('/api/user/me', this.settings).subscribe(
      () => {
        this.translate
          .get('account/profile/saved')
          .subscribe((translation: string) => {
            Notify.custom(
              {
                icon: 'fa fa-check',
                message: translation
              },
              {
                type: 'success'
              }
            );
          });
      },
      response => {
        if (response.status === 400) {
          this.settingsInvalidParams = response.error.invalid_params;
        } else {
          Notify.response(response);
        }
      }
    );
  }

  public showFileSelectDialog() {
    this.photoInvalidParams = {};
    this.fileInput.nativeElement.click();
  }

  public resetPhoto() {
    this.http.delete('/api/user/me/photo').subscribe(
      () => {
        this.user.avatar = null;
        this.photo = null;
      },
      response => Notify.response(response)
    );
  }
}
