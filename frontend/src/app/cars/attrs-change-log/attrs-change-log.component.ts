import type {NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AttrUserValuesFields, AttrUserValuesRequest, User, UsersRequest} from '@grpc/spec.pb';
import {AttrsClient, UsersClient} from '@grpc/spec.pbsc';
import {NgbTypeahead} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {catchError, debounceTime, EMPTY, map, of, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {CarsAttrsChangeLogItemCacheService} from './item-cache.service';
import {CarsAttrsChangeLogRowComponent} from './row/row.component';

@Component({
  selector: 'app-cars-attrs-change-log',
  imports: [RouterLink, FormsModule, NgbTypeahead, ReactiveFormsModule, CarsAttrsChangeLogRowComponent],
  templateUrl: './attrs-change-log.component.html',
  styleUrl: './attrs-change-log.component.scss',
  providers: [CarsAttrsChangeLogItemCacheService],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsAttrsChangeLogComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);

  readonly #userID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('user_id') ?? '')), {
    requireSync: true,
  });

  readonly #itemID = toSignal(this.#route.queryParamMap.pipe(map((params) => params.get('item_id') ?? '')), {
    requireSync: true,
  });

  protected readonly itemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'cars-attrs-change-log-items',
    params: () => ({itemID: this.#itemID(), userID: this.#userID()}),
    stream: ({params: {itemID, userID}}) =>
      this.#attrsClient
        .getUserValues(
          new AttrUserValuesRequest({
            fields: new AttrUserValuesFields({valueText: true}),
            itemId: itemID,
            language: this.#languageService.language,
            userId: userID ? userID : undefined,
          }),
        )
        .pipe(map((response) => response.items ?? [])),
  });

  readonly #control = new FormControl<string>('', {nonNullable: true});

  protected readonly usersDataSource: (text$: Observable<string>) => Observable<(null | User)[]> = (
    text$: Observable<string>,
  ) =>
    text$.pipe(
      debounceTime(200),
      switchMap((query) => {
        if (query === '' || query === '#') {
          return of([]);
        }

        if (query.startsWith('#')) {
          return this.#userService.getUser$(query.substring(1) || '').pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((user) => [user]),
          );
        }

        return this.#usersClient.getUsers(new UsersRequest({limit: 10, search: query})).pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
          map((response) => response.items ?? []),
        );
      }),
    );

  constructor() {
    this.#pageEnv.set({pageId: 103});

    // Syncs the reactive FormControl's displayed value from the URL param (rather than the other
    // way around) - clearUser()/userOnSelect() below both drive user_id through the router, and
    // this keeps the typeahead input reflecting whatever the URL currently says.
    effect(() => {
      const userID = this.#userID();
      this.#control.setValue(userID ? '#' + userID : '');
    });
  }

  protected readonly userQuery = this.#control;
  protected readonly hasUserID = computed(() => !!this.#userID());

  protected userFormatter(x: string | User) {
    return x instanceof User ? x.name : x;
  }

  protected userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    // e.item is typed `any` by ng-bootstrap - usersDataSource above is the only source feeding
    // this typeahead, and it resolves (null | User)[].
    const selected = e.item as null | User;
    void this.#router.navigate([], {
      queryParams: {
        user_id: selected?.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearUser(control: FormControl<string>): void {
    control.setValue('');
    void this.#router.navigate([], {
      queryParams: {
        user_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }
}
