import { Component, Injectable } from '@angular/core';
import * as showdown from 'showdown';
import * as escapeRegExp from 'lodash.escaperegexp';
import { UserService, APIUser } from '../services/user';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';
import { DecimalPipe } from '@angular/common';
import { BytesPipe } from 'ngx-pipes';
import { PageEnvService } from '../services/page-env.service';

export class APIAbout {
  developer: number;
  fr_translator: number;
  zh_translator: number;
  be_translator: number;
  pt_br_translator: number;
  contributors: number[];
  total_pictures: number;
  pictures_size: number;
  total_users: number;
  total_cars: number;
  total_comments: number;
}

function replaceAll(str: string, find: string, replace: string): string {
  return str.replace(new RegExp(escapeRegExp(find), 'g'), replace);
}

function replacePairs(str: string, pairs: { [key: string]: string }): string {
  for (const key in pairs) {
    if (pairs.hasOwnProperty(key)) {
      str = replaceAll(str, String(key), pairs[key]);
    }
  }
  return str;
}

@Component({
  selector: 'app-about',
  templateUrl: './about.component.html'
})
@Injectable()
export class AboutComponent {
  public html = '';

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private userService: UserService,
    private router: Router,
    private decimalPipe: DecimalPipe,
    private bytesPipe: BytesPipe,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/136/name',
          pageId: 136
        }),
      0
    );

    this.http.get<APIAbout>('/api/about').subscribe(
      response => {
        this.translate.get('about/text').subscribe(
          translation => {
            const ids: number[] = response.contributors;
            ids.push(response.developer);
            ids.push(response.fr_translator);
            ids.push(response.zh_translator);
            ids.push(response.be_translator);
            ids.push(response.pt_br_translator);

            this.userService.getUserMap(ids).then(
              (users: Map<number, APIUser>) => {
                const contributorsHtml: string[] = [];
                for (const id of response.contributors) {
                  contributorsHtml.push(this.userHtml(users.get(id)));
                }

                const markdownConverter = new showdown.Converter({});
                this.html = replacePairs(
                  markdownConverter.makeHtml(translation),
                  {
                    '%users%': contributorsHtml.join(' '),
                    '%total-pictures%': this.decimalPipe.transform(
                      response.total_pictures
                    ),
                    '%total-vehicles%': response.total_cars.toString(),
                    '%total-size%': bytesPipe
                      .transform(response.pictures_size, 1)
                      .toString(),
                    '%total-users%': response.total_users.toString(),
                    '%total-comments%': response.total_comments.toString(),
                    '%github%':
                      '<i class="fa fa-github"></i> <a href="https://github.com/autowp/autowp">https://github.com/autowp/autowp</a>',
                    '%developer%': this.userHtml(users.get(response.developer)),
                    '%fr-translator%': this.userHtml(
                      users.get(response.fr_translator)
                    ),
                    '%zh-translator%': this.userHtml(
                      users.get(response.zh_translator)
                    ),
                    '%be-translator%': this.userHtml(
                      users.get(response.be_translator)
                    ),
                    '%pt-br-translator%': this.userHtml(
                      users.get(response.pt_br_translator)
                    )
                  }
                );
              },
              responses => {
                console.log('reject', responses);
              }
            );
          },
          responses => {
            console.log('Failed to translate');
          }
        );
      },
      response => {
        Notify.response(response);
      }
    );
  }

  private userHtml(user: APIUser): string {
    const span = document.createElement('span');
    const classes = ['user'];
    if (user.deleted) {
      classes.push('muted');
    }
    if (user.long_away) {
      classes.push('long-away');
    }
    if (user.green) {
      classes.push('green-man');
    }
    span.setAttribute('class', classes.join(' '));
    const a = document.createElement('a');
    a.setAttribute(
      'href',
      this.router
        .createUrlTree([
          '/users/user',
          user.identity ? user.identity : 'user' + user.id
        ])
        .toString()
    );
    a.innerText = user.name;

    return '<i class="fa fa-user"></i> ' + span.appendChild(a).outerHTML;
  }
}
