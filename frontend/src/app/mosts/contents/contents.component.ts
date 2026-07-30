import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {MostsItem, MostsItemsRequest, MostsVehicleType} from '@grpc/spec.pb';
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
import {combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {ToastsService} from '../../toasts/toasts.service';
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
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, RouterLink, NgbTooltip, AsyncPipe, RemarkModule],
  templateUrl: './contents.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MostsContentsComponent {
  readonly #mostsService = inject(MostsService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #mostsClient = inject(MostsClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);

  readonly prefix = input.required<string[]>();

  readonly ratingCatname = input.required<null | string>();
  readonly #ratingCatname$ = toObservable(this.ratingCatname);

  protected readonly ratingCatnameNormalized$ = this.#ratingCatname$.pipe(
    switchMap((ratingCatname) =>
      this.#menu$.pipe(map((menu) => (ratingCatname ? ratingCatname : (menu.ratings || [])[0].catname))),
    ),
  );

  readonly typeCatname = input.required<null | string>();
  protected readonly typeCatname$ = toObservable(this.typeCatname);

  readonly yearsCatname = input.required<null | string>();
  protected readonly yearsCatname$ = toObservable(this.yearsCatname);

  readonly brandID = input<string>();
  protected readonly brandID$ = toObservable(this.brandID);

  readonly #menu$ = this.brandID$.pipe(
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((brandID) => this.#mostsService.getMenu$(brandID)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly years$ = this.#menu$.pipe(map((menu) => menu.years));

  protected readonly ratings$ = this.#menu$.pipe(map((menu) => menu.ratings));

  protected readonly vehicleTypes$: Observable<MostsVehicleTypeTranslated[]> = this.#menu$.pipe(
    map((menu) => vehicleTypesToList(menu.vehicleTypes || [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly defaultTypeCatname$ = this.vehicleTypes$.pipe(map((vehicleTypes) => vehicleTypes[0].catname));

  protected readonly items$: Observable<MostsItem[]> = combineLatest([
    this.ratingCatnameNormalized$.pipe(distinctUntilChanged(), debounceTime(10)),
    this.typeCatname$.pipe(distinctUntilChanged(), debounceTime(10)),
    this.yearsCatname$.pipe(distinctUntilChanged(), debounceTime(10)),
  ]).pipe(
    tap(() => {
      this.initPageEnv();
    }),
    switchMap(([ratingCatname, typeCatname, yearsCatname]) =>
      this.brandID$.pipe(
        switchMap((brandID) =>
          this.#mostsClient.getItems(
            new MostsItemsRequest({
              brandId: brandID,
              language: this.#languageService.language,
              ratingCatname: ratingCatname,
              typeCatname: typeCatname || '',
              yearsCatname: yearsCatname || '',
            }),
          ),
        ),
        catchError((error: unknown) => {
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      ),
    ),
    map((response) => response.items || []),
  );

  private initPageEnv() {
    this.#pageEnv.set({pageId: 21});
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
