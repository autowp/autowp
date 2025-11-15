import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {APIItem, ItemFields, ItemRequest, ItemType, RefreshInheritanceRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';
import {BehaviorSubject, EMPTY, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

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

  protected readonly data$: Observable<APIItem> = this.#route.queryParamMap.pipe(
    map((params) => params.get('item_id') ?? ''),
    distinctUntilChanged(),
    debounceTime(30),
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
    switchMap((item) => {
      if (!item) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      this.#pageEnv.set({
        pageId: 102,
        title: $localize`Specs editor of ${item.nameText}`,
      });
      return of(item);
    }),
  );

  protected onEngineChanged() {
    this.#change$.next();
  }

  protected refreshInheritance(item: APIItem) {
    this.#itemsClient.refreshInheritance(new RefreshInheritanceRequest({itemId: '' + item.id})).subscribe({
      error: (response: unknown) => this.#toastService.handleError(response),
      next: () => {
        this.#router.navigate(['/cars/specifications-editor'], {
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
