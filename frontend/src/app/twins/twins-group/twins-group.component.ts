import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentListOptions,
  ItemRequest,
  ItemsRequest,
  ItemType,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {TwinsSidebarComponent} from '../sidebar.component';

@Component({
  selector: 'app-twins-group',
  imports: [RouterLink, RouterLinkActive, RouterOutlet, TwinsSidebarComponent, AsyncPipe],
  templateUrl: './twins-group.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly group$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('group') ?? ''),
    distinctUntilChanged(),
    switchMap((group) =>
      group
        ? this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({
                acceptedPicturesCount: true,
                hasChildSpecs: true,
                nameHtml: true,
                nameText: true,
              }),
              id: group,
              language: this.#languageService.language,
            }),
          )
        : EMPTY,
    ),
    switchMap((group) => {
      if (!group) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(group);
    }),
    tap((group) => {
      setTimeout(
        () =>
          this.#pageEnv.set({
            pageId: 25,
            title: group.nameText,
          }),
        0,
      );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly selectedBrands$: Observable<string[]> = this.group$.pipe(
    switchMap((group) =>
      this.#itemsClient.list(
        new ItemsRequest({
          options: new ItemListOptions({
            child: new ItemParentListOptions({
              itemParentParentByChild: new ItemParentListOptions({
                parentId: group.id,
              }),
            }),
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
        }),
      ),
    ),
    map((response) => (response.items || []).map((item) => item.catname)),
  );

  protected readonly layoutParams$ = this.#pageEnv.layoutParams$.asObservable();
}
