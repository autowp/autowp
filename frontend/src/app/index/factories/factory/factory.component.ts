import type {TopFactoriesListItem} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCountComponent} from '@utils/name-count/name-count.component';
import {switchMap} from 'rxjs';

@Component({
  selector: 'app-index-factories-factory',
  imports: [AsyncPipe, NameCountComponent],
  templateUrl: './factory.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class IndexFactoriesFactoryComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly factory = input.required<TopFactoriesListItem>();
  protected readonly factory$ = toObservable(this.factory);

  protected readonly response$ = this.factory$.pipe(
    switchMap((factory) =>
      this.#itemsClient.getNewItems(
        new NewItemsRequest({
          itemId: factory.id,
          language: this.#languageService.language,
        }),
      ),
    ),
  );
}
