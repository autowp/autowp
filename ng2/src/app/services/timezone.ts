import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { shareReplay, map } from 'rxjs/operators';

export interface APITimezoneGetResponse {
  items: string[];
}

@Injectable()
export class TimezoneService {
  private timezones$: Observable<string[]>;

  constructor(private http: HttpClient) {
    this.timezones$ = this.http.get<APITimezoneGetResponse>('/api/timezone')
      .pipe(
        map(response => response.items),
        shareReplay(1)
      );
  }

  public getTimezones(): Observable<string[]> {
    return this.timezones$;
  }
}
