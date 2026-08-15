import type {TwinsBrandsList, TwinsBrandsListItem} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTwinsBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';

@Component({
  selector: 'app-twins-sidebar',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './sidebar.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsSidebarComponent {
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly selected = input.required<string[]>();

  protected readonly brands$: Observable<TwinsBrandsList> = this.#itemsClient.getTwinsBrandsList(
    new GetTwinsBrandsListRequest({language: this.#languageService.language}),
  );

  protected active(item: TwinsBrandsListItem): boolean {
    return this.selected().includes(item.catname);
  }
}
