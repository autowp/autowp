import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {APIItem, ItemFields, ItemListOptions, ItemsRequest, Picture} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable, of} from 'rxjs';
import {debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {GalleryComponent} from '../../../gallery/gallery.component';
import {BrandPerspectivePageData} from '../../catalogue.module';

@Component({
  selector: 'app-catalogue-mixed-gallery',
  imports: [GalleryComponent, AsyncPipe],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMixedGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly identity$: Observable<string> = this.#route.paramMap.pipe(
    map((route) => route.get('identity') || ''),
    distinctUntilChanged(),
    debounceTime(10),
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

  protected readonly brand$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        return EMPTY;
      }
      return this.#itemsClient.list(
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
      );
    }),
    map((response) => (response.items?.length ? response.items[0] : null)),
    switchMap((brand) => {
      if (!brand) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(brand);
    }),
  );

  protected readonly data$ = this.#route.data as Observable<BrandPerspectivePageData>;

  protected pictureSelected(data: BrandPerspectivePageData, item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: data.picture_page.id,
        title: item.nameText,
      });
    }
  }
}
