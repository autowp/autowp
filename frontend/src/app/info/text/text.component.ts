import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {APIGetTextRequest, APIUser} from '@grpc/spec.pb';
import {TextClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {DiffEditorComponent, DiffEditorModel} from 'ngx-monaco-editor-v2';
import {combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface InfoText {
  current: null | {
    revision: string;
    text: string;
    user$: Observable<APIUser | null>;
  };
  currentModel: DiffEditorModel;
  next: null | {
    revision: string;
  };
  prev: null | {
    revision: string;
    text: string;
    user$: Observable<APIUser | null>;
  };
  prevModel: DiffEditorModel;
}

@Component({
  selector: 'app-info-text',
  imports: [RouterLink, UserComponent, AsyncPipe, DiffEditorComponent],
  templateUrl: './text.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoTextComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #textClient = inject(TextClient);

  protected readonly options = {
    originalEditable: false,
    readOnly: true,
    theme: 'vs-dark',
  };

  readonly #id$ = this.#route.paramMap.pipe(
    map((params) => params.get('id')),
    distinctUntilChanged(),
    debounceTime(10),
  );

  readonly #revision$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('revision')),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly data$: Observable<InfoText> = combineLatest([this.#id$, this.#revision$]).pipe(
    switchMap(([id, revision]) =>
      this.#textClient.getText(
        new APIGetTextRequest({
          id: id ? id : undefined,
          revision: revision ? revision : undefined,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => ({
      current: response.current
        ? {
            revision: response.current.revision,
            text: response.current.text,
            user$: this.#userService.getUser$(response.current.userId),
          }
        : null,
      currentModel: {
        code: response.current?.text ?? '',
        language: 'text/markdown',
      },
      next:
        response.next && response.next.revision !== '0'
          ? {
              revision: response.next.revision,
            }
          : null,
      prev:
        response.prev && response.prev.revision !== '0'
          ? {
              revision: response.prev.revision,
              text: response.prev.text,
              user$: this.#userService.getUser$(response.prev.userId),
            }
          : null,
      prevModel: {
        code: response.prev?.text || '',
        language: 'text/markdown',
      },
    })),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 197}), 0);
  }
}
