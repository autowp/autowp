import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, REQUEST} from '@angular/core';
import {DomSanitizer, SafeResourceUrl} from '@angular/platform-browser';
import {RouterLink} from '@angular/router';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-donate',
  imports: [RouterLink, RemarkModule],
  templateUrl: './donate.component.html',
  styleUrl: './donate.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #domSanitizer = inject(DomSanitizer);
  readonly #document = inject(DOCUMENT);
  // Only set server-side (see HTTP_TRANSFER_CACHE_ORIGIN_MAP in app.config.server.ts for the same
  // pattern) - document.defaultView.location.host isn't available yet during SSR, and using it
  // there would bake "https://undefined/..." into the server-rendered iframe/successURL.
  readonly #request = inject(REQUEST, {optional: true});

  protected readonly frameUrl: SafeResourceUrl;
  protected readonly language: string = this.#languageService.language;

  constructor() {
    const host = this.#request?.headers.get('host') ?? this.#document.defaultView?.location.host;

    const map: Record<string, string> = {
      account: '41001161017513',
      'button-text': '14',
      comment: 'on',
      'default-sum': '100',
      hint: $localize`Your wish`,
      'mobile-payment-type-choice': 'on',
      'payment-type-choice': 'on',
      'project-name': $localize`WheelsAge.org`,
      'project-site': 'https://' + host + '/',
      quickpay: 'shop',
      successURL: 'https://' + host + '/donate/success',
      'target-visibility': 'on',
      targets: $localize`For website work`,
      'targets-hint': $localize`Your wish`,
      writer: 'seller',
    };

    const url = new URL('https://yoomoney.ru/quickpay/shop-widget');
    for (const key in map) {
      url.searchParams.append(key, map[key]);
    }

    // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
    this.frameUrl = this.#domSanitizer.bypassSecurityTrustResourceUrl(url.toString());
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 196});
  }
}
