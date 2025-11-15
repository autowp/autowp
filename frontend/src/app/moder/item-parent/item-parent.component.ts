import {AsyncPipe, UpperCasePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIGetItemParentLanguagesRequest,
  ItemFields,
  ItemParent,
  ItemParentLanguage,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemParentType,
  ItemRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {Empty} from '@ngx-grpc/well-known-types';
import {ContentLanguageService} from '@services/content-language';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {getItemTypeTranslation} from '@utils/translations';
import {BehaviorSubject, combineLatest, EMPTY, forkJoin, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';

interface LanguageItem {
  invalidParams: InvalidParams;
  language: string;
  name: null | string;
}

@Component({
  selector: 'app-moder-item-parent',
  imports: [RouterLink, FormsModule, InvalidParamsPipe, AsyncPipe, UpperCasePipe],
  templateUrl: './item-parent.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemParentComponent {
  readonly #contentLanguage = inject(ContentLanguageService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  protected readonly typeOptions: {name: string; value: ItemParentType}[] = [
    {
      name: $localize`Stock model`,
      value: ItemParentType.ITEM_TYPE_DEFAULT,
    },
    {
      name: $localize`Related`,
      value: ItemParentType.ITEM_TYPE_TUNING,
    },
    {
      name: $localize`Sport`,
      value: ItemParentType.ITEM_TYPE_SPORT,
    },
    {
      name: $localize`Design`,
      value: ItemParentType.ITEM_TYPE_DESIGN,
    },
  ];

  readonly #itemID$ = this.#route.paramMap.pipe(
    map((params) => parseInt(params.get('item_id') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(30),
  );

  readonly #parentID$ = this.#route.paramMap.pipe(
    map((params) => parseInt(params.get('parent_id') ?? '', 10)),
    distinctUntilChanged(),
    debounceTime(30),
  );

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly itemParent$ = combineLatest([this.#itemID$, this.#parentID$, this.#reload$]).pipe(
    switchMap(([itemID, parentID]) =>
      this.#itemsClient.getItemParent(
        new ItemParentsRequest({
          options: new ItemParentListOptions({
            itemId: '' + itemID,
            parentId: '' + parentID,
          }),
        }),
      ),
    ),
  );

  protected readonly item$ = this.#itemID$.pipe(
    switchMap((itemID) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id: '' + itemID,
          language: this.#languageService.language,
        }),
      ),
    ),
    tap((item) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: 78,
        title: getItemTypeTranslation(item.itemTypeId, 'name') + ': ' + item.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly parent$ = this.#parentID$.pipe(
    switchMap((parentID) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id: '' + parentID,
          language: this.#languageService.language,
        }),
      ),
    ),
  );

  protected readonly languages$: Observable<LanguageItem[]> = combineLatest([this.#itemID$, this.#parentID$]).pipe(
    switchMap(([itemID, parentID]) =>
      combineLatest([
        this.#contentLanguage.languages$,
        this.#itemsClient.getItemParentLanguages(
          new APIGetItemParentLanguagesRequest({
            itemId: '' + itemID,
            parentId: '' + parentID,
          }),
        ),
      ]),
    ),
    map(([languages, itemParentLanguage]) => {
      const resLanguages = languages.map(
        (language): LanguageItem => ({
          invalidParams: {},
          language,
          name: null,
        }),
      );

      for (const languageData of itemParentLanguage.items ? itemParentLanguage.items : []) {
        for (const i of resLanguages) {
          if (i.language === languageData.language) {
            i.name = languageData.name;
          }
        }
      }

      return resLanguages;
    }),
  );

  protected reloadItemParent() {
    this.#reload$.next();
  }

  protected save(itemParent: ItemParent, languages: LanguageItem[]) {
    const promises: Observable<Empty | void>[] = [
      this.#itemsClient.updateItemParent(
        new ItemParent({
          catname: itemParent.catname,
          itemId: itemParent.itemId,
          parentId: itemParent.parentId,
          type: itemParent.type,
        }),
      ),
    ];

    for (const language of languages) {
      language.invalidParams = {};
      promises.push(
        this.#itemsClient
          .setItemParentLanguage(
            new ItemParentLanguage({
              itemId: itemParent.itemId,
              language: language.language,
              name: language.name ?? undefined,
              parentId: itemParent.parentId,
            }),
          )
          .pipe(
            catchError((response: unknown) => {
              if (response instanceof GrpcStatusEvent) {
                const fieldViolations = extractFieldViolations(response);
                language.invalidParams = fieldViolations2InvalidParams(fieldViolations);
              } else {
                this.#toastService.handleError(response);
              }
              return EMPTY;
            }),
          ),
      );
    }

    forkJoin(promises).subscribe(() => {
      this.reloadItemParent();
    });
  }
}
