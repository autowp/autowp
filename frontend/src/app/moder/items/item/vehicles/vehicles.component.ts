import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {Item, ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, map, Observable, switchMap} from 'rxjs';

@Component({
  selector: 'app-moder-items-item-vehicles',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './vehicles.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemVehiclesComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly itemId = input.required<string>();

  protected readonly engineVehicles$: Observable<Item[]> = toObservable(this.itemId).pipe(
    switchMap((itemId) =>
      itemId
        ? this.#itemsClient.list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
              language: this.#languageService.language,
              limit: 100,
              options: new ItemListOptions({
                engineId: itemId,
              }),
            }),
          )
        : EMPTY,
    ),
    map((response) => (response.items ? response.items : [])),
  );
}
