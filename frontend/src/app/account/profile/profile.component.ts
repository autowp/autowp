import type {ElementRef, OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {HttpClient, HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject, signal, viewChild} from '@angular/core';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {environment} from '@environment/environment';
import {DeleteUserPhotoRequest, MeRequest, UpdateUserRequest, User, UserFields} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {TimezoneService} from '@services/timezone';
import {browserWindow} from '@utils/browser-window';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import Keycloak from 'keycloak-js';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, catchError, combineLatest, EMPTY, map, of, shareReplay, switchMap, tap} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';
import {AccountContactsEditorComponent} from '../contacts-editor/contacts-editor.component';

interface FormControls {
  language: FormControl<string>;
  timezone: FormControl<string>;
}

@Component({
  selector: 'app-account-profile',
  imports: [
    FormsModule,
    AsyncPipe,
    InvalidParamsPipe,
    ReactiveFormsModule,
    RemarkModule,
    AccountContactsEditorComponent,
  ],
  templateUrl: './profile.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountProfileComponent implements OnInit {
  readonly #http = inject(HttpClient);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #timezone = inject(TimezoneService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);
  readonly #window = browserWindow();

  protected readonly settingsInvalidParams = signal<InvalidParams>({});
  protected readonly photoInvalidParams = signal<InvalidParams>({});

  private readonly input = viewChild<ElementRef<HTMLInputElement>>('input');

  protected readonly changeProfileUrl =
    environment.keycloak.url.replace(/\/$/g, '') + '/realms/' + environment.keycloak.realm + '/account/#/personal-info';

  protected readonly timezones$ = this.#timezone.timezones$;

  protected readonly languages: {name: string; value: string}[] = environment.languages.map((language) => ({
    name: language.name,
    value: language.code,
  }));

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly user$ = combineLatest([this.#auth.user$, this.#reload$]).pipe(
    switchMap(([user]) => {
      if (!user) {
        if (this.#window) {
          void this.#keycloak.login({
            locale: this.#languageService.language,
            redirectUri: this.#window.location.href,
          });
        }
        return EMPTY;
      }

      return of(user);
    }),
    switchMap(() =>
      this.#usersClient.me(
        new MeRequest({
          fields: new UserFields({
            contacts: true,
            img: true,
            language: true,
            timezone: true,
            votesLeft: true,
            votesPerDay: true,
          }),
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly votesPerDay$ = this.user$.pipe(map((user) => +user.votesPerDay || 0));
  protected readonly votesLeft$ = this.user$.pipe(map((user) => +user.votesLeft || 0));
  protected readonly photo$ = this.user$.pipe(map((user) => user.img));

  protected readonly form$: Observable<FormGroup<FormControls>> = this.user$.pipe(
    map(
      (user) =>
        new FormGroup({
          language: new FormControl<string>(user.language, {nonNullable: true}),
          timezone: new FormControl<string>(user.timezone, {nonNullable: true}),
        }),
    ),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_PROFILE});
  }

  protected reload(): void {
    this.#reload$.next(void 0);
  }

  private showSavedMessage() {
    this.#toastService.success($localize`Data saved`);
  }

  protected sendSettings(form: FormGroup<FormControls>, id: string) {
    this.settingsInvalidParams.set({});

    this.#usersClient
      .updateUser(
        new UpdateUserRequest({
          updateMask: new FieldMask({paths: ['language', 'timezone']}),
          user: new User({
            id,
            language: form.controls.language.value || undefined,
            timezone: form.controls.timezone.value || undefined,
          }),
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          if (response instanceof GrpcStatusEvent) {
            const fieldViolations = extractFieldViolations(response);
            this.settingsInvalidParams.set(fieldViolations2InvalidParams(fieldViolations));
          } else {
            this.#toastService.handleError(response);
          }
        },
        next: () => {
          this.showSavedMessage();
        },
      });
  }

  protected resetPhoto(id: string) {
    this.#usersClient.deleteUserPhoto(new DeleteUserPhotoRequest({id})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.#reload$.next(void 0);
      },
    });
  }

  protected onChange(user: User, event: Event) {
    const files = [].slice.call((event.target as HTMLInputElement).files);
    if (files.length <= 0) {
      return;
    }

    const file = files[0];

    const formData: FormData = new FormData();
    formData.append('photo', file);

    return this.#http
      .request('POST', '/api/user/' + user.id + '/photo', {body: formData})
      .pipe(
        catchError((response: unknown) => {
          const input = this.input();
          if (input) {
            input.nativeElement.value = '';
          }
          if (response instanceof HttpErrorResponse && response.status === 400) {
            // HttpErrorResponse.error is `any` - the backend error body's shape is only known by
            // convention (a `invalid_params` field), not typed by Angular.
            const body = response.error as {invalid_params: InvalidParams};
            this.photoInvalidParams.set(body.invalid_params);
            return EMPTY;
          }

          this.#toastService.handleError(response);
          return EMPTY;
        }),
        tap(() => {
          const input = this.input();
          if (input) {
            input.nativeElement.value = '';
          }
        }),
        switchMap(() => this.#usersClient.me(new MeRequest({fields: new UserFields({img: true})}))),
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
        tap(() => {
          this.#reload$.next();
        }),
      )
      .subscribe();
  }
}
