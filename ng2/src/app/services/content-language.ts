import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, shareReplay } from 'rxjs/operators';

export interface APIContentLanguageGetResponse {
  items: string[];
}

@Injectable()
export class ContentLanguageService {
  private languages$: Observable<string[]>;

  constructor(private http: HttpClient) {
    this.languages$ = this.http.get<APIContentLanguageGetResponse>('/api/content-language').pipe(
      map(response => response.items),
      shareReplay(1)
    );
  }

  public getList(): Observable<string[]> {
    return this.languages$;
  }
}
