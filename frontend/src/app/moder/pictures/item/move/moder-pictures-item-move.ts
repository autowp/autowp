import type {OnInit} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {Item, ItemParent, Pages} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {form, FormField} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CreatePictureItemRequest,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemParentFields,
  ItemParentListOptions,
  ItemParentsRequest,
  ItemsRequest,
  ItemType,
  PictureItem,
  PictureItemType,
  SetPictureItemItemIDRequest,
  UpdatePictureItemRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {browserWindow} from '@utils/browser-window';
import {
  catchError,
  combineLatest,
  debounceTime,
  distinctUntilChanged,
  EMPTY,
  map,
  of,
  shareReplay,
  switchMap,
} from 'rxjs';

import {chunk} from '../../../../chunk';
import {Paginator} from '../../../../paginator/paginator/paginator';
import {ToastsService} from '../../../../toasts/toasts.service';
import {ModerPictureMoveItem} from './item/moder-picture-move-item';

export interface PictureItemMoveSelection {
  itemId: string;
  perspectiveId: number;
  type: PictureItemType;
}

interface HtmlAndSelectItemParams {
  html: string;
  selection: SelectItemParams;
}

interface SelectItemParams {
  selection: PictureItemMoveSelection;
  src: SrcSelection;
}

interface SrcSelection {
  id: number;
  srcItemID: number;
  srcType: PictureItemType;
}

@Component({
  selector: 'app-moder-pictures-item-move',
  imports: [RouterLink, Paginator, FormField, ModerPictureMoveItem, AsyncPipe],
  templateUrl: './moder-pictures-item-move.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemMove implements OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #window = browserWindow();

  protected readonly conceptsExpanded = signal(false);

  readonly #srcItemID$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('src_item_id') ?? '', 10)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #srcType$: Observable<PictureItemType> = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('src_type') ?? '', 10)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly id$ = this.#route.paramMap.pipe(
    map((params) => parseInt(params.get('id') ?? '', 10)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly src$ = combineLatest([this.id$, this.#srcItemID$, this.#srcType$]).pipe(
    map(([id, srcItemID, srcType]) => ({id, srcItemID, srcType})),
  );

  readonly #isContentTypeOrEmpty$ = this.isSrcTypeOrEmpty$(PictureItemType.PICTURE_ITEM_CONTENT);
  protected readonly showBrands$ = this.#isContentTypeOrEmpty$;
  protected readonly showFactories$ = this.#isContentTypeOrEmpty$;
  protected readonly showMuseums$ = this.#isContentTypeOrEmpty$;
  protected readonly showPersons$ = this.#isContentTypeOrEmpty$;
  protected readonly showAuthors$ = this.isSrcTypeOrEmpty$(PictureItemType.PICTURE_ITEM_AUTHOR);
  protected readonly showCopyrights$ = this.isSrcTypeOrEmpty$(PictureItemType.PICTURE_ITEM_COPYRIGHTS);

  protected readonly showTabBar$ = combineLatest([
    this.showBrands$,
    this.showFactories$,
    this.showMuseums$,
    this.showPersons$,
    this.showAuthors$,
    this.showCopyrights$,
  ]).pipe(
    map(
      ([showBrands, showFactories, showMuseums, showPersons, showAuthors, showCopyrights]) =>
        showBrands && showFactories && showMuseums && showPersons && showAuthors && showCopyrights,
    ),
  );

  protected readonly factoriesActive$ = this.tabActive$('show_factories', PictureItemType.PICTURE_ITEM_CONTENT);
  protected readonly museumsActive$ = this.tabActive$('show_museums', PictureItemType.PICTURE_ITEM_CONTENT);
  protected readonly personsActive$ = this.tabActive$('show_persons', PictureItemType.PICTURE_ITEM_CONTENT);

  protected readonly authorsActive$ = combineLatest([
    this.showAuthors$,
    this.src$,
    this.#route.queryParamMap.pipe(
      map((params) => !!params.get('show_authors')),
      distinctUntilChanged(),
    ),
  ]).pipe(
    map(
      ([showAuthors, {srcItemID, srcType}, active]) =>
        showAuthors && !srcItemID && (active || srcType === PictureItemType.PICTURE_ITEM_AUTHOR),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly copyrightsActive$ = this.tabActive$('show_copyrights', PictureItemType.PICTURE_ITEM_COPYRIGHTS);

  protected readonly brandsActive$ = combineLatest([
    this.isSrcTypeOrEmpty$(PictureItemType.PICTURE_ITEM_CONTENT),
    this.factoriesActive$,
    this.museumsActive$,
    this.personsActive$,
    this.authorsActive$,
    this.copyrightsActive$,
  ]).pipe(
    map(
      ([srcTypeIsContentOrEmpty, showFactories, showMuseums, showPersons, showAuthors, showCopyrights]) =>
        srcTypeIsContentOrEmpty && !showFactories && !showMuseums && !showPersons && !showAuthors && !showCopyrights,
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly searchBrandControl = form(signal(''));
  protected readonly searchPersonControl = form(signal(''));
  protected readonly searchAuthorControl = form(signal(''));

  readonly #page$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly brandId$ = this.#route.queryParamMap.pipe(
    map((params) => params.get('brand_id')),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly museums$ = this.#page$.pipe(
    switchMap((page) =>
      this.getItems$(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 50,
          options: new ItemListOptions({
            typeId: ItemType.ITEM_TYPE_MUSEUM,
          }),
          page,
        }),
        PictureItemType.PICTURE_ITEM_CONTENT,
      ),
    ),
  );

  protected readonly factories$ = this.#page$.pipe(
    switchMap((page) =>
      this.getItems$(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 50,
          options: new ItemListOptions({
            typeId: ItemType.ITEM_TYPE_FACTORY,
          }),
          page,
        }),
        PictureItemType.PICTURE_ITEM_CONTENT,
      ),
    ),
  );

  protected readonly persons$: Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> =
    this.authorsAndPersons$(this.searchPersonControl, PictureItemType.PICTURE_ITEM_CONTENT);

  protected readonly authors$: Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> =
    this.authorsAndPersons$(this.searchAuthorControl, PictureItemType.PICTURE_ITEM_AUTHOR);

  protected readonly copyrights$: Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> = this.#page$.pipe(
    switchMap((page) =>
      this.getItems$(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 50,
          options: new ItemListOptions({
            typeId: ItemType.ITEM_TYPE_COPYRIGHT,
          }),
          page,
        }),
        PictureItemType.PICTURE_ITEM_COPYRIGHTS,
      ),
    ),
  );

  protected readonly vehicles$: Observable<ItemParent[]> = this.brandId$.pipe(
    switchMap((brandID) =>
      this.#itemsClient.getItemParents(
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
              typeId: ItemType.ITEM_TYPE_VEHICLE,
            }),
            // brandID comes straight from params.get('brand_id') (no `?? ''`) - a literal
            // `?brand_id=` query value still yields '', which must be treated the same as "no
            // filter" as null is, not passed through as a real (nonexistent) empty brand id.
            // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing
            parentId: brandID ? brandID : undefined,
          }),
          order: ItemParentsRequest.Order.NAME,
        }),
      ),
    ),
    map((response) => response.items ?? []),
  );

  protected readonly engines$: Observable<ItemParent[]> = this.brandId$.pipe(
    switchMap((brandID) =>
      this.#itemsClient.getItemParents(
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
              typeId: ItemType.ITEM_TYPE_ENGINE,
            }),
            // brandID comes straight from params.get('brand_id') (no `?? ''`) - a literal
            // `?brand_id=` query value still yields '', which must be treated the same as "no
            // filter" as null is, not passed through as a real (nonexistent) empty brand id.
            // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing
            parentId: brandID ? brandID : undefined,
          }),
          order: ItemParentsRequest.Order.NAME,
        }),
      ),
    ),
    map((response) => response.items ?? []),
  );

  protected readonly concepts$: Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> = this.brandId$.pipe(
    switchMap((brandID) =>
      this.getItems$(
        new ItemsRequest({
          fields: new ItemFields({
            nameHtml: true,
          }),
          language: this.#languageService.language,
          limit: 500,
          options: new ItemListOptions({
            ancestor: new ItemParentCacheListOptions({
              // brandID comes straight from params.get('brand_id') (no `?? ''`) - a literal
              // `?brand_id=` query value still yields '', which must be treated the same as "no
              // filter" as null is, not passed through as a real (nonexistent) empty brand id.
              // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing
              parentId: brandID ? brandID : undefined,
            }),
            isConcept: true,
            typeId: ItemType.ITEM_TYPE_VEHICLE,
          }),
          page: 1,
        }),
        PictureItemType.PICTURE_ITEM_COPYRIGHTS,
      ),
    ),
  );

  protected readonly brands$: Observable<{items: Item[][]; paginator?: Pages}> = combineLatest([
    this.#page$,
    toObservable(this.searchBrandControl().value).pipe(distinctUntilChanged(), debounceTime(30)),
  ]).pipe(
    switchMap(([page, search]) => {
      const trimmed = search.trim();

      return this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({nameHtml: true}),
          language: this.#languageService.language,
          limit: 200,
          options: new ItemListOptions({
            name: trimmed ? '%' + trimmed + '%' : undefined,
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
          page,
        }),
      );
    }),
    map((response) => ({
      items: chunk<Item>(response.items ?? [], 6),
      paginator: response.paginator,
    })),
  );

  protected readonly PictureItemType = PictureItemType;

  private isSrcTypeOrEmpty$(pictureItemType: PictureItemType) {
    return this.#srcType$.pipe(
      map((srcType) => !srcType || srcType === pictureItemType),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }

  private tabActive$(param: string, pictureItemType: PictureItemType) {
    return combineLatest([
      this.isSrcTypeOrEmpty$(pictureItemType),
      this.#srcItemID$,
      this.#route.queryParamMap.pipe(
        map((params) => !!params.get(param)),
        distinctUntilChanged(),
      ),
    ]).pipe(
      map(([srcTypeIsContentOrEmpty, srcItemID, active]) => srcTypeIsContentOrEmpty && active && !srcItemID),
      shareReplay({bufferSize: 1, refCount: false}),
    );
  }

  private getItems$(
    request: ItemsRequest,
    selectionType: PictureItemType,
  ): Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> {
    return combineLatest([this.src$, this.#itemsClient.list(new ItemsRequest(request))]).pipe(
      map(([src, {items, paginator}]) => ({
        items: (items ?? []).map((item) => ({
          html: item.nameHtml,
          selection: {
            selection: {
              itemId: item.id,
              perspectiveId: 0,
              type: selectionType,
            },
            src,
          },
        })),
        paginator,
      })),
    );
  }

  private authorsAndPersons$(
    searchControl: FieldTree<string>,
    selectionType: PictureItemType,
  ): Observable<{items: HtmlAndSelectItemParams[]; paginator?: Pages}> {
    return combineLatest([
      this.#page$,
      toObservable(searchControl().value).pipe(distinctUntilChanged(), debounceTime(30)),
    ]).pipe(
      switchMap(([page, search]) => {
        const trimmed = search.trim();

        return this.getItems$(
          new ItemsRequest({
            fields: new ItemFields({nameHtml: true}),
            language: this.#languageService.language,
            limit: 50,
            options: new ItemListOptions({
              name: trimmed ? '%' + trimmed + '%' : undefined,
              typeId: ItemType.ITEM_TYPE_PERSON,
            }),
            page,
          }),
          selectionType,
        );
      }),
    );
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_PICTURE_MOVE,
    });
  }

  protected selectItem({selection, src: {id, srcItemID, srcType}}: SelectItemParams) {
    const dstItemID = selection.itemId;
    const dstPerspectiveID = selection.perspectiveId;

    if (srcItemID && srcType) {
      this.#picturesClient
        .setPictureItemItemID(
          new SetPictureItemItemIDRequest({
            itemId: '' + srcItemID,
            newItemId: dstItemID,
            pictureId: '' + id,
            type: srcType,
          }),
        )
        .pipe(
          switchMap(() => {
            if (!dstPerspectiveID) {
              return of(null);
            }

            return this.#picturesClient.updatePictureItem(
              new UpdatePictureItemRequest({
                pictureItem: new PictureItem({
                  itemId: dstItemID,
                  perspectiveId: dstPerspectiveID || undefined,
                  pictureId: '' + id,
                  type: srcType,
                }),
                updateMask: new FieldMask({paths: ['perspective_id']}),
              }),
            );
          }),
          catchError((error: unknown) => {
            this.#toastService.handleError(error);
            return EMPTY;
          }),
        )
        .subscribe(() => {
          this.#window?.localStorage.setItem('last_item', dstItemID);
          void this.#router.navigate(['/moder/pictures', id]);
        });
    } else {
      this.#picturesClient
        .createPictureItem(
          new CreatePictureItemRequest({
            itemId: dstItemID,
            perspectiveId: dstPerspectiveID || undefined,
            pictureId: '' + id,
            type: selection.type,
          }),
        )
        .pipe(
          catchError((error: unknown) => {
            this.#toastService.handleError(error);
            return EMPTY;
          }),
        )
        .subscribe(() => {
          this.#window?.localStorage.setItem('last_item', dstItemID);
          void this.#router.navigate(['/moder/pictures', id]);
        });
    }

    return false;
  }

  protected toggleConcepts() {
    this.conceptsExpanded.set(!this.conceptsExpanded());
    return false;
  }
}
