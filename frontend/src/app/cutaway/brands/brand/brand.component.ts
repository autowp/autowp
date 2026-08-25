import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-cutaway',
  imports: [RouterLink, PaginatorComponent, ThumbnailComponent],
  templateUrl: './brand.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class CutawayBrandsBrandComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand') ?? '')), {
    requireSync: true,
  });

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly brandResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `cutaway-brand-${this.#catname()}`,
    params: () => this.#catname(),
    stream: ({params: catname}) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
            }),
          }),
        )
        .pipe(switchMap((response) => (response.items?.length ? of(response.items[0]) : notFoundError()))),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so picturesResource's params() below doesn't blow up on a non-NOT_FOUND
  // brandResource error (surfaced generically by the template instead).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  protected readonly picturesResource = rxResource({
    id: `cutaway-brand-pictures-${this.#catname()}`,
    params: () => {
      const brand = this.brandData();

      return brand ? {brandID: brand.id, page: this.#page()} : undefined;
    },
    stream: ({params}) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 12,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: params.brandID}),
              perspectiveId: 9,
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_ACCEPT_DATETIME_DESC,
          page: params.page,
          paginator: true,
        }),
      ),
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });

    this.#pageEnv.set({pageId: 109});
  }

  protected readonly errorMessage = errorMessage;
}
