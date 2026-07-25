import {AsyncPipe, DecimalPipe, DOCUMENT} from '@angular/common';
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

Our project owes its existence to the people who come here and contribute their time and knowledge.

Some add materials, others help find errors in what's already there. Some specialize in a particular make, others keep up with everything. Some quietly fill the site step by step without seeking attention, while others gather applause with rare but striking photos.

There are many of us, and we're all different, and that's wonderful. Here are just a few of us:

%users%

#### "Color coding of pants"

It's become our custom to mark some of our people with a special color - green. It's not just for show - it's a special badge. Know that if you see one of the "greens", you can always grab them and ask about anything related to our project, because the "greens" are the most responsive people, most invested in the life of the project.

Some of the "greens" also hold moderator privileges.

### Feedback

If you have any remarks, suggestions or other thoughts, you can voice them on the [forum](/forums/), ask personally through the messaging system, or write to the site administration via "[feedback](/feedback)".

If you have questions about advertising, link exchange or promoting your product in other ways, they all have the same answer: we do not place advertising.

### Numbers

As it happens, we like to indulge our vanity with big numbers, and to show them off to everyone. Some of them, for your attention:

* the site has more than %total-pictures% images, %total-vehicles% cars, amounting to roughly %total-size% of data
* about %total-users% users are registered, who have left more than %total-comments% comments

### Development

The project is developed and maintained mainly by %developer% ([contributors](https://github.com/autowp/autowp/graphs/contributors))

French site translation: %fr-translator%

Chinese site translation: %zh-translator%

Belarusian site translation: %be-translator%

Brazilian portuguese site translation: %pt-br-translator%

The site runs on [Zend Framework](http://framework.zend.com/), [jQuery](http://jquery.com/), [Twitter bootstrap](http://getbootstrap.com/), as well as many other "clever words".

The site's source code is open, so that anyone willing has the opportunity to influence the nature and quality of the project.

%github%

[![Build Status](https://travis-ci.org/autowp/autowp.svg?branch=master)](https://travis-ci.org/autowp/autowp)
[![Code Climate](https://codeclimate.com/github/autowp/autowp/badges/gpa.svg)](https://codeclimate.com/github/autowp/autowp)
[![Coverage Status](https://coveralls.io/repos/github/autowp/autowp/badge.svg?branch=master)](https://coveralls.io/github/autowp/autowp?branch=master)

### Support the project

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
  readonly #statisticsClient = inject(StatisticsClient);
  readonly #document = inject(DOCUMENT);

  protected readonly version = versionJson;

  protected readonly html$ = this.#statisticsClient
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
    this.#pageEnv.set({pageId: 136});
  }

  private userHtml(user: APIUser | null | undefined): string {
    if (!user) {
      return '';
    }
    const span = this.#document.createElement('span');
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
    const a = this.#document.createElement('a');
    a.setAttribute(
      'href',
      this.#router.createUrlTree(['/users', user.identity ? user.identity : 'user' + user.id]).toString(),
    );
    a.innerText = user.name;

    return '<i class="bi bi-person-fill" aria-hidden="true"></i> ' + span.appendChild(a).outerHTML;
  }
}
