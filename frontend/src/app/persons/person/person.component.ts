import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink, RouterOutlet} from '@angular/router';
import {ItemFields, ItemRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

@Component({
  selector: 'app-persons-person',
  imports: [RouterLink, RouterOutlet, AsyncPipe],
  templateUrl: './person.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class PersonsPersonComponent {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #auth = inject(AuthService);
  protected readonly pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  protected readonly itemResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the person id read once at construction time - a static id would let a
    // second instance of this component, created by navigating away and to a different person's
    // page before Angular's whenStable() ever resolves, match TransferState's still-present
    // entry from the first person and seed itself with the wrong data.
    id: `persons-person-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
            id: itemID,
            language: this.#languageService.language,
          }),
        )
        .pipe(switchMap((item) => (item.itemTypeId === ItemType.ITEM_TYPE_PERSON ? of(item) : notFoundError()))),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that.
      if (!this.itemResource.hasValue()) {
        return;
      }

      const item = this.itemResource.value();
      this.pageEnv.set({
        pageId: PageId.PERSON,
        title: item.nameText,
      });
    });
  }

  protected readonly errorMessage = errorMessage;
}
