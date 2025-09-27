import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {GalleryComponent} from '../../../gallery/gallery.component';

@Component({
  selector: 'app-persons-person-gallery',
  imports: [GalleryComponent, AsyncPipe],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);

  protected readonly identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    switchMap((identity) => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(identity);
    }),
  );

  protected readonly itemID$ = this.#route.parent!.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected pictureSelected(item: null | Picture) {
    if (item) {
      setTimeout(() => {
        this.#pageEnv.set({
          layout: {isGalleryPage: true},
          pageId: 34,
          title: item.nameText,
        });
      }, 0);
    }
  }
}
