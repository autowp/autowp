import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PicturesRequest,
  PreviewPicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY} from 'rxjs';
import {catchError, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {ItemComponent} from '../../item/item/item.component';
import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-cars-deteless',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, ItemComponent],
  templateUrl: './dateless.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsDatelessComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  protected readonly data$ = this.#route.queryParamMap.pipe(
    map((params) => parseInt(params.get('page') ?? '', 10)),
    distinctUntilChanged(),
    switchMap((page) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            canEditSpecs: true,
            categories: new ItemsRequest({
              fields: new ItemFields({nameHtml: true}),
            }),
            childsCount: true,
            description: true,
            design: true,
            engineVehicles: new ItemsRequest({
              fields: new ItemFields({nameHtml: true, route: true}),
            }),
            hasText: true,
            nameDefault: true,
            nameHtml: true,
            previewPictures: new PreviewPicturesRequest({
              pictures: new PicturesRequest({
                options: new PictureListOptions({
                  pictureItem: new PictureItemListOptions({typeId: PictureItemType.PICTURE_ITEM_CONTENT}),
                }),
              }),
            }),
            twins: new ItemsRequest(),
          }),
          language: this.#languageService.language,
          limit: 10,
          options: new ItemListOptions({
            dateless: true,
          }),
          order: ItemsRequest.Order.AGE,
          page,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 1}), 0);
  }
}
