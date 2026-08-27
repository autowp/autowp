import type {SafeHtml} from '@angular/platform-browser';
import type {Item} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {DomSanitizer} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {GetSpecificationsRequest, ItemFields} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {map} from 'rxjs';

import type {Breadcrumbs, CatalogueData} from '../../catalogue-service';

import {CatalogueService} from '../../catalogue-service';

@Component({
  selector: 'app-catalogue-vehicles-specifications',
  imports: [RouterLink],
  templateUrl: './specifications.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueVehiclesSpecificationsComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #catalogueService = inject(CatalogueService);
  readonly #router = inject(Router);
  readonly #attrsClient = inject(AttrsClient);
  readonly #sanitizer = inject(DomSanitizer);
  readonly #languageService = inject(LanguageService);

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
  // navigates off this resource's (and htmlResource's) error() signal.
  //
  // `id` is suffixed with the brand/path/type route params read once at construction time - see
  // the identical note on CatalogueVehiclesComponent.catalogueResource in ../vehicles.component.ts.
  protected readonly catalogueResource = rxResource({
    id: `catalogue-vehicles-specifications-catalogue-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    stream: (): Observable<CatalogueData> =>
      this.#catalogueService.resolveCatalogue$(
        this.#route,
        new ItemFields({
          hasChildSpecs: true,
          hasSpecs: true,
        }),
      ),
  });

  // Reading a resource's value() while it's in an error state throws - every other computed()
  // and resource params() below that needs catalogueResource's data reads it through this signal
  // instead of the resource directly, so a real (non-NOT_FOUND) failure here degrades the rest of
  // the page to its "no data yet" branches instead of taking the whole component down. The error
  // itself is shown inline in the template (specifications.component.html), not swallowed here.
  protected readonly catalogueData = computed(() =>
    this.catalogueResource.hasValue() ? this.catalogueResource.value() : undefined,
  );

  protected readonly brand = computed(() => this.catalogueData()?.brand);

  protected readonly breadcrumbs = computed<Breadcrumbs[] | undefined>(() => {
    const data = this.catalogueData();
    return data ? CatalogueService.pathToBreadcrumbs(data.brand, data.path) : undefined;
  });

  protected readonly item = computed<Item | undefined>(() => {
    const data = this.catalogueData();
    const item = data?.path[data.path.length - 1].item;
    return item ?? undefined;
  });

  protected readonly htmlResource = rxResource({
    id: `catalogue-vehicles-specifications-html-${this.#catname() ?? ''}-${this.#pathParam() ?? ''}-${this.#typeParam() ?? ''}`,
    params: () => this.item(),
    stream: ({params: item}): Observable<SafeHtml> => {
      if (item.hasChildSpecs) {
        return (
          this.#attrsClient
            .getChildSpecifications(
              new GetSpecificationsRequest({
                itemId: item.id,
                language: this.#languageService.language,
              }),
            )
            // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
            .pipe(map((response) => this.#sanitizer.bypassSecurityTrustHtml(response.html)))
        );
      }

      if (item.hasSpecs) {
        return (
          this.#attrsClient
            .getSpecifications(
              new GetSpecificationsRequest({itemId: item.id, language: this.#languageService.language}),
            )
            // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
            .pipe(map((response) => this.#sanitizer.bypassSecurityTrustHtml(response.html)))
        );
      }

      return notFoundError();
    },
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.catalogueResource.error()) || isNotFoundError(this.htmlResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const brand = this.brand();
      if (!brand) {
        return;
      }

      this.#pageEnv.set({
        pageId: PageId.CATALOGUE_SPECIFICATIONS,
        title: $localize`Specifications of` + ' ' + brand.nameHtml,
      });
    });
  }

  protected readonly errorMessage = errorMessage;
}
