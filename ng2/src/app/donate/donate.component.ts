import { Component, Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { PageEnvService } from '../services/page-env.service';
import { LanguageService } from '../services/language';

@Component({
  selector: 'app-donate',
  templateUrl: './donate.component.html'
})
@Injectable()
export class DonateComponent {
  public frameUrl: string;
  public language: string;

  constructor(
    private translate: TranslateService,
    private pageEnv: PageEnvService,
    private languageService: LanguageService
  ) {
    this.language = this.languageService.getLanguage();

    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/196/name',
          pageId: 196
        }),
      0
    );

    this.translate
      .get(['donate/target', 'donate/project', 'donate/comment-hint'])
      .subscribe((translations: string[]) => {
        const map = {
          account: '41001161017513',
          quickpay: 'donate',
          'payment-type-choice': 'on',
          'mobile-payment-type-choice': 'on',
          'default-sum': '100',
          targets: translations[0],
          'target-visibility': 'on',
          'project-name': translations[1],
          'project-site': 'https://' + window.location.host + '/',
          'button-text': '01',
          comment: 'on',
          hint: translations[2],
          successURL: 'https://' + window.location.host + '/ng/donate/success'
        };

        const url = new URL('https://money.yandex.ru/embed/donate.xml');
        for (const key in map) {
          if (map.hasOwnProperty(key)) {
            url.searchParams.append(key, map[key]);
          }
        }

        this.frameUrl = url.toString();
      });
  }
}
