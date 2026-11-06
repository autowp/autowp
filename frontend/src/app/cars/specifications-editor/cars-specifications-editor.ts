import type {Item} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {Router, RouterLink} from '@angular/router';
import {ItemFields, ItemRequest, ItemType, RefreshInheritanceRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {isNotFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {tap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {CarsSpecificationsEditorEngine} from './engine/cars-specifications-editor-engine';
import {CarsSpecificationsEditorResult} from './result/cars-specifications-editor-result';
import {CarsSpecificationsEditorSpec} from './spec/cars-specifications-editor-spec';
@Component({
  selector: 'app-cars-specifications-editor',
  imports: [
    ResourceStatus,
    RouterLink,
    CarsSpecificationsEditorEngine,
    CarsSpecificationsEditorSpec,
    CarsSpecificationsEditorResult,
    RemarkModule,
  ],
  templateUrl: './cars-specifications-editor.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditor {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly isModer = this.#auth.hasRole(Role.MODER);
  protected readonly isSpecsAdmin = this.#auth.hasRole(Role.ADMIN);
  readonly tab = input('info', {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? 'info',
  });
  protected readonly user = this.#auth.user;

  readonly itemId = input('', {
    // eslint-disable-next-line @angular-eslint/no-input-rename -- Preserve the public query parameter name.
    alias: 'item_id',
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? '',
  });
  protected readonly dataResource = rxResource({
    params: () => this.itemId(),
    stream: ({params: id}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({attrZoneId: true, nameHtml: true, nameText: true}),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          tap({
            next: (item) => {
              this.#pageEnv.set({
                pageId: PageId.SPECIFICATIONS_EDITOR,
                title: $localize`Specs editor of ${item.nameText}`,
              });
            },
            error: (error: unknown) => {
              if (isNotFoundError(error)) this.#notFound.report();
            },
          }),
        ),
  });
  protected readonly data = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  protected readonly ItemType = ItemType;

  protected onEngineChanged() {
    this.dataResource.reload();
  }

  protected refreshInheritance(item: Item) {
    this.#itemsClient.refreshInheritance(new RefreshInheritanceRequest({itemId: item.id})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: () => {
        void this.#router.navigate(['/cars/specifications-editor'], {
          queryParams: {
            item_id: item.id,
            tab: 'admin',
          },
        });
      },
    });
  }
}
