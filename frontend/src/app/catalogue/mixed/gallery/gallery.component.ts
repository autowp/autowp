import type {Item, Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map, of, switchMap} from 'rxjs';

import type {BrandPerspectivePageData} from '../../catalogue.module';

import {GalleryComponent} from '../../../gallery/gallery.component';

@Component({
  selector: 'app-catalogue-mixed-gallery',
  imports: [GalleryComponent],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMixedGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  // Static per-route config (mixed/other/logotypes each declare their own `data`), not a resolver
  // that changes without a fresh component instance, so requireSync is safe here.
  protected readonly data = toSignal(this.#route.data as Observable<BrandPerspectivePageData>, {requireSync: true});

  // Missing catname/identity, or a not-found brand, are all surfaced as a NOT_FOUND resource
  // error rather than an imperative Router.navigate() inside the stream — see the constructor
  // effect() below, which is the single place that navigates off this resource's error() signal.
  //
  // `id` is suffixed with data().catname (mixed/other/logotypes all share this component) and the
  // brand catname read once at construction time — see the identical note on
  // CatalogueMixedComponent.brandResource in ../mixed.component.ts.
  protected readonly brandResource = rxResource({
    id: `catalogue-mixed-gallery-brand-${this.data().catname}-${this.#catname() ?? ''}`,
    params: () => ({catname: this.#catname(), identity: this.identity()}),
    stream: ({params: {catname, identity}}): Observable<Item> => {
      if (!catname || !identity) {
        return notFoundError();
      }
      return this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameHtml: true,
              nameText: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
            }),
          }),
        )
        .pipe(
          switchMap((response) => {
            if (!response.items || response.items.length <= 0) {
              return notFoundError();
            }
            return of(response.items[0]);
          }),
        );
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
      }
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: this.data().picture_page.id,
        title: item.nameText,
      });
    }
  }

  protected readonly errorMessage = errorMessage;
}
