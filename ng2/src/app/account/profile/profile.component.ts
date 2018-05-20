import { Component, Injectable, ViewChild, ElementRef } from '@angular/core';
import * as ngFileUpload from 'ng-file-upload';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { APIUser } from '../../services/user';
import { FileUploader, FileItem, ParsedResponseHeaders } from 'ng2-file-upload';
import { PageEnvService } from '../../services/page-env.service';

export interface APITimezoneGetResponse {
  items: string[];
}

export interface APILanguageGetResponse {
  items: {
    [key: string]: string;
  };
}

@Component({
  selector: 'app-account-profile',
  templateUrl: './profile.component.html'
})
@Injectable()
export class AccountProfileComponent {
  @ViewChild('fileInput') fileInput: ElementRef;

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

  constructor(
    private translate: TranslateService,
    private http: HttpClient,
    private router: Router,
    private auth: AuthService,
    private pageEnv: PageEnvService
  ) {
    if (!this.auth.user) {
      // TODO: use guard
      this.router.navigate(['/signin']);
      return;
    }

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

    this.http
      .get<APIUser>('/api/user/me', {
        params: {
          fields: 'name,timezone,language,votes_per_day,votes_left,img'
        }
      })
      .subscribe(
        response => {
          this.profile.name = response.name;
          this.settings.timezone = response.timezone;
          this.settings.language = response.language;
          this.votesPerDay = response.votes_per_day;
          this.votesLeft = response.votes_left;
          this.photo = response.img;
        },
        response => {
          Notify.response(response);
        }
      );

    this.http.get<APITimezoneGetResponse>('/api/timezone').subscribe(
      response => {
        this.timezones = response.items;
      },
      response => {
        Notify.response(response);
      }
    );

    this.http.get<APILanguageGetResponse>('/api/language').subscribe(
      response => {
        this.languages = [];
        for (const key in response.items) {
          if (response.items.hasOwnProperty(key)) {
            this.languages.push({
              name: response.items[key],
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

  public sendProfile() {
    this.profileInvalidParams = {};

    this.http.put<void>('/api/user/me', this.profile).subscribe(
      () => {
        this.auth.user.name = this.profile.name;

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

  /*public uploadFiles(file: any, errFiles: any) {
    this.file = file;
    if (file) {
      this.photoInvalidParams = {};
      file.progress = 0;

      file.upload = this.Upload.upload({
        url: '/api/user/me/photo',
        data: { file: file }
      });

      file.upload.then(
        (response: any) => {
          file.progress = 0;
          file.result = response;


        },
        response => {
          if (response.status > 0) {
            if (response.status === 400) {
              this.photoInvalidParams = response.error.invalid_params;
            } else {
              Notify.response(response);
            }
          }
          file.progress = 0;
        },
        (evt: any) => {
          file.progress = Math.min(
            100,
            Math.round(100.0 * evt.loaded / evt.total)
          );
        }
      );
    }

    // account/profile/photo/saved
  }*/

  public showFileSelectDialog() {
    this.photoInvalidParams = {};
    this.fileInput.nativeElement.click();
  }

  public resetPhoto() {
    this.http.delete('/api/user/me/photo').subscribe(
      () => {
        this.auth.user.avatar = null;
        this.photo = null;
      },
      response => Notify.response(response)
    );
  }
}
