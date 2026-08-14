import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
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
      pageId: 187,
      title: '', // data.picture.name_text,
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 187,
        title: item.nameText,
      });
    }
  }
}
