import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  APIUser,
  APIUsersRequest,
  AttrUserValue,
  AttrUserValuesFields,
  AttrUserValuesRequest,
  ItemFields,
  ItemRequest,
} from '@grpc/spec.pb';
import {AttrsClient, ItemsClient, UsersClient} from '@grpc/spec.pbsc';
import {NgbTooltip, NgbTypeahead, NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {getUnitAbbrTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

interface AttrUserValueListItem {
  item$: Observable<APIItem | null>;
  path$: Observable<string[]>;
  unitAbbr$: Observable<null | string | undefined>;
  user$: Observable<APIUser | null>;
  userValue: AttrUserValue;
}

@Component({
  selector: 'app-cars-attrs-change-log',
  imports: [
    RouterLink,
    FormsModule,
    NgbTypeahead,
    NgbTooltip,
    UserComponent,
    AsyncPipe,
    DatePipe,
    TimeAgoPipe,
    ReactiveFormsModule,
  ],
  templateUrl: './attrs-change-log.component.html',
  styleUrl: './attrs-change-log.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsAttrsChangeLogComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #usersClient = inject(UsersClient);
  readonly #userService = inject(UserService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #attrsService = inject(APIAttrsService);

  readonly #itemsCache = new Map<string, Observable<APIItem>>();

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected readonly userID$: Observable<string> = this.#route.queryParamMap.pipe(
    map((params) => params.get('user_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly itemID$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly items$: Observable<{
    items: AttrUserValueListItem[];
  }> = combineLatest([this.userID$, this.itemID$]).pipe(
    switchMap(([userID, itemID]) =>
      this.#attrsClient.getUserValues(
        new AttrUserValuesRequest({
          fields: new AttrUserValuesFields({valueText: true}),
          itemId: itemID,
          language: this.#languageService.language,
          userId: userID ? userID : undefined,
        }),
      ),
    ),
    map((response) => ({
      items: (response.items || []).map((userValue) => {
        const attr$ = this.#attrsService.getAttribute$(userValue.attributeId);
        return {
          item$: this.getItem$(userValue.itemId),
          path$: this.#attrsService.getPath$(userValue.attributeId),
          unitAbbr$: attr$.pipe(map((attr) => (attr?.unitId ? getUnitAbbrTranslation(attr.unitId) : null))),
          user$: this.#userService.getUser$(userValue.userId),
          userValue,
        };
      }),
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #control = new FormControl<string>('', {nonNullable: true});

  protected readonly userQuery$ = this.userID$.pipe(
    map((userID) => {
      this.#control.setValue(userID ? '#' + userID : '');
      return this.#control;
    }),
  );

  protected readonly usersDataSource: (text$: Observable<string>) => Observable<(APIUser | null)[]> = (
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

        return this.#usersClient
          .getUsers(
            new APIUsersRequest({
              limit: '10',
              search: query,
            }),
          )
          .pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((response) => response.items || []),
          );
      }),
    );

  private getItem$(id: string): Observable<APIItem | null> {
    let o$ = this.#itemsCache.get(id);
    if (!o$) {
      o$ = this.#itemsClient
        .item(
          new ItemRequest({fields: new ItemFields({nameHtml: true}), id: id, language: this.#languageService.language}),
        )
        .pipe(shareReplay({bufferSize: 1, refCount: false}));
      this.#itemsCache.set(id, o$);
    }
    return o$;
  }

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 103}), 0);
  }

  protected userFormatter(x: APIUser | string) {
    return x instanceof APIUser ? x.name : x;
  }

  protected userOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.#router.navigate([], {
      queryParams: {
        user_id: e.item.id,
      },
      queryParamsHandling: 'merge',
    });
  }

  protected clearUser(control: FormControl<string>): void {
    control.setValue('');
    this.#router.navigate([], {
      queryParams: {
        user_id: null,
      },
      queryParamsHandling: 'merge',
    });
  }
}
