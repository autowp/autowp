import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import {
  MostsService,
  APIMostsItem,
  APIMostsMenuRating,
  APIMostsMenuYear
} from '../services/mosts';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { APIVehicleType } from '../services/vehicle-type';
import { PageEnvService } from '../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  tap,
  switchMap
} from 'rxjs/operators';

function vehicleTypesToList(vehilceTypes: APIVehicleType[]): APIVehicleType[] {
  const result: APIVehicleType[] = [];
  for (const item of vehilceTypes) {
    result.push(item);
    for (const child of item.childs) {
      result.push(child);
    }
  }

  return result;
}

@Component({
  selector: 'app-mosts',
  templateUrl: './mosts.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class MostsComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public items: APIMostsItem[];
  public years: APIMostsMenuYear[];
  public ratings: APIMostsMenuRating[];
  public vehilceTypes: APIVehicleType[];
  public loading = 0;
  public ratingCatname: string;
  public typeCatname: string;
  public yearsCatname: string;
  public defaultTypeCatname: string;

  constructor(
    private http: HttpClient,
    private mostsService: MostsService,
    private translate: TranslateService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = combineLatest(
      this.route.params.pipe(
        distinctUntilChanged(),
        debounceTime(30)
      ),
      this.mostsService.getMenu(),
      (params, menu) => ({ params, menu })
    )
      .pipe(
        tap(data => {
          this.ratingCatname = data.params.rating_catname;
          this.typeCatname = data.params.type_catname;
          this.yearsCatname = data.params.years_catname;

          this.years = data.menu.years;
          this.ratings = data.menu.ratings;
          this.vehilceTypes = vehicleTypesToList(data.menu.vehilce_types);

          this.defaultTypeCatname = this.vehilceTypes[0].catname;

          if (!this.ratingCatname) {
            this.ratingCatname = this.ratings[0].catname;
          }

          this.setPageEnv(
            this.ratingCatname,
            this.typeCatname,
            this.yearsCatname
          );
        }),
        switchMap(data =>
          this.mostsService.getItems({
            rating_catname: this.ratingCatname,
            type_catname: this.typeCatname,
            years_catname: this.yearsCatname
          })
        )
      )
      .subscribe(response => {
        this.items = response.items;
      });

    /*
      setTimeout(() => {
          $('small.unit').tooltip({
            placement: 'bottom'
          });
        }, 0);
      */
  }

  private setPageEnv(
    ratingCatname: string,
    typeCatname: string,
    yearsCatname: string
  ) {
    const ratingName = 'most/' + ratingCatname;
    if (typeCatname) {
      const typeName = this.getVehicleTypeName(typeCatname);

      if (yearsCatname) {
        let yearName = '';
        for (const year of this.years) {
          if (year.catname === yearsCatname) {
            yearName = year.name;
          }
        }

        this.translate.get([ratingName, typeName, yearName]).subscribe(
          (translations: string[]) => {
            this.initPageEnv(156, {
              MOST_CATNAME: ratingCatname,
              MOST_NAME: translations[0],
              CAR_TYPE_CARNAME: typeCatname,
              CAR_TYPE_NAME: translations[1],
              YEAR_CATNAME: yearsCatname,
              YEAR_NAME: translations[2]
            });
          },
          () => {
            this.initPageEnv(156, {
              MOST_CATNAME: ratingCatname,
              MOST_NAME: ratingName,
              CAR_TYPE_CARNAME: typeCatname,
              CAR_TYPE_NAME: typeName,
              YEAR_CATNAME: yearsCatname,
              YEAR_NAME: yearName
            });
          }
        );
      } else {
        this.translate.get([ratingName, typeName]).subscribe(
          (translations: string[]) => {
            this.initPageEnv(155, {
              MOST_CATNAME: ratingCatname,
              MOST_NAME: translations[0],
              CAR_TYPE_CARNAME: typeCatname,
              CAR_TYPE_NAME: translations[1]
            });
          },
          () => {
            this.initPageEnv(155, {
              MOST_CATNAME: ratingCatname,
              MOST_NAME: ratingName,
              CAR_TYPE_CARNAME: typeCatname,
              CAR_TYPE_NAME: typeName
            });
          }
        );
      }
    } else {
      this.translate.get(ratingName).subscribe(
        (translation: string) => {
          this.initPageEnv(154, {
            MOST_CATNAME: ratingCatname,
            MOST_NAME: translation
          });
        },
        () => {
          this.initPageEnv(154, {
            MOST_CATNAME: ratingCatname,
            MOST_NAME: ratingName
          });
        }
      );
    }
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  private getVehicleTypeName(catname: string): string {
    let result = '';
    for (const vehilceType of this.vehilceTypes) {
      if (vehilceType.catname === catname) {
        result = vehilceType.name;
        break;
      }

      for (const subVehilceType of vehilceType.childs) {
        if (subVehilceType.catname === catname) {
          result = subVehilceType.name;
          break;
        }
      }

      if (result) {
        break;
      }
    }

    return result;
  }

  private initPageEnv(pageId: number, args: any) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      disablePageName: true,
      name: 'page/' + pageId + '/name',
      pageId: pageId,
      args: args
    });
  }
}
