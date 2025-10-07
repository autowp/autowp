import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {DomSanitizer} from '@angular/platform-browser';
import {APIItem} from '@grpc/spec.pb';
import {AttrsService} from '@rest/api/attrs.service';
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
  readonly #attrsService = inject(AttrsService);
  readonly #sanitizer = inject(DomSanitizer);
  readonly #languageService = inject(LanguageService);

  readonly item = input.required<APIItem>();

  protected readonly html$ = toObservable(this.item).pipe(
    switchMap((item) =>
      item
        ? this.#attrsService.attrsGetSpecifications({itemId: item.id, language: this.#languageService.language})
        : EMPTY,
    ),
    // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
    map((response) => this.#sanitizer.bypassSecurityTrustHtml(response.html)),
  );
}
