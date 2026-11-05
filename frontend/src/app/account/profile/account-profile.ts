import type {ElementRef, OnInit} from '@angular/core';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {AsyncPipe} from '@angular/common';
import {HttpClient, HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, computed, inject, linkedSignal, signal, viewChild} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {form, FormField, submit} from '@angular/forms/signals';
import {environment} from '@environment/environment';
import {DeleteUserPhotoRequest, MeRequest, UpdateUserRequest, User, UserFields} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {TimezoneService} from '@services/timezone';
import {browserWindow} from '@utils/browser-window';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import Keycloak from 'keycloak-js';
import {RemarkModule} from 'ngx-remark';
import {catchError, EMPTY, firstValueFrom, of, switchMap, tap} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';
import {AccountContactsEditor} from '../contacts-editor/account-contacts-editor';
@Component({
  selector: 'app-account-profile',
  imports: [FormField, AsyncPipe, InvalidParamsPipe, RemarkModule, AccountContactsEditor],
  templateUrl: './account-profile.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountProfile implements OnInit {
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

  readonly #authUser = toSignal(
    this.#auth.user$.pipe(
      tap((user) => {
        if (!user && this.#window)
          void this.#keycloak.login({locale: this.#languageService.language, redirectUri: this.#window.location.href});
      }),
    ),
  );
  protected readonly userResource = rxResource({
    params: () => this.#authUser(),
    stream: ({params}) =>
      params
        ? this.#usersClient
            .me(
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
            )
            .pipe(
              tap({
                error: (error: unknown) => {
                  this.#toastService.handleError(error);
                },
              }),
            )
        : of(undefined),
  });
  protected readonly user = computed(() => (this.userResource.hasValue() ? this.userResource.value() : undefined));

  protected readonly votesPerDay = computed(() => +(this.user()?.votesPerDay ?? 0) || 0);
  protected readonly votesLeft = computed(() => +(this.user()?.votesLeft ?? 0) || 0);
  protected readonly photo = computed(() => this.user()?.img);

  readonly #model = linkedSignal(() => ({
    language: this.user()?.language ?? '',
    timezone: this.user()?.timezone ?? '',
  }));
  protected readonly form = form(this.#model);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_PROFILE});
  }

  protected reload(): void {
    this.userResource.reload();
  }

  private showSavedMessage() {
    this.#toastService.success($localize`Data saved`);
  }

  protected sendSettings(id: string) {
    void submit(this.form, async () => {
      this.settingsInvalidParams.set({});

      try {
        await firstValueFrom(
          this.#usersClient.updateUser(
            new UpdateUserRequest({
              updateMask: new FieldMask({paths: ['language', 'timezone']}),
              user: new User({
                id,
                language: this.#model().language || undefined,
                timezone: this.#model().timezone || undefined,
              }),
            }),
          ),
        );
        this.showSavedMessage();
      } catch (response: unknown) {
        if (response instanceof GrpcStatusEvent) {
          const fieldViolations = extractFieldViolations(response);
          this.settingsInvalidParams.set(fieldViolations2InvalidParams(fieldViolations));
        } else {
          this.#toastService.handleError(response);
        }
      }
    });
  }

  protected resetPhoto(id: string) {
    this.#usersClient.deleteUserPhoto(new DeleteUserPhotoRequest({id})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        this.userResource.reload();
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
          this.userResource.reload();
        }),
      )
      .subscribe();
  }
}
