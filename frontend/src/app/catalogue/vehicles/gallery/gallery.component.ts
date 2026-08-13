import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {Item, ItemParent, Picture} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError} from 'app/grpc';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

import {APIGalleryFilter, GalleryComponent} from '../../../gallery/gallery.component';
import {CatalogueService} from '../../catalogue-service';

@Component({
  selector: 'app-catalogue-vehicles-gallery',
  imports: [GalleryComponent],
  templateUrl: './gallery.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesGalleryComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogueService = inject(CatalogueService);
  readonly #router = inject(Router);

  protected readonly identity = toSignal(this.#route.paramMap.pipe(map((route) => route.get('identity'))), {
    requireSync: true,
  });

  readonly #exact = toSignal(this.#route.data.pipe(map((data) => !!data['exact'])), {requireSync: true});

  readonly #catname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });
  readonly #pathParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('path'))), {
    requireSync: true,
  });
  readonly #typeParam = toSignal(this.#route.paramMap.pipe(map((params) => params.get('type'))), {
    requireSync: true,
  });

  // Missing/unresolvable brand or path segments are surfaced by resolveCatalogue$ itself as a
  // NOT_FOUND resource error - see the constructor effect() below, which is the single place that
  // navigates off this resource's error() signal.
  //
  // `id` is suffixed with the brand/path/type route params read once at construction time - see
  // the identical note on CatalogueVehiclesComponent.catalogueResource in ../vehicles.component.ts.
  protected readonly catalogueResource = rxResource({
    id: `catalogue-vehicles-gallery-catalogue-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    stream: (): Observable<{brand: Item; path: ItemParent[]; type: string}> =>
      this.#catalogueService.resolveCatalogue$(this.#route),
  });

  readonly #routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueResource.value();
    if (!data) {
      return undefined;
    }

    return ['/', data.brand.catname, ...data.path.map((node) => node.catname), ...(this.#exact() ? ['exact'] : [])];
  });

  protected readonly galleryRouterLink = computed(() => {
    const routerLink = this.#routerLink();
    return routerLink ? [...routerLink, 'gallery'] : [];
  });

  protected readonly picturesRouterLink = computed(() => {
    const routerLink = this.#routerLink();
    return routerLink ? [...routerLink, 'pictures'] : [];
  });

  protected readonly filter = computed<APIGalleryFilter | undefined>(() => {
    const data = this.catalogueResource.value();
    if (!data) {
      return undefined;
    }

    const itemID = data.path[data.path.length - 1].item?.id;
    const exact = this.#exact();
    return {
      exactItemID: exact ? itemID : undefined,
      itemID: exact ? undefined : itemID,
    };
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error()) || !this.identity()) {
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
}
