import type {ResourceRef} from '@angular/core';
import type {Observable} from 'rxjs';

import {inject, Injector, Service, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {DeleteModerVoteTemplateRequest, ModerVoteTemplate} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth-service';
import {map, tap} from 'rxjs';
export interface APIPictureModerVoteTemplatePostData {
  name: string;
  vote: number;
}

@Service()
export class APIPictureModerVoteTemplateService {
  readonly #auth = inject(AuthService);
  readonly #pictures = inject(PicturesClient);

  readonly #injector = inject(Injector);
  readonly #authenticated = this.#auth.authenticated;
  readonly #revision = signal(0);
  #templatesResource: ResourceRef<ModerVoteTemplate[] | undefined> | undefined;

  public getTemplatesResource(): ResourceRef<ModerVoteTemplate[] | undefined> {
    // Creating a template without reading the list must not trigger a background read.
    this.#templatesResource ??= rxResource({
      injector: this.#injector,
      id: `picture-moder-vote-templates${this.#authenticated() ? '-auth' : ''}`,
      params: () => {
        const authenticated = this.#authenticated();
        return authenticated === undefined ? undefined : `${Number(authenticated)}:${this.#revision()}`;
      },
      stream: () => this.#pictures.getModerVoteTemplates(new Empty()).pipe(map((response) => response.items ?? [])),
    });
    return this.#templatesResource;
  }

  public deleteTemplate$(id: string): Observable<Empty> {
    return this.#pictures.deleteModerVoteTemplate(new DeleteModerVoteTemplateRequest({id})).pipe(
      tap(() => {
        // Changing params also cancels a stale read already in flight during the mutation.
        this.#revision.update((revision) => revision + 1);
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
          // Changing params also cancels a stale read already in flight during the mutation.
          this.#revision.update((revision) => revision + 1);
        }),
      );
  }
}
