import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  ItemFields,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {map, of, switchMap} from 'rxjs';

import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {FactoryMapComponent} from './map/factory-map.component';

@Component({
  selector: 'app-factories',
  imports: [RouterLink, AsyncPipe, ThumbnailComponent, RemarkModule, FactoryMapComponent],
  templateUrl: './factories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class FactoryComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #auth = inject(AuthService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly #itemID = toSignal(this.#route.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  protected readonly itemResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `factory-item-${this.#itemID()}`,
    params: () => this.#itemID(),
    stream: ({params: id}) =>
      this.#itemsClient
        .item(
          new ItemRequest({
            fields: new ItemFields({
              description: true,
              fullText: true,
              location: true,
              nameHtml: true,
              nameText: true,
              relatedGroupPictures: true,
            }),
            id,
            language: this.#languageService.language,
          }),
        )
        .pipe(
          switchMap((factory) => (factory.itemTypeId === ItemType.ITEM_TYPE_FACTORY ? of(factory) : notFoundError())),
        ),
  });

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so downstream consumers below don't blow up on a non-NOT_FOUND
  // itemResource error (surfaced generically by the template instead).
  protected readonly itemData = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly picturesResource = rxResource({
    id: `factory-pictures-${this.#itemID()}`,
    params: () => this.itemData()?.id,
    stream: ({params: itemId}) =>
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
          limit: 24,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({itemId}),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          paginator: false,
        }),
      ),
  });

  // Same reasoning as itemData above - picturesResource has no inline error slot in the template,
  // so a transient error just leaves the pictures section empty instead of throwing.
  protected readonly picturesData = computed(() =>
    this.picturesResource.hasValue() ? this.picturesResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      if (isNotFoundError(this.itemResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const factory = this.itemData();
      if (factory) {
        this.#pageEnv.set({
          pageId: PageId.FACTORIES,
          title: factory.nameText,
        });
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
