import { Injectable } from '@angular/core';

@Injectable()
export class LanguageService {
  private language = document.documentElement.getAttribute('lang');

  constructor() {}

  public getLanguage() {
    return this.language;
  }
}
