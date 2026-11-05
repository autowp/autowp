import type {Item} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {DomSanitizer} from '@angular/platform-browser';
import {GetSpecificationsRequest} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map} from 'rxjs';
@Component({
  selector: 'app-cars-specifications-editor-result',
  imports: [ResourceStatus],
  templateUrl: './cars-specifications-editor-result.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorResult {
  readonly #attrsClient = inject(AttrsClient);
  readonly #sanitizer = inject(DomSanitizer);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();

  protected readonly htmlResource = rxResource({
    params: () => this.item(),
    stream: ({params: item}) =>
      this.#attrsClient
        .getSpecifications(new GetSpecificationsRequest({itemId: item.id, language: this.#languageService.language}))
        .pipe(
          // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
          map((response) => this.#sanitizer.bypassSecurityTrustHtml(response.html)),
        ),
  });
}
