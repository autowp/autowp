import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopTwinsBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCountComponent} from '@utils/name-count/name-count.component';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-index-twins',
  imports: [RouterLink, AsyncPipe, RemarkModule, NameCountComponent],
  templateUrl: './twins.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class IndexTwinsComponent {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$ = this.#items.getTopTwinsBrandsList(
    new GetTopTwinsBrandsListRequest({language: this.#languageService.language}),
  );
}
