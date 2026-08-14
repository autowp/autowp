import {ChangeDetectionStrategy, Component, computed, effect, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {MostsItemsRequest, MostsVehicleType} from '@grpc/spec.pb';
import {MostsClient} from '@grpc/spec.pbsc';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle, NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {
  getMostsPeriodsTranslation,
  getMostsRatingParamsTranslation,
  getMostsRatingsTranslation,
  getUnitAbbrTranslation,
  getUnitNameTranslation,
  getVehicleTypeRpTranslation,
} from '@utils/translations';
import {RemarkModule} from 'ngx-remark';
import {map} from 'rxjs';

import {MostsService} from '../mosts.service';

export interface MostsVehicleTypeTranslated extends MostsVehicleType.AsObject {
  nameTranslated?: string;
}

function vehicleTypesToList(vehicleTypes: MostsVehicleType[]): MostsVehicleTypeTranslated[] {
  const result: MostsVehicleTypeTranslated[] = [];
  for (const item of vehicleTypes) {
    result.push({...item.toObject(), nameTranslated: getVehicleTypeRpTranslation(item.nameRp)});
    for (const child of item.childs || []) {
      result.push({...child.toObject(), nameTranslated: getVehicleTypeRpTranslation(child.nameRp)});
    }
  }

  return result;
}

@Component({
  selector: 'app-mosts-contents',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, RouterLink, NgbTooltip, RemarkModule],
  templateUrl: './contents.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MostsContentsComponent {
  readonly #mostsService = inject(MostsService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #mostsClient = inject(MostsClient);
  readonly #languageService = inject(LanguageService);

  readonly prefix = input.required<string[]>();
  readonly ratingCatname = input.required<null | string>();
  readonly typeCatname = input.required<null | string>();
  readonly yearsCatname = input.required<null | string>();
  readonly brandID = input<string>();

  protected readonly menuResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // vehicleTypesToList() (which calls .toObject()) runs here in the stream, on the real
    // MostsVehicleType instances fresh off the wire, rather than in the vehicleTypes computed()
    // below reading menuResource.value() lazily: a resource value seeded from TransferState on
    // hydration is a plain JSON-shaped object, not a real MostsVehicleType class instance, so
    // .toObject() doesn't exist on it even though the same public fields do.
    id: 'mosts-contents-menu',
    params: () => this.brandID(),
    stream: ({params: brandID}) =>
      this.#mostsService.getMenu$(brandID).pipe(
        map((menu) => ({
          ratings: menu.ratings,
          vehicleTypes: vehicleTypesToList(menu.vehicleTypes || []),
          years: menu.years,
        })),
      ),
  });

  protected readonly years = computed(() => this.menuResource.value()?.years);
  protected readonly ratings = computed(() => this.menuResource.value()?.ratings);
  protected readonly vehicleTypes = computed(() => this.menuResource.value()?.vehicleTypes);

  protected readonly defaultTypeCatname = computed(() => this.vehicleTypes()?.[0]?.catname);

  protected readonly ratingCatnameNormalized = computed(() => this.ratingCatname() || this.ratings()?.[0]?.catname);

  protected readonly itemsResource = rxResource({
    id: 'mosts-contents-items',
    params: () => {
      const ratingCatname = this.ratingCatnameNormalized();

      return ratingCatname === undefined
        ? undefined
        : {
            brandID: this.brandID(),
            ratingCatname,
            typeCatname: this.typeCatname() || '',
            yearsCatname: this.yearsCatname() || '',
          };
    },
    stream: ({params}) =>
      this.#mostsClient
        .getItems(
          new MostsItemsRequest({
            brandId: params.brandID,
            language: this.#languageService.language,
            ratingCatname: params.ratingCatname,
            typeCatname: params.typeCatname,
            yearsCatname: params.yearsCatname,
          }),
        )
        .pipe(map((response) => response.items || [])),
  });

  constructor() {
    // Mirrors the original tap()-before-fetch placement: MostsComponent's own ngOnInit only runs
    // once (Angular reuses this component instance across in-place /mosts/** param navigations),
    // so this effect is what keeps pageEnv correctly set to pageId 21 on every subsequent
    // rating/type/years change, independent of whether the items fetch below has resolved yet.
    effect(() => {
      this.ratingCatnameNormalized();
      this.typeCatname();
      this.yearsCatname();
      this.#pageEnv.set({pageId: 21});
    });
  }

  protected getUnitAbbrTranslation(id: string): string {
    return getUnitAbbrTranslation(id);
  }

  protected getUnitNameTranslation(id: string): string {
    return getUnitNameTranslation(id);
  }

  protected getMostsRatingsTranslation(id: string): string {
    return getMostsRatingsTranslation(id);
  }

  protected getMostsRatingParamsTranslation(id: string): string {
    return getMostsRatingParamsTranslation(id);
  }

  protected getMostsPeriodsTranslation(id: string): string {
    return getMostsPeriodsTranslation(id);
  }
}
