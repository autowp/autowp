import type {Picture} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, effect, inject, input} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {PictureItemType} from '@grpc/spec.pb';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {Gallery} from 'app/gallery/gallery';
import {map} from 'rxjs';

@Component({
  selector: 'app-persons-person-author-gallery',
  imports: [Gallery],
  templateUrl: './persons-person-author-gallery.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonAuthorGallery {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);

  protected picturesRouterLink: string[] = [];

  readonly identity = input<null | string, RouteParam>(null, {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? null,
  });

  protected readonly itemID = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('id') ?? '')),
    {requireSync: true},
  );

  protected readonly PictureItemType = PictureItemType;

  constructor() {
    effect(() => {
      if (!this.identity()) {
        this.#notFound.report();
      }
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.PICTURES,
        title: item.nameText,
      });
    }
  }
}
