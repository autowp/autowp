import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopTwinsBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {MarkdownComponent} from '@utils/markdown/markdown.component';

@Component({
  selector: 'app-index-twins',
  imports: [RouterLink, MarkdownComponent, AsyncPipe],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexTwinsComponent {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$ = this.#items.getTopTwinsBrandsList(
    new GetTopTwinsBrandsListRequest({language: this.#languageService.language}),
  );
}
