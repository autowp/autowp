import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Item, ItemFields, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

@Component({
  selector: 'app-moder-items-too-big',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './too-big.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsTooBigComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$: Observable<Item[]> = this.#itemsClient
    .list(
      new ItemsRequest({
        fields: new ItemFields({childsCount: true, nameHtml: true}),
        language: this.#languageService.language,
        limit: 100,
        order: ItemsRequest.Order.CHILDS_COUNT,
      }),
    )
    .pipe(map((response) => response.items || []));

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 131,
    });
  }
}
