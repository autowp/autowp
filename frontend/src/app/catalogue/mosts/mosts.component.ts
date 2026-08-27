import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage, isNotFoundError, notFoundError} from 'app/grpc';
import {MostsContentsComponent} from 'app/mosts/contents/contents.component';
import {map, of, switchMap} from 'rxjs';

@Component({
  selector: 'app-catalogue-mosts',
  imports: [RouterLink, MostsContentsComponent],
  templateUrl: './mosts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMostsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly ratingCatname = toSignal(
    this.#route.paramMap.pipe(map((params) => params.get('rating_catname'))),
    {requireSync: true},
  );
  protected readonly typeCatname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('type_catname'))), {
    requireSync: true,
  });
  protected readonly yearsCatname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('years_catname'))), {
    requireSync: true,
  });

  readonly #brandCatname = toSignal(this.#route.paramMap.pipe(map((params) => params.get('brand'))), {
    requireSync: true,
  });

  protected readonly brandResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the brand catname read once at construction time - a static id would let a
    // second instance of this component, created by navigating SSR /bmw/mosts -> away ->
    // /toyota/mosts before Angular's whenStable() ever resolves, match TransferState's
    // still-present bmw entry and seed itself with bmw's brand instead of fetching toyota's.
    id: `catalogue-mosts-brand-${this.#brandCatname() ?? ''}`,
    params: () => this.#brandCatname(),
    stream: ({params: catname}) => {
      if (!catname) {
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

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the effect and template below don't blow up on a non-NOT_FOUND
  // brandResource error (surfaced generically by the template instead).
  protected readonly brandData = computed(() =>
    this.brandResource.hasValue() ? this.brandResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const brand = this.brandData();
      if (brand) {
        // pageId 21 is the mosts page - matching MostsContentsComponent's own effect, which runs
        // after this one (it's a child component) and would otherwise leave the two disagreeing.
        // That child sets no title, so the title set here is the one that reaches the document.
        this.#pageEnv.set({
          pageId: PageId.MOSTS,
          title: $localize`${brand.nameText} Mostly`,
        });
      }
    });
  }

  protected readonly errorMessage = errorMessage;
}
