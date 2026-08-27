import type {OnInit} from '@angular/core';
import type {User} from '@grpc/spec.pb';
import type {DiffEditorModel} from 'ngx-monaco-editor-v2';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {GetTextRequest} from '@grpc/spec.pb';
import {TextClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {DiffEditorComponent} from 'ngx-monaco-editor-v2';
import {catchError, combineLatest, distinctUntilChanged, EMPTY, map, of, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface InfoText {
  current: null | {
    revision: string;
    text: string;
    user$: Observable<null | User>;
  };
  currentModel: DiffEditorModel;
  next: null | {
    revision: string;
  };
  prev: null | {
    revision: string;
    text: string;
    user$: Observable<null | User>;
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
  readonly #router = inject(Router);
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
    switchMap((id) => {
      if (!id) {
        void this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(id);
    }),
  );

  readonly #revision$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('revision') ?? ''),
    distinctUntilChanged(),
  );

  protected readonly data$: Observable<InfoText> = combineLatest([this.#id$, this.#revision$]).pipe(
    switchMap(([id, revision]) => this.#textClient.getText(new GetTextRequest({id, revision}))),
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
        code: response.prev?.text ?? '',
        language: 'text/markdown',
      },
    })),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.INFO_TEXT});
  }
}
