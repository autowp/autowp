import {AsyncPipe, DecimalPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {Router, RouterLink} from '@angular/router';
import {APIUser} from '@grpc/spec.pb';
import {StatisticsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import escapeStringRegexp from 'escape-string-regexp';
import {marked} from 'marked';
import {BytesPipe} from 'ngx-pipes';
import {map, switchMap} from 'rxjs/operators';

import * as versionJson from '../../version.json';

function replaceAll(str: string, find: string, replace: string): string {
  return str.replace(new RegExp(escapeStringRegexp(find), 'g'), replace);
}

function replacePairs(str: string, pairs: Record<string, string>): string {
  for (const key in pairs) {
    str = replaceAll(str, String(key), pairs[key]);
  }
  return str;
}

const aboutText = $localize`### People

Своим существованием наш проект обязан людям, приходящим сюда и вкладывающим своё время и знания.

Кто-то добавляет материалы, а кто-то помогает найти ошибки в уже имеющихся. Кто-то специализируется на конкретной марке, а кто-то успевает за всем. Кто-то без лишнего внимания со стороны наполняет сайт шаг за шагом, а кто-то собирает овации редкими, но жгучими фото.

Нас много и мы разные, и это прекрасно. Вот лишь некоторые из нас:

%users%

#### "Цветовая дифференциация штанов"

Так завелось, что мы выделяем некоторых наших людей особым цветом - зеленым. Не просто так - это особая метка. Знайте, если вы видите кого-то из "зеленых", вы всегда можете схватить его и спросить о чем угодно вокруг нашего проекта, потому что "зеленые" - это самые отзывчивые и заинтересованные в жизни проекта люди.

Некоторая часть "зеленых" наделена модераторскими функциями.

### Feedback

Если у вас есть какие-то замечания, предложения или иные мысли, вы можете озвучить их на [форуме](/forums/), задать лично через систему обмена сообщениями или написать в "[обратную связь](/feedback)" администрации сайта.

Если у вас есть вопросы о размещении рекламы, обмена ссылками или продвижении вашего продукта иными способами, все они имеют единственный ответ: мы не размещаем рекламу.

### Numbers

Так сложилось, что мы любим тешить своё тщеславие большими цифрами, а также всем их показывать. Вашему вниманию некоторые из них:

* на сайте более %total-pictures% изображений, %total-vehicles% автомобилей, что составляет порядка %total-size% данных
* зарегистрировано около %total-users% пользователей, оставивших более %total-comments% сообщений

### Development

Разработка и поддержка проекта ведется в основном силами %developer% ([contributors](https://github.com/autowp/autowp/graphs/contributors))

French site translation: %fr-translator%

Chinese site translation: %zh-translator%

Belarusian site translation: %be-translator%

Brazilian portuguese site translation: %pt-br-translator%

Сайт работает на [Zend Framework](http://framework.zend.com/), [jQuery](http://jquery.com/), [Twitter bootstrap](http://getbootstrap.com/), а также многих других "умных словах".

Исходный код сайта является открытым, чтобы каждый желающий имел возможность влиять на суть и качество проекта.

%github%

[![Build Status](https://travis-ci.org/autowp/autowp.svg?branch=master)](https://travis-ci.org/autowp/autowp)
[![Code Climate](https://codeclimate.com/github/autowp/autowp/badges/gpa.svg)](https://codeclimate.com/github/autowp/autowp)
[![Coverage Status](https://coveralls.io/repos/github/autowp/autowp/badge.svg?branch=master)](https://coveralls.io/github/autowp/autowp?branch=master)

### Поддержать проект

You can support our project by [finances](/donate) or [moral](/feedback).
Take part in [the translation of the site](https://github.com/autowp/autowp-frontend/tree/master/src/locale) into other languages.`;

@Component({
  selector: 'app-about',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './about.component.html',
  providers: [BytesPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AboutComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #router = inject(Router);
  readonly #decimalPipe = inject(DecimalPipe);
  readonly #bytesPipe = inject(BytesPipe);
  readonly #pageEnv = inject(PageEnvService);
  readonly #statGrpc = inject(StatisticsClient);

  protected readonly version = versionJson;

  protected readonly html$ = this.#statGrpc
    .getAboutData(new Empty())
    .pipe(
      switchMap((about) => {
        const ids: string[] = about.contributors;
        ids.push(about.developer);
        ids.push(about.frTranslator);
        ids.push(about.zhTranslator);
        ids.push(about.beTranslator);
        ids.push(about.ptBrTranslator);

        return this.#userService.getUserMap$(ids).pipe(
          map((users) => ({
            about,
            aboutText,
            users,
          })),
        );
      }),
    )
    .pipe(
      map((data) => {
        const contributorsHtml: string[] = [];
        for (const id of data.about.contributors) {
          contributorsHtml.push(this.userHtml(data.users.get(id)));
        }

        const html = marked.parse(data.aboutText, {async: false});
        return replacePairs(html, {
          '%be-translator%': this.userHtml(data.users.get(data.about.beTranslator)),
          '%developer%': this.userHtml(data.users.get(data.about.developer)),
          '%fr-translator%': this.userHtml(data.users.get(data.about.frTranslator)),
          '%github%':
            '<i class="bi bi-github" aria-hidden="true"></i> ' +
            '<a href="https://github.com/autowp/autowp">https://github.com/autowp/autowp</a>',
          '%pt-br-translator%': this.userHtml(data.users.get(data.about.ptBrTranslator)),
          '%total-comments%': data.about.totalComments.toString(),
          '%total-pictures%': this.#decimalPipe.transform(data.about.totalPictures) ?? '',
          '%total-size%': this.#bytesPipe.transform(data.about.picturesSize * 1024 * 1024, 1).toString(),
          '%total-users%': data.about.totalUsers.toString(),
          '%total-vehicles%': data.about.totalItems.toString(),
          '%users%': contributorsHtml.join(' '),
          '%zh-translator%': this.userHtml(data.users.get(data.about.zhTranslator)),
        });
      }),
    );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 136}), 0);
  }

  private userHtml(user: APIUser | null | undefined): string {
    if (!user) {
      return '';
    }
    const span = document.createElement('span');
    const classes = ['user'];
    if (user.deleted) {
      classes.push('muted');
    }
    if (user.longAway) {
      classes.push('long-away');
    }
    if (user.green) {
      classes.push('green-man');
    }
    span.setAttribute('class', classes.join(' '));
    const a = document.createElement('a');
    a.setAttribute(
      'href',
      this.#router.createUrlTree(['/users', user.identity ? user.identity : 'user' + user.id]).toString(),
    );
    a.innerText = user.name;

    return '<i class="bi bi-person-fill" aria-hidden="true"></i> ' + span.appendChild(a).outerHTML;
  }
}
