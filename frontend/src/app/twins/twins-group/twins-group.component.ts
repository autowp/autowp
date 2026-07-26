import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {ItemFields, ItemListOptions, ItemParentListOptions, ItemRequest, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {of} from 'rxjs';
import {map} from 'rxjs/operators';

import {TwinsSidebarComponent} from '../sidebar.component';

@Component({
  selector: 'app-twins-group',
  imports: [RouterLink, RouterLinkActive, RouterOutlet, TwinsSidebarComponent],
  templateUrl: './twins-group.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupComponent {
  readonly #route = inject(ActivatedRoute);
  protected readonly pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #groupID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('group') ?? '')), {
    requireSync: true,
  });

  protected readonly groupResource = rxResource({
    stream: () => {
      const groupID = this.#groupID();
      if (!groupID) {
        return notFoundError();
      }
      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            acceptedPicturesCount: true,
            hasChildSpecs: true,
            nameHtml: true,
            nameText: true,
          }),
          id: groupID,
          language: this.#languageService.language,
        }),
      );
    },
  });

  protected readonly selectedBrandsResource = rxResource({
    params: () => this.groupResource.value(),
    stream: ({params: group}) => {
      if (!group) {
        return of([]);
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            options: new ItemListOptions({
              child: new ItemParentListOptions({
                itemParentParentByChild: new ItemParentListOptions({
                  parentId: group.id,
                }),
              }),
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
          }),
        )
        .pipe(map((response) => (response.items || []).map((item) => item.catname)));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const group = this.groupResource.value();
      if (group) {
        this.pageEnv.set({
          pageId: 25,
          title: group.nameText,
        });
      }
    });
  }
}
