import {inject, LOCALE_ID, Service} from '@angular/core';
import {environment} from '@environment/environment';

export interface Language {
  code: string;
  flag: string;
  hostname: string;
  locale: string;
  name: string;
}

@Service()
export class LanguageService {
  readonly #localeId = inject(LOCALE_ID);

  public readonly language: string = 'en';

  constructor() {
    const localeId = this.#localeId;

    for (const lang of environment.languages as Language[]) {
      if (lang.locale === localeId) {
        this.language = lang.code;
        break;
      }
    }
  }
}
