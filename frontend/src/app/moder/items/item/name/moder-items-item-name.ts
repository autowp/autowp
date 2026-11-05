import type {FieldTree} from '@angular/forms/signals';
import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, UpperCasePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, Injector, input, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {applyEach, disabled, form, FormField, maxLength, submit} from '@angular/forms/signals';
import {RouterLink} from '@angular/router';
import {GetItemLanguagesRequest, ItemLanguage} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbNav, NgbNavContent, NgbNavItem, NgbNavLink, NgbNavLinkBase, NgbNavOutlet} from '@ng-bootstrap/ng-bootstrap';
import {ContentLanguageService} from '@services/content-language';
import {combineLatest, firstValueFrom, map, of, switchMap} from 'rxjs';

import {MarkdownEdit} from '../../../../markdown-edit/markdown-edit/markdown-edit';
import {ToastsService} from '../../../../toasts/toasts.service';

interface LanguagesFormValue {
  languages: Pick<ItemLanguage, 'fullText' | 'fullTextId' | 'itemId' | 'language' | 'name' | 'text' | 'textId'>[];
}

@Component({
  selector: 'app-moder-items-item-name',
  imports: [
    NgbNav,
    NgbNavItem,
    NgbNavLink,
    NgbNavLinkBase,
    NgbNavContent,
    FormField,
    MarkdownEdit,
    RouterLink,
    NgbNavOutlet,
    AsyncPipe,
    UpperCasePipe,
  ],
  templateUrl: './moder-items-item-name.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemName {
  readonly #injector = inject(Injector);
  readonly #toasts = inject(ToastsService);
  readonly #contentLanguage = inject(ContentLanguageService);
  readonly #itemsClient = inject(ItemsClient);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

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

  protected readonly editForm$: Observable<FieldTree<LanguagesFormValue>> = this.item$.pipe(
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

      return form(
        signal<LanguagesFormValue>({
          languages: Array.from(languages.values()).map(
            ({itemId, language, name, text, textId, fullText, fullTextId}) => ({
              itemId,
              language,
              name,
              text,
              textId,
              fullText,
              fullTextId,
            }),
          ),
        }),
        (path) => {
          applyEach(path.languages, (language) => {
            maxLength(language.name, 255);
          });
          disabled(path, {when: ({state}) => state.submitting()});
        },
        {injector: this.#injector},
      );
    }),
  );

  protected saveLanguages(editForm: FieldTree<LanguagesFormValue>): void {
    void submit(editForm, async () => {
      const value = editForm().value();
      const results = await Promise.all(
        value.languages.map(async (language) => {
          try {
            await firstValueFrom(this.#itemsClient.updateItemLanguage(new ItemLanguage(language)));
            return true;
          } catch (response: unknown) {
            this.#toasts.handleError(response);
            return false;
          }
        }),
      );
      if (results.every(Boolean)) {
        editForm().reset(value);
      }
    });
  }
}
