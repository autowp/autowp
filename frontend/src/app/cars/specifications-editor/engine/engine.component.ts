import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {APIItem, UpdateItemRequest} from '@grpc/spec.pb';
import {ItemFields, ItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {Observable, of} from 'rxjs';
import {shareReplay, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../../toasts/toasts.service';

@Component({
  selector: 'app-cars-specifications-editor-engine',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './engine.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorEngineComponent {
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<APIItem>();
  protected readonly item$ = toObservable(this.item);

  readonly changed = output<void>();
  protected readonly isAllowedEditEngine$ = this.#auth
    .hasRole$(Role.CARS_MODER)
    .pipe(shareReplay({bufferSize: 1, refCount: false}));

  protected readonly engine$: Observable<APIItem | null> = this.item$.pipe(
    switchMap((item) => {
      if (!item?.engineItemId || item?.engineItemId === '0') {
        return of(null);
      }

      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({nameHtml: true}),
          id: item.engineItemId,
          language: this.#languageService.language,
        }),
      );
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  private setEngineID(item: APIItem, value: string, inherited: boolean) {
    this.#itemsClient
      .updateItem(
        new UpdateItemRequest({
          item: new APIItem({
            engineInherit: inherited,
            engineItemId: value,
            id: item.id,
          }),
          updateMask: new FieldMask({paths: ['engine_inherit', 'engine_item_id']}),
        }),
      )
      .subscribe({
        error: (response: unknown) => this.#toastService.handleError(response),
        next: () => this.changed.emit(void 0),
      });
  }

  protected inheritEngine(item: APIItem) {
    this.setEngineID(item, '0', true);
  }

  protected cancelInheritance(item: APIItem) {
    this.setEngineID(item, '0', false);
  }
}
