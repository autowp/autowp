import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, UpperCasePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {RouterLink} from '@angular/router';
import {GetItemLanguagesRequest, ItemLanguage} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbNav, NgbNavContent, NgbNavItem, NgbNavLink, NgbNavLinkBase, NgbNavOutlet} from '@ng-bootstrap/ng-bootstrap';
import {ContentLanguageService} from '@services/content-language';
import {combineLatest, map, of, switchMap} from 'rxjs';

import {MarkdownEditComponent} from '../../../../markdown-edit/markdown-edit/markdown-edit.component';

@Component({
  selector: 'app-moder-items-item-name',
  imports: [
    NgbNav,
    NgbNavItem,
    NgbNavLink,
    NgbNavLinkBase,
    NgbNavContent,
    FormsModule,
    MarkdownEditComponent,
    RouterLink,
    NgbNavOutlet,
    AsyncPipe,
    UpperCasePipe,
  ],
  templateUrl: './name.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemNameComponent {
  readonly #contentLanguage = inject(ContentLanguageService);
  readonly #itemsClient = inject(ItemsClient);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

  protected readonly loadingNumber = signal(false);

  protected readonly languages$ = this.#contentLanguage.languages$.pipe(
    map((contentLanguages) => {
      const languages = new Map<string, ItemLanguage>();

      for (const language of contentLanguages) {
        languages.set(
          language,
          new ItemLanguage({
            fullText: '',
            fullTextId: 0,
            language,
            name: '',
            text: '',
            textId: 0,
          }),
        );
      }

      return languages;
    }),
  );

  protected readonly data$: Observable<ItemLanguage[]> = this.item$.pipe(
    switchMap((item) =>
      combineLatest([
        of(item.id),
        this.#itemsClient.getItemLanguages(new GetItemLanguagesRequest({itemId: item.id})),
        this.languages$,
      ]),
    ),
    map(([itemId, {items}, languages]) => {
      languages.forEach((language) => {
        language.itemId = itemId;
      });

      for (const value of items ?? []) {
        languages.set(value.language, value);
      }

      return Array.from(languages.values());
    }),
  );

  protected saveLanguages(itemLanguages: ItemLanguage[]) {
    for (const itemLanguage of itemLanguages) {
      this.loadingNumber.set(true);
      this.#itemsClient.updateItemLanguage(itemLanguage).subscribe({
        error: () => {
          this.loadingNumber.set(false);
        },
        next: () => {
          this.loadingNumber.set(false);
        },
      });
    }
  }
}
