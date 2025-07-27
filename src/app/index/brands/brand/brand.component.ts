import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {APITopBrandsListItem, NewItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {EMPTY} from 'rxjs';
import {switchMap} from 'rxjs/operators';

@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, NgbPopover, AsyncPipe],
  selector: 'app-index-brands-brand',
  styleUrls: ['./brand.component.scss'],
  templateUrl: './brand.component.html',
})
export class IndexBrandsBrandComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly brand = input.required<APITopBrandsListItem>();
  protected readonly brand$ = toObservable(this.brand);

  protected readonly response$ = this.brand$.pipe(
    switchMap((brand) =>
      brand
        ? this.#itemsClient.getBrandNewItems(
            new NewItemsRequest({
              itemId: brand.id,
              language: this.#languageService.language,
            }),
          )
        : EMPTY,
    ),
  );
}
