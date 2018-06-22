import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { shareReplay, map } from 'rxjs/operators';

export interface APILanguageGetResponse {
  items: {
    [key: string]: string;
  };
}

@Injectable()
export class LanguageService {
  private language = document.documentElement.getAttribute('lang');
  private languages$: Observable<APILanguageGetResponse>;

  constructor(private http: HttpClient) {
    this.languages$ = this.http
      .get<APILanguageGetResponse>('/api/language')
      .pipe(shareReplay(1));
  }

  public getLanguage() {
    return this.language;
  }

  public getLanguages(): Observable<APILanguageGetResponse> {
    return this.languages$;
  }
}
