import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {Item, ItemFields, ItemRequest, UpdateItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {of} from 'rxjs';

import {ToastsService} from '../../../toasts/toasts.service';
@Component({
  selector: 'app-cars-specifications-editor-engine',
  imports: [ResourceStatus, RouterLink],
  templateUrl: './cars-specifications-editor-engine.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorEngine {
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();

  readonly changed = output();
  protected readonly isAllowedEditEngine = toSignal(this.#auth.hasRole$(Role.CARS_MODER), {initialValue: false});
  protected readonly engineResource = rxResource({
    params: () => this.item().engineItemId,
    stream: ({params}) =>
      !params || params === '0'
        ? of(null)
        : this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({nameHtml: true}),
              id: params,
              language: this.#languageService.language,
            }),
          ),
  });

  private setEngineID(item: Item, value: string, inherited: boolean) {
    this.#itemsClient
      .updateItem(
        new UpdateItemRequest({
          item: new Item({
            engineInherit: inherited,
            engineItemId: value,
            id: item.id,
          }),
          updateMask: new FieldMask({paths: ['engine_inherit', 'engine_item_id']}),
        }),
      )
      .subscribe({
        error: (response: unknown) => {
          this.#toastService.handleError(response);
        },
        next: () => {
          this.changed.emit(void 0);
        },
      });
  }

  protected inheritEngine(item: Item) {
    this.setEngineID(item, '0', true);
  }

  protected cancelInheritance(item: Item) {
    this.setEngineID(item, '0', false);
  }
}
