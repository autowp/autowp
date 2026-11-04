import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ItemFields, ItemRequest, ItemType, RefreshInheritanceRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth-service';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {isNotFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, catchError, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {CarsSpecificationsEditorEngine} from './engine/cars-specifications-editor-engine';
import {CarsSpecificationsEditorResult} from './result/cars-specifications-editor-result';
import {CarsSpecificationsEditorSpec} from './spec/cars-specifications-editor-spec';

@Component({
  selector: 'app-cars-specifications-editor',
  imports: [
    RouterLink,
    CarsSpecificationsEditorEngine,
    CarsSpecificationsEditorSpec,
    CarsSpecificationsEditorResult,
    AsyncPipe,
    RemarkModule,
  ],
  templateUrl: './cars-specifications-editor.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsSpecificationsEditor {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
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
        this.#notFound.report();
      } else {
        this.#toastService.handleError(response);
      }
      return EMPTY;
    }),
    map((item) => {
      this.#pageEnv.set({
        pageId: PageId.SPECIFICATIONS_EDITOR,
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
