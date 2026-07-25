import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {DomSanitizer} from '@angular/platform-browser';
import {GetSpecificationsRequest, Item} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

@Component({
  selector: 'app-cars-specifications-editor-result',
  imports: [AsyncPipe],
  templateUrl: './result.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditorResultComponent {
  readonly #attrsClient = inject(AttrsClient);
  readonly #sanitizer = inject(DomSanitizer);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<Item>();

  protected readonly html$ = toObservable(this.item).pipe(
    switchMap((item) =>
      item
        ? this.#attrsClient.getSpecifications(
            new GetSpecificationsRequest({itemId: item.id, language: this.#languageService.language}),
          )
        : EMPTY,
    ),
    // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
    map((response) => this.#sanitizer.bypassSecurityTrustHtml(response.html)),
  );
}
