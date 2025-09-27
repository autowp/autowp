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
  selector: 'app-index-brands-brand',
  imports: [RouterLink, NgbPopover, AsyncPipe],
  templateUrl: './brand.component.html',
  styleUrl: './brand.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
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
