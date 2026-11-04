import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {map} from 'rxjs';

import {IndexBrandsBrand} from './brand/index-brands-brand';

@Component({
  selector: 'app-index-brands',
  imports: [RouterLink, IndexBrandsBrand, AsyncPipe],
  templateUrl: './index-brands.html',
  styleUrl: './index-brands.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexBrands {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  // Deterministic (not truly random) so the skeleton's widths match between the SSR pass and
  // client hydration - Math.random() here would produce a different sequence each time and
  // mismatch the @for block's per-item tracking in the template.
  protected readonly placeholderItems = Array.from({length: 60}, (_, i) => ({width: 3 + (i % 6)}));

  protected readonly result$ = this.#items
    .getTopBrandsList(new GetTopBrandsListRequest({language: this.#languageService.language}))
    .pipe(
      map((response) => ({
        brands: response.brands,
        more: response.brands ? response.total - response.brands.length : 0,
      })),
    );
}
