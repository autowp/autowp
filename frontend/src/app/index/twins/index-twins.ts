import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {GetTopTwinsBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NameCount} from '@utils/name-count/name-count';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-index-twins',
  imports: [RouterLink, AsyncPipe, RemarkModule, NameCount],
  templateUrl: './index-twins.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexTwins {
  readonly #items = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$ = this.#items.getTopTwinsBrandsList(
    new GetTopTwinsBrandsListRequest({language: this.#languageService.language}),
  );
}
