import type {OnInit} from '@angular/core';
import type {Picture} from '@grpc/spec.pb';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';

import {Gallery} from './gallery';

@Component({
  selector: 'app-gallery-page',
  imports: [Gallery],
  templateUrl: './gallery-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GalleryPage implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  readonly identity = input<null | string, RouteParam>(null, {
    transform: (value: RouteParam) => (Array.isArray(value) ? value[0] : value) ?? null,
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isGalleryPage: true},
      pageId: PageId.PICTURE,
      title: '', // data.picture.name_text,
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.PICTURE,
        title: item.nameText,
      });
    }
  }
}
