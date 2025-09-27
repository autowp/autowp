import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {DomSanitizer, SafeResourceUrl} from '@angular/platform-browser';
import {RouterLink} from '@angular/router';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {MarkdownComponent} from '@utils/markdown/markdown.component';

@Component({
  selector: 'app-donate',
  imports: [RouterLink, MarkdownComponent],
  templateUrl: './donate.component.html',
  styleUrl: './donate.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #languageService = inject(LanguageService);
  readonly #domSanitizer = inject(DomSanitizer);

  protected readonly frameUrl: SafeResourceUrl;
  protected readonly language: string = this.#languageService.language;

  constructor() {
    const map: Record<string, string> = {
      account: '41001161017513',
      'button-text': '14',
      comment: 'on',
      'default-sum': '100',
      hint: $localize`Your wish`,
      'mobile-payment-type-choice': 'on',
      'payment-type-choice': 'on',
      'project-name': $localize`WheelsAge.org`,
      'project-site': 'https://' + window.location.host + '/',
      quickpay: 'shop',
      successURL: 'https://' + window.location.host + '/donate/success',
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
    setTimeout(() => this.#pageEnv.set({pageId: 196}), 0);
  }
}
