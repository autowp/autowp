import type {OnInit} from '@angular/core';
import type {Picture} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {map} from 'rxjs';

import {GalleryComponent} from './gallery.component';

@Component({
  selector: 'app-gallery-page',
  imports: [GalleryComponent],
  templateUrl: './gallery-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GalleryPageComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
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
