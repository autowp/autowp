import type {Item, PictureItemType} from '@grpc/spec.pb';
import type {NgbTypeaheadSelectItemEvent} from '@ng-bootstrap/ng-bootstrap';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {FormControl, ReactiveFormsModule} from '@angular/forms';
import {ItemFields, ItemListOptions, ItemsRequest, ItemType, PictureItemListOptions} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbTypeahead} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, debounceTime, distinctUntilChanged, EMPTY, map, of, switchMap} from 'rxjs';

export interface PersonSearchSelection {
  id: string;
  nameHtml: string;
  nameText: string;
}

@Component({
  selector: 'app-person-search',
  imports: [ReactiveFormsModule, NgbTypeahead],
  templateUrl: './person-search.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonSearchComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  /** When set, only persons already linked to at least one picture with this picture-item type
   * are offered (e.g. PICTURE_ITEM_AUTHOR → the catalogue's known photo authors). */
  readonly pictureItemType = input<null | PictureItemType>(null);
  readonly placeholder = input<string>('');

  readonly selected = output<PersonSearchSelection>();

  protected readonly query = new FormControl<string>('', {nonNullable: true});

  protected readonly dataSource: (text$: Observable<string>) => Observable<Item[]> = (text$) =>
    text$.pipe(
      debounceTime(200),
      distinctUntilChanged(),
      switchMap((query) => {
        const trimmed = query.trim();
        if (trimmed === '') {
          return of<Item[]>([]);
        }

        const options = new ItemListOptions({
          name: '%' + trimmed + '%',
          typeId: ItemType.ITEM_TYPE_PERSON,
        });

        const pictureItemType = this.pictureItemType();
        if (pictureItemType !== null) {
          options.pictureItems = new PictureItemListOptions({typeId: pictureItemType});
        }

        return this.#itemsClient
          .list(
            new ItemsRequest({
              fields: new ItemFields({nameHtml: true, nameText: true}),
              language: this.#languageService.language,
              limit: 10,
              options,
            }),
          )
          .pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
            map((response) => response.items ?? []),
          );
      }),
    );

  protected readonly formatter = (x: Item | string): string => (typeof x === 'string' ? x : x.nameText);

  protected onSelect(event: NgbTypeaheadSelectItemEvent): void {
    // ng-bootstrap types the event item as `any`; dataSource is the only feed and resolves Item[].
    const item = event.item as Item;
    this.selected.emit({id: item.id, nameHtml: item.nameHtml, nameText: item.nameText});
    this.query.setValue('', {emitEvent: false});
    event.preventDefault();
  }

  public clear(): void {
    this.query.setValue('', {emitEvent: false});
  }
}
