import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {APIBrandsListItem, BrandIcons, NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {EMPTY} from 'rxjs';
import {switchMap} from 'rxjs/operators';

@Component({
  selector: 'app-brands-item',
  imports: [RouterLink, NgbPopover, AsyncPipe],
  templateUrl: './item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsItemComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<APIBrandsListItem>();
  protected readonly brand$ = toObservable(this.brand);

  readonly icons = input.required<BrandIcons>();

  protected readonly response$ = this.brand$.pipe(
    switchMap((brand) =>
      brand
        ? this.#itemsClient.getBrandNewItems(
            new NewItemsRequest({
              itemId: '' + brand.id,
              language: this.#languageService.language,
            }),
          )
        : EMPTY,
    ),
  );

  protected cssClass(item: APIBrandsListItem): string {
    return item.catname.replace(/\./g, '_');
  }
}
