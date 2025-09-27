import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  APIItemList,
  ItemFields,
  ItemListOptions,
  ItemParent,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemRequest,
  ItemsRequest,
  ItemType,
  Pages,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {perspectiveIDLogotype, perspectiveIDMixed} from '@services/picture';
import {combineLatest, EMPTY, forkJoin, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, startWith, switchMap, tap} from 'rxjs/operators';

import {chunk} from '../../chunk';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {UploadSelectTreeItemComponent} from './tree-item/tree-item.component';

@Component({
  selector: 'app-upload-select',
  imports: [FormsModule, RouterLink, PaginatorComponent, UploadSelectTreeItemComponent, AsyncPipe, ReactiveFormsModule],
  templateUrl: './select.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadSelectComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly search = new FormControl<string>('', {nonNullable: true});
  protected readonly loading = signal(false);
  protected readonly conceptsOpen = signal(false);

  protected readonly data$: Observable<{
    brand:
      | undefined
      | {
          concepts: ItemParent[];
          engines: ItemParent[];
          item: APIItem;
          vehicles: ItemParent[];
        };
    brands: APIItem[][] | undefined;
    paginator: Pages | undefined;
  }> = combineLatest([
    this.search.valueChanges.pipe(
      startWith(''),
      map((value) => value.trim()),
      distinctUntilChanged(),
      debounceTime(50),
    ),
    this.#route.queryParamMap.pipe(
      map((params) => ({
        brandId: params.get('brand_id'),
        page: parseInt(params.get('page') ?? '', 10),
      })),
    ),
  ]).pipe(
    map(([search, query]) => ({brandId: query.brandId, page: query.page, search})),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    tap(() => this.loading.set(true)),
    switchMap((params) => {
      const brandId = params.brandId;
      const page = params.page;

      return forkJoin([
        brandId ? this.brandObservable$(brandId) : of(undefined),
        brandId ? of(undefined) : this.brandsObservable$(page, params.search),
      ]);
    }),
    map(([brand, brands]) => ({
      brand,
      brands: chunk(brands?.items ?? [], 6),
      paginator: brands?.paginator,
    })),
    tap(() => this.loading.set(false)),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 30}), 0);
  }

  private brandsObservable$(page: number, search: string): Observable<APIItemList> {
    return this.#itemsClient
      .list(
        new ItemsRequest({
          fields: new ItemFields({
            nameOnly: true,
          }),
          language: this.#languageService.language,
          limit: 500,
          options: new ItemListOptions({
            name: search ? '%' + search + '%' : undefined,
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
          order: ItemsRequest.Order.NAME,
          page,
        }),
      )
      .pipe(
        catchError((err: unknown) => {
          this.#toastService.handleError(err);
          return EMPTY;
        }),
      );
  }

  private brandObservable$(brandId: string): Observable<{
    concepts: ItemParent[];
    engines: ItemParent[];
    item: APIItem;
    vehicles: ItemParent[];
  }> {
    return this.#itemsClient.item(new ItemRequest({id: brandId, language: this.#languageService.language})).pipe(
      catchError(() => {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }),
      switchMap((item) => this.brandItemsObservable(item)),
      map(([item, vehicles, engines, concepts]) => ({concepts, engines, item, vehicles})),
    );
  }

  private brandItemsObservable(item: APIItem) {
    return forkJoin([
      of(item),
      this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                childsCount: true,
                nameHtml: true,
              }),
            }),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemParentListOptions({
              item: new ItemListOptions({
                isNotConcept: true,
                typeId: ItemType.ITEM_TYPE_VEHICLE,
              }),
              parentId: item.id,
            }),
            order: ItemParentsRequest.Order.AUTO,
          }),
        )
        .pipe(
          map((response) => response.items || []),
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
      this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                childsCount: true,
                nameHtml: true,
              }),
            }),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemParentListOptions({
              item: new ItemListOptions({
                isNotConcept: true,
                typeId: ItemType.ITEM_TYPE_ENGINE,
              }),
              parentId: item.id,
            }),
            order: ItemParentsRequest.Order.AUTO,
          }),
        )
        .pipe(
          map((response) => response.items || []),
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
      this.#itemsClient
        .getItemParents(
          new ItemParentsRequest({
            fields: new ItemParentFields({
              item: new ItemFields({
                childsCount: true,
                nameHtml: true,
              }),
            }),
            language: this.#languageService.language,
            limit: 500,
            options: new ItemParentListOptions({
              item: new ItemListOptions({
                isConcept: true,
              }),
              parentId: item.id,
            }),
            order: ItemParentsRequest.Order.AUTO,
          }),
        )
        .pipe(
          map((response) => response.items || []),
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
    ]);
  }

  protected readonly perspectiveIDLogotype = perspectiveIDLogotype;
  protected readonly perspectiveIDMixed = perspectiveIDMixed;
}
