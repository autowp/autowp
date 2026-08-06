import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {TopSpecsContributionsRequest, User} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {Observable, of} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {convertChildsCounts} from '../../catalogue/catalogue-service';
import {chunkBy} from '../../chunk';

type RawSpecsCarItem = Omit<CatalogueListItem, 'contributors'> & {contributorIds: string[]};

@Component({
  selector: 'app-index-specs-cars',
  imports: [RouterLink, CatalogueListItemComponent],
  templateUrl: './specs-cars.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexSpecsCarsComponent {
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #userService = inject(UserService);

  readonly #rawItemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'index-specs-cars',
    stream: () =>
      this.#itemsClient
        .getTopSpecsContributions(new TopSpecsContributionsRequest({language: this.#languageService.language}))
        .pipe(
          map((response) =>
            (response.items || []).map((item): RawSpecsCarItem => {
              const largeFormat = !!item.previewPictures?.largeFormat;

              const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map(
                (picture, idx) => {
                  let thumb = null;
                  if (picture.picture) {
                    thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
                  }

                  return {
                    picture: picture?.picture ? picture.picture : null,
                    routerLink: picture?.picture ? [...item.route, 'pictures', picture.picture.identity] : [],
                    thumb,
                  };
                },
              );

              return {
                acceptedPicturesCount: item.acceptedPicturesCount,
                canEditSpecs: item.canEditSpecs,
                categories: item.categories,
                childsCounts: item.childsCounts ? convertChildsCounts(item.childsCounts) : null,
                contributorIds: (item.specsContributors || []).map((contributor) => contributor.userId),
                description: item.description,
                design: item.design,
                details: {
                  count: item.childsCount,
                  routerLink: item.route,
                },
                engineVehicles: item.engineVehicles,
                hasText: item.hasText,
                id: item.id,
                itemTypeId: item.itemTypeId,
                nameDefault: item.nameDefault,
                nameHtml: item.nameHtml,
                picturesRouterLink: item.route.concat(['pictures']),
                previewPictures: {
                  largeFormat: false,
                  pictures,
                },
                produced: item.produced?.value,
                producedExactly: item.producedExactly,
                specsRouterLink: item.specsRoute,
                twinsGroups: item.twins,
              };
            }),
          ),
        ),
  });

  // Chained off the flat #rawItemsResource rather than resolved inline there: a raw Observable
  // (getUser$) created per item and consumed via `| async` only once the template renders each
  // item would race Angular's SSR whenStable() check the same way the Articles list author lookup
  // did — the outer resource's pending task completes before the deferred change-detection pass
  // that would subscribe to it. Chaining this resource off #rawItemsResource keeps the lookup
  // inside Angular's pending-task tracking instead.
  readonly #contributorsResource = rxResource({
    id: 'index-specs-cars-contributors',
    params: () => {
      const items = this.#rawItemsResource.value();
      if (!items) {
        return [];
      }
      return [...new Set(items.flatMap((item) => item.contributorIds))];
    },
    // A plain object rather than a Map: TransferState round-trips resource values through
    // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
    // enumerable properties, no toJSON), losing all entries.
    stream: ({params: userIds}): Observable<Record<string, User>> => {
      if (userIds.length === 0) {
        return of({});
      }
      return this.#userService.getUserMap$(userIds).pipe(
        map((userMap) => Object.fromEntries(userMap)),
        // getUserMap$ throws if the backend can't find a requested user. Degrade to showing no
        // user rather than erroring the whole resource over one stale reference.
        catchError(() => of({})),
      );
    },
  });

  protected readonly items = computed<CatalogueListItem[][] | undefined>(() => {
    const items = this.#rawItemsResource.value();
    if (!items) {
      return undefined;
    }
    const usersById = this.#contributorsResource.value() ?? {};

    return chunkBy(
      items.map(({contributorIds, ...item}) => ({
        ...item,
        contributors: contributorIds.map((id) => usersById[id] ?? null),
      })),
      2,
    );
  });
}
