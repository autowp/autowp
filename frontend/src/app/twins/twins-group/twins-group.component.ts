import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink, RouterLinkActive, RouterOutlet} from '@angular/router';
import {ItemFields, ItemListOptions, ItemParentListOptions, ItemRequest, ItemsRequest, ItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

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
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the group id read once at construction time - a static id would let a
    // second instance of this component, created by navigating away and to a different twins
    // group's page before Angular's whenStable() ever resolves, match TransferState's
    // still-present entry from the first group and seed itself with the wrong data.
    id: `twins-group-${this.#groupID()}`,
    params: () => this.#groupID(),
    stream: ({params: groupID}) => {
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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so selectedBrandsResource's params() and the constructor effect() below
  // don't blow up on a non-NOT_FOUND groupResource error (surfaced generically by the template
  // instead).
  protected readonly groupData = computed(() =>
    this.groupResource.hasValue() ? this.groupResource.value() : undefined,
  );

  protected readonly selectedBrandsResource = rxResource({
    // Distinct id from groupResource above, also suffixed with the group id (see that resource's
    // comment) - this is not actually a singleton per page across different twins groups.
    id: `twins-group-selected-brands-${this.#groupID()}`,
    // Angular skips stream() entirely while params() returns undefined, so group is always
    // defined once stream() actually runs.
    params: () => this.groupData(),
    stream: ({params: group}) => {
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
        .pipe(map((response) => (response.items ?? []).map((item) => item.catname)));
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const group = this.groupData();
      if (group) {
        this.pageEnv.set({
          pageId: 25,
          title: group.nameText,
        });
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
