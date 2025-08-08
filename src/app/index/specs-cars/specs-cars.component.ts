import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {TopSpecsContributionsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {UserService} from '@services/user';
import {
  CatalogueListItem,
  CatalogueListItemComponent,
  CatalogueListItemPicture,
} from '@utils/list-item/list-item.component';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

import {convertChildsCounts} from '../../catalogue/catalogue-service';
import {chunkBy} from '../../chunk';

@Component({
  selector: 'app-index-specs-cars',
  imports: [RouterLink, AsyncPipe, CatalogueListItemComponent],
  templateUrl: './specs-cars.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexSpecsCarsComponent {
  readonly #userService = inject(UserService);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly items$: Observable<CatalogueListItem[][]> = this.#itemsClient
    .getTopSpecsContributions(
      new TopSpecsContributionsRequest({
        language: this.#languageService.language,
      }),
    )
    .pipe(
      map((response) => {
        return chunkBy(
          (response.items || []).map((item) => {
            const largeFormat = !!item.previewPictures?.largeFormat;

            const pictures: CatalogueListItemPicture[] = (item.previewPictures?.pictures || []).map((picture, idx) => {
              let thumb = null;
              if (picture.picture) {
                thumb = largeFormat && idx == 0 ? picture.picture.thumbLarge : picture.picture.thumbMedium;
              }

              return {
                picture: picture?.picture ? picture.picture : null,
                routerLink: picture?.picture ? [...item.route, 'pictures', picture.picture.identity] : [],
                thumb,
              };
            });

            return {
              acceptedPicturesCount: item.acceptedPicturesCount,
              canEditSpecs: item.canEditSpecs,
              categories: item.categories,
              childsCounts: item.childsCounts ? convertChildsCounts(item.childsCounts) : null,
              contributors: (item.specsContributors || []).map((contributor) =>
                this.#userService.getUser$(contributor.userId),
              ),
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
            } as CatalogueListItem;
          }),
          2,
        );
      }),
    );
}
