import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  APIUser,
  AttrAttribute,
  AttrConflict,
  AttrConflictsRequest,
  AttrConflictValue,
  ItemFields,
  ItemRequest,
  Pages,
} from '@grpc/spec.pb';
import {AttrsClient, ItemsClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {getUnitAbbrTranslation} from '@utils/translations';
import {combineLatest, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {UserComponent} from '../../user/user/user.component';

interface APIAttrConflictInList {
  attribute$: Observable<AttrAttribute | undefined>;
  conflict: AttrConflict;
  item$: Observable<APIItem | null>;
  unitName$: Observable<null | string>;
  values: APIAttrConflictValueInList[];
}

interface APIAttrConflictValueInList {
  user$: Observable<APIUser | null>;
  value: AttrConflictValue;
}

function mapFilter(filter: null | string): AttrConflictsRequest.Filter {
  switch (filter) {
    case '-1':
      return AttrConflictsRequest.Filter.I_DISAGREE;
    case '0':
      return AttrConflictsRequest.Filter.ALL;
    case '1':
      return AttrConflictsRequest.Filter.DO_NOT_AGREE_WITH_ME;
    case 'minus-weight':
      return AttrConflictsRequest.Filter.MINUS_WEIGHT;
  }

  return AttrConflictsRequest.Filter.ALL;
}

@Component({
  selector: 'app-account-specs-conflicts',
  imports: [RouterLink, UserComponent, PaginatorComponent, AsyncPipe],
  templateUrl: './specs-conflicts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountSpecsConflictsComponent implements OnInit {
  readonly #languageService = inject(LanguageService);
  readonly #userService = inject(UserService);
  protected readonly auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #itemsClient = inject(ItemsClient);
  readonly #attrsService = inject(APIAttrsService);

  protected readonly page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(10),
  );

  protected readonly filter$: Observable<AttrConflictsRequest.Filter> = this.#route.queryParamMap.pipe(
    map((params) => params.get('filter')),
    distinctUntilChanged(),
    debounceTime(10),
    map((filter) => mapFilter(filter)),
  );

  protected readonly user$: Observable<APIUser | null> = this.auth.user$;

  readonly #itemsCache = new Map<string, Observable<APIItem>>();

  private getItem$(id: string): Observable<APIItem | null> {
    let o$ = this.#itemsCache.get(id);
    if (!o$) {
      o$ = this.#itemsClient
        .item(new ItemRequest({fields: new ItemFields({nameHtml: true}), id, language: this.#languageService.language}))
        .pipe(shareReplay({bufferSize: 1, refCount: false}));
      this.#itemsCache.set(id, o$);
    }
    return o$;
  }

  protected readonly data$: Observable<{conflicts: APIAttrConflictInList[]; paginator: Pages | undefined}> =
    combineLatest([this.page$, this.filter$]).pipe(
      switchMap(([page, filter]) =>
        combineLatest([
          this.#attrsClient.getConflicts(
            new AttrConflictsRequest({
              filter,
              language: this.#languageService.language,
              page,
            }),
          ),
          this.user$,
        ]),
      ),
      map(([data, user]) => ({
        conflicts: (data.items || []).map((conflict): APIAttrConflictInList => {
          const attribute$ = this.#attrsService.getAttribute$(conflict.attributeId);
          return {
            attribute$,
            conflict,
            item$: this.getItem$(conflict.itemId),
            unitName$: attribute$.pipe(
              map((attr) => (attr?.unitId && attr.unitId !== '0' ? getUnitAbbrTranslation(attr.unitId) : null)),
            ),
            values: (conflict.values || []).map((value) => ({
              user$: user?.id === value.userId ? of(null) : this.#userService.getUser$(value.userId),
              value,
            })),
          };
        }),
        paginator: data.paginator,
      })),
    );

  protected readonly AttrConflictsRequest = AttrConflictsRequest;

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 188}), 0);
  }
}
