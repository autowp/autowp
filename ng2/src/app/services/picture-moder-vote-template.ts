import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, combineLatest } from 'rxjs';
import { map, shareReplay, tap, switchMapTo } from 'rxjs/operators';
import { AuthService } from './auth.service';

export interface APIPictureModerVoteTemplatePostData {
  vote: number;
  name: string;
}

export class APIPictureModerVoteTemplate {
  id: number;
  name: string;
  vote: number;
}

export class APIPictureModerVoteTemplateGetResponse {
  items: APIPictureModerVoteTemplate[];
}

@Injectable()
export class PictureModerVoteTemplateService {
  private change$ = new BehaviorSubject<null>(null);
  constructor(private http: HttpClient, private auth: AuthService) {}

  public getTemplates(): Observable<APIPictureModerVoteTemplate[]> {
    return combineLatest(this.change$, this.auth.getUser()).pipe(
      switchMapTo(
        this.http.get<APIPictureModerVoteTemplateGetResponse>(
          '/api/picture-moder-vote-template'
        )
      ),
      map(response => response.items),
      shareReplay(1)
    );
  }

  public deleteTemplate(id: number): Observable<void> {
    return this.http
      .delete<void>('/api/picture-moder-vote-template/' + id)
      .pipe(tap(() => this.change$.next(null)));
  }

  public createTemplate(
    template: APIPictureModerVoteTemplatePostData
  ): Observable<void> {
    return this.http
      .post<void>('/api/picture-moder-vote-template', template)
      .pipe(tap(() => this.change$.next(null)));
  }
}
