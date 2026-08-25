import type {OnInit, ResourceRef} from '@angular/core';
import type {Item, ItemLinks} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, Injector, input, signal} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {FormsModule} from '@angular/forms';
import {ItemLink, ItemLinkListOptions, ItemLinkRequest, ItemLinksRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {errorMessage} from 'app/grpc';
import {catchError, forkJoin, map, of, tap} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-items-item-links',
  imports: [FormsModule, AsyncPipe],
  templateUrl: './links.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ModerItemsItemLinksComponent implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #toastService = inject(ToastsService);
  readonly #injector = inject(Injector);

  readonly item = input.required<Item>();
  protected readonly item$ = toObservable(this.item);

  protected readonly loadingNumber = signal(false);

  protected readonly canEditMeta$ = this.#auth.hasRole$(Role.CARS_MODER);

  protected readonly newLink = {
    name: '',
    type: 'default',
    url: '',
  };

  // Constructed in ngOnInit() (with an explicit injector) rather than as a field initializer:
  // `item` is a *required* input, unreadable until Angular has bound it, which happens after
  // construction but before ngOnInit.
  protected linksResource!: ResourceRef<ItemLinks | undefined>;

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
  }

  protected saveLinks(itemId: string, links: ItemLink[]) {
    const promises: Observable<null>[] = [];

    if (this.newLink.url) {
      promises.push(
        this.#itemsClient
          .createItemLink(
            new ItemLink({
              itemId: itemId,
              name: this.newLink.name,
              type: this.newLink.type,
              url: this.newLink.url,
            }),
          )
          .pipe(
            catchError((response: unknown) => {
              this.#toastService.handleError(response);
              return of(null);
            }),
            tap((response) => {
              if (response) {
                this.newLink.name = '';
                this.newLink.url = '';
                this.newLink.type = 'default';
              }
            }),
            map(() => null),
          ),
      );
    }

    for (const link of links) {
      if (link.url) {
        promises.push(
          this.#itemsClient
            .updateItemLink(
              new ItemLink({
                id: link.id,
                itemId: itemId,
                name: link.name,
                type: link.type,
                url: link.url,
              }),
            )
            .pipe(
              catchError((response: unknown) => {
                this.#toastService.handleError(response);
                return of(null);
              }),
              map(() => null),
            ),
        );
      } else {
        promises.push(this.#itemsClient.deleteItemLink(new ItemLinkRequest({id: link.id})).pipe(map(() => null)));
      }
    }

    this.loadingNumber.set(true);
    forkJoin(promises).subscribe({
      complete: () => {
        this.loadingNumber.set(false);
      },
      next: () => this.linksResource.reload(),
    });
  }

  protected readonly errorMessage = errorMessage;
}
