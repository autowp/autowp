import type {OnInit, ResourceRef} from '@angular/core';
import type {MostsItem, MostsRating, MostsVehicleType, YearsRange} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, effect, inject, Injector, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {MostsItemsRequest} from '@grpc/spec.pb';
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
import {errorMessage} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {map} from 'rxjs';

import {MostsService} from '../mosts.service';

export interface MostsVehicleTypeTranslated extends MostsVehicleType.AsObject {
  nameTranslated?: string;
}

interface MostsMenuData {
  ratings: MostsRating[] | undefined;
  vehicleTypes: MostsVehicleTypeTranslated[];
  years: undefined | YearsRange[];
}

function vehicleTypesToList(vehicleTypes: MostsVehicleType[]): MostsVehicleTypeTranslated[] {
  const result: MostsVehicleTypeTranslated[] = [];
  for (const item of vehicleTypes) {
    result.push({...item.toObject(), nameTranslated: getVehicleTypeRpTranslation(item.nameRp)});
    for (const child of item.childs ?? []) {
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
export class MostsContentsComponent implements OnInit {
  readonly #mostsService = inject(MostsService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #mostsClient = inject(MostsClient);
  readonly #languageService = inject(LanguageService);
  readonly #injector = inject(Injector);

  readonly prefix = input.required<string[]>();
  readonly ratingCatname = input.required<null | string>();
  readonly typeCatname = input.required<null | string>();
  readonly yearsCatname = input.required<null | string>();
  readonly brandID = input<string>();

  // Both resources below are constructed in ngOnInit() (with an explicit injector, since ngOnInit
  // isn't an injection context) rather than as field initializers: their TransferState `id`s have
  // to be derived from the inputs, and inputs aren't bound yet at field-initializer time - reading
  // a *required* one there is forbidden by Angular's compiler outright, and an optional one would
  // silently read undefined. Same reasoning (and same shape) as GalleryComponent.galleryResource.
  protected menuResource!: ResourceRef<MostsMenuData | undefined>;
  protected itemsResource!: ResourceRef<MostsItem[] | undefined>;

  ngOnInit(): void {
    this.menuResource = rxResource({
      // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
      // vehicleTypesToList() (which calls .toObject()) runs here in the stream, on the real
      // MostsVehicleType instances fresh off the wire, rather than in the vehicleTypes computed()
      // below reading menuResource.value() lazily: a resource value seeded from TransferState on
      // hydration is a plain JSON-shaped object, not a real MostsVehicleType class instance, so
      // .toObject() doesn't exist on it even though the same public fields do.
      //
      // `id` is suffixed with brandID: this component renders both the global /mosts page (no
      // brand) and the per-brand /:brand/mosts one, and their menus differ. A static id would let
      // a second instance - created by navigating between them before Angular's whenStable() ever
      // resolves, while the transfer cache is still active - seed itself from the other page's
      // entry and stick with it, since params() never changes afterwards.
      id: `mosts-contents-menu-${this.brandID() ?? ''}`,
      injector: this.#injector,
      params: () => this.brandID(),
      stream: ({params: brandID}) =>
        this.#mostsService.getMenu$(brandID).pipe(
          map((menu) => ({
            ratings: menu.ratings,
            vehicleTypes: vehicleTypesToList(menu.vehicleTypes ?? []),
            years: menu.years,
          })),
        ),
    });

    this.itemsResource = rxResource({
      // `id` carries every input the items query varies on. The rating/type/years levels are four
      // separate route configs (see mosts-routing.module.ts and catalogue-routing.module.ts), so
      // picking a different rating from the dropdown destroys and recreates this component rather
      // than just changing its inputs - a static id would seed the new rating's resource with the
      // previous rating's items, and it would never fetch.
      id: `mosts-contents-items-${this.brandID() ?? ''}-${this.ratingCatname() ?? ''}-${this.typeCatname() ?? ''}-${this.yearsCatname() ?? ''}`,
      injector: this.#injector,
      params: () => {
        const ratingCatname = this.ratingCatnameNormalized();

        return ratingCatname === undefined
          ? undefined
          : {
              brandID: this.brandID(),
              ratingCatname,
              typeCatname: this.typeCatname() ?? '',
              yearsCatname: this.yearsCatname() ?? '',
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
          .pipe(map((response) => response.items ?? [])),
    });
  }

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the computeds below don't blow up when menuResource errors (the page
  // just renders nothing, since ratingCatnameNormalized() falls through to undefined too).
  protected readonly menuData = computed(() => (this.menuResource.hasValue() ? this.menuResource.value() : undefined));

  protected readonly years = computed(() => this.menuData()?.years);
  protected readonly ratings = computed(() => this.menuData()?.ratings);
  protected readonly vehicleTypes = computed(() => this.menuData()?.vehicleTypes);

  protected readonly defaultTypeCatname = computed(() => this.vehicleTypes()?.[0]?.catname);

  // ratingCatname() is '' (not null) when the route has no rating_catname param - see
  // mosts.component.ts's `?? ''` at the source - so the empty-string case must fall through to
  // the first rating exactly like a real "unset" would. ?? wouldn't treat '' as unset.
  // eslint-disable-next-line @typescript-eslint/prefer-nullish-coalescing
  protected readonly ratingCatnameNormalized = computed(() => this.ratingCatname() || this.ratings()?.[0]?.catname);

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

  protected readonly errorMessage = errorMessage;
}
