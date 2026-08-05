import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {ItemFields, ItemRequest, Picture} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {GalleryComponent} from 'app/gallery/gallery.component';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs/operators';

@Component({
  selector: 'app-twins-group-gallery',
  imports: [GalleryComponent],
  templateUrl: './twins-group-gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupGalleryComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #groupID = toSignal(this.#route.parent!.parent!.paramMap.pipe(map((route) => route.get('group'))), {
    requireSync: true,
  });

  protected readonly groupResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'twins-group-gallery',
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

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const group = this.groupResource.value();
      if (group) {
        this.#pageEnv.set({
          layout: {isGalleryPage: true},
          pageId: 28,
          title: group.nameText,
        });
      }
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 28,
        title: item.nameText,
      });
    }
  }
}
