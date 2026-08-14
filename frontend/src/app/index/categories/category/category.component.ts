import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {NewItemsRequest, TopCategoriesListItem} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {switchMap} from 'rxjs';

@Component({
  selector: 'app-index-categories-category',
  imports: [RouterLink, NgbPopover, AsyncPipe],
  templateUrl: './category.component.html',
  styleUrl: './category.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexCategoriesCategoryComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly category = input.required<TopCategoriesListItem>();
  protected readonly category$ = toObservable(this.category);

  protected readonly response$ = this.category$.pipe(
    switchMap((category) =>
      this.#itemsClient.getNewItems(
        new NewItemsRequest({
          itemId: category.id,
          language: this.#languageService.language,
        }),
      ),
    ),
  );
}
