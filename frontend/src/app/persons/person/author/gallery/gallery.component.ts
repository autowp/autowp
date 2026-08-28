import type {Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {PictureItemType} from '@grpc/spec.pb';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {requireRouteParent} from '@utils/require-route-parent';
import {GalleryComponent} from 'app/gallery/gallery.component';
import {map} from 'rxjs';

@Component({
  selector: 'app-persons-person-author-gallery',
  imports: [GalleryComponent],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonAuthorGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #notFound = inject(NotFoundService);

  protected picturesRouterLink: string[] = [];

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  protected readonly itemID = toSignal(
    requireRouteParent(requireRouteParent(this.#route)).paramMap.pipe(map((params) => params.get('id') ?? '')),
    {requireSync: true},
  );

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

  protected readonly PictureItemType = PictureItemType;
}
