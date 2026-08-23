import type {OnInit} from '@angular/core';

import {DecimalPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {StatisticsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {errorMessage} from 'app/grpc';
import {NgMathPipesModule} from 'ngx-pipes';
import {RemarkComponent, RemarkNodeComponent, RemarkTemplateDirective} from 'ngx-remark';

import * as versionJson from '../../version.json';
import {UserComponent} from '../user/user/user.component';

// Placeholders below are markdown links with a `placeholder:` URL, e.g. [users](placeholder:users) -
// real markdown remark-parse already produces a distinct 'link' AST node for, no custom plugin
// needed. The *remarkTemplate="'link'" block in the template (about.component.html) checks for that
// prefix and renders the matching dynamic content, falling through to a plain <a> (same as
// <remark>'s own default link template) for every other link below.
const aboutText = $localize`### People

Our project owes its existence to the people who come here and contribute their time and knowledge.

Some add materials, others help find errors in what's already there. Some specialize in a particular make, others keep up with everything. Some quietly fill the site step by step without seeking attention, while others gather applause with rare but striking photos.

There are many of us, and we're all different, and that's wonderful. Here are just a few of us:

[users](placeholder:users)

#### "Color coding of pants"

It's become our custom to mark some of our people with a special color - green. It's not just for show - it's a special badge. Know that if you see one of the "greens", you can always grab them and ask about anything related to our project, because the "greens" are the most responsive people, most invested in the life of the project.

Some of the "greens" also hold moderator privileges.

### Feedback

If you have any remarks, suggestions or other thoughts, you can voice them on the [forum](/forums/), ask personally through the messaging system, or write to the site administration via "[feedback](/feedback)".

If you have questions about advertising, link exchange or promoting your product in other ways, they all have the same answer: we do not place advertising.

### Numbers

As it happens, we like to indulge our vanity with big numbers, and to show them off to everyone. Some of them, for your attention:

* the site has more than [total-pictures](placeholder:total-pictures) images, [total-vehicles](placeholder:total-vehicles) cars, amounting to roughly [total-size](placeholder:total-size) of data
* about [total-users](placeholder:total-users) users are registered, who have left more than [total-comments](placeholder:total-comments) comments

### Development

The project is developed and maintained mainly by [developer](placeholder:developer) ([contributors](https://github.com/autowp/autowp/graphs/contributors))

French site translation: [fr-translator](placeholder:fr-translator)

Chinese site translation: [zh-translator](placeholder:zh-translator)

Belarusian site translation: [be-translator](placeholder:be-translator)

Brazilian portuguese site translation: [pt-br-translator](placeholder:pt-br-translator)

The site runs on [Zend Framework](http://framework.zend.com/), [jQuery](http://jquery.com/), [Twitter bootstrap](http://getbootstrap.com/), as well as many other "clever words".

The site's source code is open, so that anyone willing has the opportunity to influence the nature and quality of the project.

[github](placeholder:github)

[![Build Status](https://travis-ci.org/autowp/autowp.svg?branch=master)](https://travis-ci.org/autowp/autowp)
[![Code Climate](https://codeclimate.com/github/autowp/autowp/badges/gpa.svg)](https://codeclimate.com/github/autowp/autowp)
[![Coverage Status](https://coveralls.io/repos/github/autowp/autowp/badge.svg?branch=master)](https://coveralls.io/github/autowp/autowp?branch=master)

### Support the project

You can support our project by [finances](/donate) or [moral](/feedback).
Take part in [the translation of the site](https://github.com/autowp/autowp-frontend/tree/master/src/locale) into other languages.`;

@Component({
  selector: 'app-about',
  imports: [
    DecimalPipe,
    NgMathPipesModule,
    RemarkComponent,
    RemarkNodeComponent,
    RemarkTemplateDirective,
    RouterLink,
    UserComponent,
  ],
  templateUrl: './about.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AboutComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #statisticsClient = inject(StatisticsClient);

  protected readonly version = versionJson;
  protected readonly aboutText = aboutText;

  protected readonly aboutResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'about-data',
    stream: () => this.#statisticsClient.getAboutData(new Empty()),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so usersResource's params() below doesn't blow up on a non-NOT_FOUND
  // aboutResource error (surfaced generically by the template instead).
  protected readonly aboutData = computed(() =>
    this.aboutResource.hasValue() ? this.aboutResource.value() : undefined,
  );

  protected readonly usersResource = rxResource({
    id: 'about-users',
    // Angular skips stream() entirely while params() returns undefined, so about is always
    // defined once stream() actually runs.
    params: () => this.aboutData(),
    stream: ({params: about}) => {
      // Fetched into a fresh array rather than pushing onto about.contributors directly - that
      // would mutate the resource's own value and leak the developer/translators into the
      // contributors list below, which already shows them separately.
      const ids = [
        ...about.contributors,
        about.developer,
        about.frTranslator,
        about.zhTranslator,
        about.beTranslator,
        about.ptBrTranslator,
      ];

      return this.#userService.getUserMap$(ids);
    },
  });

  // Gates the template on both resources being ready before rendering any of the markdown below,
  // since it reads both aboutData()/usersResource.value() while rendering placeholder nodes.
  protected readonly ready = computed(() => {
    const about = this.aboutResource.value();
    const users = this.usersResource.value();
    return about && users ? {about, users} : undefined;
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 136});
  }

  protected readonly errorMessage = errorMessage;
}
