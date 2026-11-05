import type {WritableSignal} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {InvalidParams} from '@utils/invalid-params.pipe';

import {UpperCasePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, Injector, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {applyEach, disabled, form, FormField, maxLength, submit} from '@angular/forms/signals';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  GetItemParentLanguagesRequest,
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
import {ContentLanguageService} from '@services/content-language';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {numericEnumValue} from '@utils/numeric-enum-value';
import {getItemTypeTranslation} from '@utils/translations';
import {combineLatest, defer, distinctUntilChanged, firstValueFrom, map, tap} from 'rxjs';

import {extractFieldViolations, fieldViolations2InvalidParams} from '../../grpc';
import {ToastsService} from '../../toasts/toasts.service';
interface ParentFormValue {
  catname: string;
  itemId: string;
  languages: {language: string; name: string}[];
  parentId: string;
  type: string;
}

interface ParentEditor {
  errors: WritableSignal<Record<string, InvalidParams>>;
  form: FieldTree<ParentFormValue>;
}

@Component({
  selector: 'app-moder-item-parent',
  imports: [RouterLink, FormField, InvalidParamsPipe, UpperCasePipe],
  templateUrl: './moder-item-parent.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerItemParent {
  readonly #injector = inject(Injector);
  readonly #contentLanguage = inject(ContentLanguageService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  readonly #routeParams = toSignal(this.#route.paramMap, {requireSync: true});

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

  readonly #itemID = toSignal(
    this.#route.paramMap.pipe(
      map((params) => parseInt(params.get('item_id') ?? '', 10)),
      distinctUntilChanged(),
    ),
    {requireSync: true},
  );

  readonly #parentID = toSignal(
    this.#route.paramMap.pipe(
      map((params) => parseInt(params.get('parent_id') ?? '', 10)),
      distinctUntilChanged(),
    ),
    {requireSync: true},
  );

  protected readonly itemResource = rxResource({
    params: () => this.#itemID(),
    stream: ({params: itemID}) =>
      defer(() =>
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
      ).pipe(
        tap((item) => {
          this.#pageEnv.set({
            layout: {isAdminPage: true},
            pageId: PageId.MODER_ITEM,
            title: getItemTypeTranslation(item.itemTypeId, 'name') + ': ' + item.nameText,
          });
        }),
      ),
  });
  protected readonly item = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly parentResource = rxResource({
    params: () => this.#parentID(),
    stream: ({params: parentID}) =>
      defer(() =>
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
  });
  protected readonly parent = computed(() =>
    this.parentResource.hasValue() ? this.parentResource.value() : undefined,
  );

  protected readonly editorResource = rxResource({
    params: () => this.#routeParams(),
    stream: ({params}) =>
      defer(() => {
        const itemId = params.get('item_id') ?? '0';
        const parentId = params.get('parent_id') ?? '0';
        return combineLatest([
          this.#contentLanguage.languages$,
          this.#itemsClient.getItemParent(
            new ItemParentsRequest({options: new ItemParentListOptions({itemId, parentId})}),
          ),
          this.#itemsClient.getItemParentLanguages(new GetItemParentLanguagesRequest({itemId, parentId})),
        ]).pipe(
          map(
            ([languages, parent, translations]) =>
              [
                parent,
                languages.map((language) => ({
                  language,
                  name: translations.items?.find((row) => row.language === language)?.name ?? '',
                })),
              ] as const,
          ),
        );
      }).pipe(
        map(([parent, languages]) => ({
          errors: signal<Record<string, InvalidParams>>({}),
          form: form(
            signal<ParentFormValue>({
              catname: parent.catname,
              itemId: parent.itemId,
              parentId: parent.parentId,
              type: `${parent.type}`,
              languages,
            }),
            (path) => {
              maxLength(path.catname, 150);
              applyEach(path.languages, (language) => {
                maxLength(language.name, 255);
              });
              disabled(path, {when: ({state}) => state.submitting()});
            },
            {injector: this.#injector},
          ),
        })),
      ),
  });
  protected readonly editor = computed(() =>
    this.editorResource.hasValue() ? this.editorResource.value() : undefined,
  );

  protected reloadItemParent() {
    this.editorResource.reload();
  }

  protected save(editor: ParentEditor): void {
    void submit(editor.form, async () => {
      const value = editor.form().value();
      editor.errors.set({});
      const operations = value.languages.map(async (language) => {
        try {
          await firstValueFrom(
            this.#itemsClient.setItemParentLanguage(
              new ItemParentLanguage({
                itemId: value.itemId,
                parentId: value.parentId,
                language: language.language,
                name: language.name,
              }),
            ),
          );
          return true;
        } catch (response: unknown) {
          if (response instanceof GrpcStatusEvent) {
            editor.errors.update((errors) => ({
              ...errors,
              [language.language]: fieldViolations2InvalidParams(extractFieldViolations(response)),
            }));
          } else {
            this.#toastService.handleError(response);
          }
          return false;
        }
      });
      operations.push(
        (async () => {
          try {
            await firstValueFrom(
              this.#itemsClient.updateItemParent(
                new ItemParent({
                  catname: value.catname,
                  itemId: value.itemId,
                  parentId: value.parentId,
                  type: numericEnumValue(ItemParentType, Number(value.type), ItemParentType.ITEM_TYPE_DEFAULT),
                }),
              ),
            );
            return true;
          } catch (response: unknown) {
            this.#toastService.handleError(response);
            return false;
          }
        })(),
      );
      const results = await Promise.all(operations);
      if (results.every(Boolean)) {
        this.reloadItemParent();
      }
    });
  }
}
