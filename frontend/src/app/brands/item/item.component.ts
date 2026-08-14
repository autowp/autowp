import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {BrandIcons, BrandsListItem, NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {switchMap} from 'rxjs';

@Component({
  selector: 'app-brands-item',
  imports: [RouterLink, NgbPopover, AsyncPipe],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsItemComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<BrandsListItem>();
  protected readonly brand$ = toObservable(this.brand);

  readonly icons = input.required<BrandIcons>();

  protected readonly response$ = this.brand$.pipe(
    switchMap((brand) =>
      this.#itemsClient.getBrandNewItems(
        new NewItemsRequest({
          itemId: brand.id,
          language: this.#languageService.language,
        }),
      ),
    ),
  );

  protected cssClass(item: BrandsListItem): string {
    return item.catname.replace(/\./g, '_');
  }
}
