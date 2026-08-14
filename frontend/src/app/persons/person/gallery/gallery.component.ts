import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {Picture, PictureItemType} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {GalleryComponent} from 'app/gallery/gallery.component';
import {map} from 'rxjs';

@Component({
  selector: 'app-persons-person-gallery',
  imports: [GalleryComponent],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  protected readonly itemID = toSignal(this.#route.parent!.paramMap.pipe(map((params) => params.get('id') ?? '')), {
    requireSync: true,
  });

  constructor() {
    effect(() => {
      if (!this.identity()) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 34,
        title: item.nameText,
      });
    }
  }

  protected readonly PictureItemType = PictureItemType;
}
