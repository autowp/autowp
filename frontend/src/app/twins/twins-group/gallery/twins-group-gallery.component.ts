import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemFields, ItemRequest, Picture} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, switchMap, tap} from 'rxjs/operators';

import {GalleryComponent} from '../../../gallery/gallery.component';

@Component({
  selector: 'app-twins-group-gallery',
  imports: [GalleryComponent, AsyncPipe],
  templateUrl: './twins-group-gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupGalleryComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly group$: Observable<APIItem | null> = this.#route.parent!.parent!.paramMap.pipe(
    map((route) => route.get('group')),
    distinctUntilChanged(),
    switchMap((groupID) => {
      if (!groupID) {
        return of(null);
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
    }),
    switchMap((group) => {
      if (!group) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(group);
    }),
    tap((group) => {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: 28,
        title: group ? group.nameText : '',
      });
    }),
  );

  protected readonly identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
  );

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
