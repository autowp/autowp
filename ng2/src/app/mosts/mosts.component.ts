import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { TranslateService } from '@ngx-translate/core';
import { MostsService, APIMostsItem, APIMostsMenuRating, APIMostsMenuYear } from '../services/mosts';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { APIVehicleType } from '../services/vehicle-type';
const $ = require('jquery');

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
  public loading: number;
  public ratingCatname: string;
  public typeCatname: string;
  public yearsCatname: string;
  public defaultTypeCatname: string;

  constructor(
    private http: HttpClient,
    private mostsService: MostsService,
    private translate: TranslateService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.ratingCatname = params.rating_catname;
      this.typeCatname = params.type_catname;
      this.yearsCatname = params.years_catname;

      this.loading++;
      this.mostsService.getMenu().then(
        data => {
          this.years = data.years;
          this.ratings = data.ratings;
          this.vehilceTypes = vehicleTypesToList(data.vehilce_types);

          this.defaultTypeCatname = this.vehilceTypes[0].catname;

          if (!this.ratingCatname) {
            this.ratingCatname = this.ratings[0].catname;
          }

          const ratingName = 'most/' + this.ratingCatname;
          if (this.typeCatname) {
            const typeName = this.getVehicleTypeName(this.typeCatname);

            if (this.yearsCatname) {
              let yearName = '';
              for (const year of this.years) {
                if (year.catname === this.yearsCatname) {
                  yearName = year.name;
                }
              }

              this.translate.get([ratingName, typeName, yearName]).subscribe(
                (translations: string[]) => {
                  this.initPageEnv(156, {
                    MOST_CATNAME: this.ratingCatname,
                    MOST_NAME: translations[0],
                    CAR_TYPE_CARNAME: this.typeCatname,
                    CAR_TYPE_NAME: translations[1],
                    YEAR_CATNAME: this.yearsCatname,
                    YEAR_NAME: translations[2]
                  });
                },
                () => {
                  this.initPageEnv(156, {
                    MOST_CATNAME: this.ratingCatname,
                    MOST_NAME: ratingName,
                    CAR_TYPE_CARNAME: this.typeCatname,
                    CAR_TYPE_NAME: typeName,
                    YEAR_CATNAME: this.yearsCatname,
                    YEAR_NAME: yearName
                  });
                }
              );
            } else {
              this.translate.get([ratingName, typeName]).subscribe(
                (translations: string[]) => {
                  this.initPageEnv(155, {
                    MOST_CATNAME: this.ratingCatname,
                    MOST_NAME: translations[0],
                    CAR_TYPE_CARNAME: this.typeCatname,
                    CAR_TYPE_NAME: translations[1]
                  });
                },
                () => {
                  this.initPageEnv(155, {
                    MOST_CATNAME: this.ratingCatname,
                    MOST_NAME: ratingName,
                    CAR_TYPE_CARNAME: this.typeCatname,
                    CAR_TYPE_NAME: typeName
                  });
                }
              );
            }
          } else {
            this.translate.get(ratingName).subscribe(
              (translation: string) => {
                this.initPageEnv(154, {
                  MOST_CATNAME: this.ratingCatname,
                  MOST_NAME: translation
                });
              },
              () => {
                this.initPageEnv(154, {
                  MOST_CATNAME: this.ratingCatname,
                  MOST_NAME: ratingName
                });
              }
            );
          }

          setTimeout(() => {
            $('small.unit').tooltip({
              placement: 'bottom'
            });
          }, 0);

          this.loading--;
        },
        response => {
          this.loading--;
          Notify.response(response);
        }
      );

      this.loading++;
      this.mostsService
        .getItems({
          rating_catname: this.ratingCatname,
          type_catname: this.typeCatname,
          years_catname: this.yearsCatname
        })
        .subscribe(
          response => {
            this.items = response.items;
            this.loading--;
          },
          response => {
            Notify.response(response);
            this.loading--;
          }
        );
    });
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
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      disablePageName: true,
      name: 'page/' + pageId + '/name',
      pageId: pageId,
      args: args
    });*/
  }
}
