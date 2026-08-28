import type {Picture} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError} from 'app/grpc';
import {map} from 'rxjs';

import type {APIGalleryFilter} from '../../../gallery/gallery.component';
import type {CatalogueData} from '../../catalogue-service';

import {GalleryComponent} from '../../../gallery/gallery.component';
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
  readonly #notFound = inject(NotFoundService);

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
    stream: (): Observable<CatalogueData> => this.#catalogueService.resolveCatalogue$(this.#route),
  });

  // Reading a resource's value() while it's in an error state throws - every other computed()
  // below that needs catalogueResource's data reads it through this signal instead of the
  // resource directly, so a real (non-NOT_FOUND) failure here degrades the rest of the page to
  // its "no data yet" branches instead of taking the whole component down. The error itself is
  // shown inline in the template (gallery.component.html), not swallowed here.
  protected readonly catalogueData = computed(() =>
    this.catalogueResource.hasValue() ? this.catalogueResource.value() : undefined,
  );

  readonly #routerLink = computed<string[] | undefined>(() => {
    const data = this.catalogueData();
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
    const data = this.catalogueData();
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
        this.#notFound.report();
      }
    });
  }

  protected pictureSelected(item: null | Picture) {
    if (item) {
      this.#pageEnv.set({
        layout: {isGalleryPage: true},
        pageId: PageId.PICTURES,
        title: item.nameText,
      });
    }
  }

  protected readonly errorMessage = errorMessage;
}
