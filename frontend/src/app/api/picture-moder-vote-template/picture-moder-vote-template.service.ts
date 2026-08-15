import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {DeleteModerVoteTemplateRequest, ModerVoteTemplate} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth.service';
import {BehaviorSubject, combineLatest, map, shareReplay, switchMap, tap} from 'rxjs';

export interface APIPictureModerVoteTemplatePostData {
  name: string;
  vote: number;
}

@Service()
export class APIPictureModerVoteTemplateService {
  readonly #auth = inject(AuthService);
  readonly #pictures = inject(PicturesClient);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  public getTemplates$(): Observable<ModerVoteTemplate[]> {
    return combineLatest([this.#change$, this.#auth.authenticated$]).pipe(
      switchMap(() => this.#pictures.getModerVoteTemplates(new Empty({}))),
      map((response) => response.items ?? []),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }

  public deleteTemplate$(id: string): Observable<Empty> {
    return this.#pictures.deleteModerVoteTemplate(new DeleteModerVoteTemplateRequest({id})).pipe(
      tap(() => {
        this.#change$.next();
      }),
    );
  }

  public createTemplate$(template: APIPictureModerVoteTemplatePostData): Observable<ModerVoteTemplate> {
    return this.#pictures
      .createModerVoteTemplate(
        new ModerVoteTemplate({
          message: template.name,
          vote: template.vote,
        }),
      )
      .pipe(
        tap(() => {
          this.#change$.next();
        }),
      );
  }
}
