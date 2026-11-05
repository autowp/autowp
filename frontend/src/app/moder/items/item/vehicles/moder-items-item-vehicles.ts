import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map, of} from 'rxjs';
@Component({
  selector: 'app-moder-items-item-vehicles',
  imports: [ResourceStatus, RouterLink],
  templateUrl: './moder-items-item-vehicles.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemVehicles {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly itemId = input.required<string>();

  protected readonly engineVehiclesResource = rxResource({
    params: () => (this.itemId() && this.itemId() !== '0' ? this.itemId() : null),
    stream: ({params: itemId}) =>
      itemId === null
        ? of(undefined)
        : this.#itemsClient
            .list(
              new ItemsRequest({
                fields: new ItemFields({nameHtml: true}),
                language: this.#languageService.language,
                limit: 100,
                options: new ItemListOptions({
                  engineId: itemId,
                }),
              }),
            )
            .pipe(map((response) => response.items ?? [])),
  });
}
