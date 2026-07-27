import {ChangeDetectionStrategy, Component, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {ItemFields, ItemListOptions, ItemsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {MostsContentsComponent} from 'app/mosts/contents/contents.component';
import {of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

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

  constructor() {
    effect(() => {
      if (isNotFoundError(this.brandResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const brand = this.brandResource.value();
      if (brand) {
        this.#pageEnv.set({
          pageId: 208,
          title: $localize`${brand.nameText} Engines`,
        });
      }
    });
  }
}
