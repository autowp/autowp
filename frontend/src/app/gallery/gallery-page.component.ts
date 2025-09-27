import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {distinctUntilChanged, map} from 'rxjs/operators';

import {GalleryComponent} from './gallery.component';

@Component({
  selector: 'app-gallery-page',
  imports: [GalleryComponent, AsyncPipe],
  templateUrl: './gallery-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GalleryPageComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
  );

  ngOnInit(): void {
    setTimeout(() => {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 187,
        title: '', // data.picture.name_text,
      });
    }, 0);
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
