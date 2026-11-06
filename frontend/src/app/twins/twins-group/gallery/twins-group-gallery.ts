import type {Picture} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, effect, inject, input} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {ItemFields, ItemRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {Gallery} from 'app/gallery/gallery';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

@Component({
  selector: 'app-twins-group-gallery',
  imports: [Gallery],
  templateUrl: './twins-group-gallery.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupGallery {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #notFound = inject(NotFoundService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #groupID = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((route) => route.get('group'))),
    {
      requireSync: true,
    },
  );

  protected readonly groupResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the group id read once at construction time - see the identical note on
    // TwinsGroup.groupResource in ../twins-group.ts.
    id: `twins-group-gallery-${this.#groupID() ?? ''}`,
    params: () => this.#groupID(),
    stream: ({params: groupID}) => {
      if (!groupID) {
        return notFoundError();
      }
      return this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          id: groupID,
          language: this.#languageService.language,
        }),
      );
    },
  });

  readonly identity = input<null | string, RouteParam>(null, {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? null,
  });

  protected readonly errorMessage = errorMessage;

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        this.#notFound.report();
        return;
      }

      // resource.value() throws while its resource is in an error state - hasValue() is the
      // reactive guard against that, so a non-NOT_FOUND error (surfaced generically by the
      // template instead) doesn't blow up this effect.
      if (!this.groupResource.hasValue()) {
        return;
      }

      const group = this.groupResource.value();
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.TWINS_GROUP_PICTURES,
        title: group.nameText,
      });
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.TWINS_GROUP_PICTURES,
        title: item.nameText,
      });
    }
  }
}
