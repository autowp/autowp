import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink, RouterOutlet} from '@angular/router';
import {ItemFields, ItemRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

@Component({
  selector: 'app-persons-person',
  imports: [RouterLink, RouterOutlet, AsyncPipe],
  templateUrl: './person.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
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

      const item = this.itemResource.value();
      if (item) {
        this.pageEnv.set({
          pageId: 213,
          title: item.nameText,
        });
      }
    });
  }
}
