import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {APIItem, APIItemList, ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {combineLatest, of} from 'rxjs';
import {map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';

@Component({
  selector: 'app-moder-items-alpha',
  imports: [RouterLink, PaginatorComponent, AsyncPipe],
  templateUrl: './alpha.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsAlphaComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly groups$ = this.#itemsClient.getAlpha(new Empty());

  protected readonly char$ = this.#route.queryParamMap.pipe(
    map((query) => query.get('char')),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #page$ = this.#route.queryParamMap.pipe(map((query) => parseInt(query.get('page') ?? '', 10)));

  protected readonly items$ = combineLatest([this.char$, this.#page$]).pipe(
    switchMap(([char, page]) =>
      char
        ? this.#itemsClient.list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
              language: this.#languageService.language,
              limit: 40,
              options: new ItemListOptions({name: char + '%'}),
              page,
            }),
          )
        : of({
            items: [] as APIItem[],
            paginator: undefined,
          } as APIItemList),
    ),
  );

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 74,
        }),
      0,
    );
  }
}
