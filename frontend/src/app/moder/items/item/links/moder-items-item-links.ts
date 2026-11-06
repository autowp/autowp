import type {OnInit, ResourceRef} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {Item, ItemLinks} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, Injector, input, linkedSignal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {disabled, form, FormField, submit} from '@angular/forms/signals';
import {ItemLink, ItemLinkListOptions, ItemLinkRequest, ItemLinksRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {errorMessage} from 'app/grpc';
import {firstValueFrom} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';
interface LinksFormValue {
  links: {id: string; name: string; type: string; url: string}[];
  newLink: {name: string; type: string; url: string};
}

@Component({
  selector: 'app-moder-items-item-links',
  imports: [FormField],
  templateUrl: './moder-items-item-links.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemsItemLinks implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);
  readonly #injector = inject(Injector);

  readonly item = input.required<Item>();

  protected readonly canEditMeta = this.#auth.hasRole(Role.CARS_MODER);

  protected editForm!: FieldTree<LinksFormValue>;

  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // `item` is a *required* input, unreadable until Angular has bound it, which happens after
  // construction but before ngOnInit.
  protected linksResource!: ResourceRef<ItemLinks | undefined>;

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.linksResource = rxResource({
      // Seeds status as resolved from TransferState on hydration. Suffixed with item().id read
      // once at construction time - not actually a singleton across different items: a static id
      // would let a second instance of this component, created by navigating away and to a
      // different item's page before Angular's whenStable() ever resolves, match TransferState's
      // still-present entry from the first item and seed itself with the wrong data.
      id: `moder-items-item-links-${this.item().id}`,
      injector: this.#injector,
      params: () => this.item().id,
      stream: ({params: itemId}) =>
        this.#itemsClient.getItemLinks(new ItemLinksRequest({options: new ItemLinkListOptions({itemId})})),
    });
    const model = linkedSignal<LinksFormValue>(() => ({
      links: this.linksResource.hasValue()
        ? (this.linksResource.value().items ?? []).map(({id, name, type, url}) => ({id, name, type, url}))
        : [],
      newLink: {name: '', type: 'default', url: ''},
    }));
    this.editForm = form(
      model,
      (path) => {
        disabled(path, {when: ({state}) => state.submitting()});
      },
      {injector: this.#injector},
    );
  }

  protected saveLinks(itemId: string): void {
    void submit(this.editForm, async () => {
      const value = this.editForm().value();
      const operations: Promise<boolean>[] = value.links.map(async (link) => {
        try {
          if (link.url) {
            await firstValueFrom(this.#itemsClient.updateItemLink(new ItemLink({...link, itemId})));
          } else {
            await firstValueFrom(this.#itemsClient.deleteItemLink(new ItemLinkRequest({id: link.id})));
            if (this.item().id === itemId) {
              this.editForm().value.update((draft) => ({
                ...draft,
                links: draft.links.filter((row) => row.id !== link.id),
              }));
            }
          }
          return true;
        } catch (response: unknown) {
          this.#toastService.handleError(response);
          return false;
        }
      });
      if (value.newLink.url) {
        operations.push(
          (async () => {
            try {
              const response = await firstValueFrom(
                this.#itemsClient.createItemLink(new ItemLink({...value.newLink, itemId})),
              );
              if (this.item().id === itemId) {
                // Keep the successful create in the draft even if another row failed. A retry
                // updates this row by id instead of creating the same link again.
                this.editForm().value.update((draft) => ({
                  ...draft,
                  links: [...draft.links, {...value.newLink, id: response.id}],
                  newLink: {name: '', type: 'default', url: ''},
                }));
              }
              return true;
            } catch (response: unknown) {
              this.#toastService.handleError(response);
              return false;
            }
          })(),
        );
      }
      const results = await Promise.all(operations);
      if (results.every(Boolean) && this.item().id === itemId) {
        this.linksResource.reload();
      }
    });
  }
}
