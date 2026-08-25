import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ItemFields, ItemRequest, ItemType, RefreshInheritanceRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, catchError, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {CarsSpecificationsEditorEngineComponent} from './engine/engine.component';
import {CarsSpecificationsEditorResultComponent} from './result/result.component';
import {CarsSpecificationsEditorSpecComponent} from './spec/spec.component';

@Component({
  selector: 'app-cars-specifications-editor',
  imports: [
    RouterLink,
    CarsSpecificationsEditorEngineComponent,
    CarsSpecificationsEditorSpecComponent,
    CarsSpecificationsEditorResultComponent,
    AsyncPipe,
    RemarkModule,
  ],
  templateUrl: './specifications-editor.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CarsSpecificationsEditorComponent {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #auth = inject(AuthService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #change$ = new BehaviorSubject<void>(void 0);
  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly isSpecsAdmin$ = this.#auth.hasRole$(Role.ADMIN);
  protected readonly tab$ = this.#route.queryParamMap.pipe(map((params) => params.get('tab') ?? 'info'));
  protected readonly user$ = this.#auth.user$;

  protected readonly data$: Observable<Item> = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
    switchMap((itemID) =>
      this.#change$.pipe(
        switchMap(() =>
          this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({
                attrZoneId: true,
                nameHtml: true,
                nameText: true,
              }),
              id: itemID,
              language: this.#languageService.language,
            }),
          ),
        ),
      ),
    ),
    catchError((response: unknown) => {
      if (isNotFoundError(response)) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      } else {
        this.#toastService.handleError(response);
      }
      return EMPTY;
    }),
    map((item) => {
      this.#pageEnv.set({
        pageId: 102,
        title: $localize`Specs editor of ${item.nameText}`,
      });
      return item;
    }),
  );

  protected onEngineChanged() {
    this.#change$.next();
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

  protected readonly ItemType = ItemType;
}
