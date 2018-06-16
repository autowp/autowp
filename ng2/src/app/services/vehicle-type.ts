import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { Observable, of } from 'rxjs';
import { map, switchMap, shareReplay } from 'rxjs/operators';

export interface APIVehicleType {
  id: number;
  name: string;
  catname: string;
  nameTranslated?: string;
  childs: APIVehicleType[];
}

export interface APIVehicleTypesGetResponse {
  items: APIVehicleType[];
}

@Injectable()
export class VehicleTypeService {
  private types$: Observable<APIVehicleType[]>;

  constructor(private http: HttpClient, private translate: TranslateService) {
    this.types$ = this.http
      .get<APIVehicleTypesGetResponse>('/api/vehicle-types')
      .pipe(
        switchMap(
          response => this.translate.get(this.collectNames(response.items)),
          (response, translations) => ({ response, translations })
        ),
        map(data => {
          this.applyTranslations(data.response.items, data.translations);
          return data.response.items;
        }),
        shareReplay(1)
      );
  }

  private collectNames(types: APIVehicleType[]): string[] {
    const result: string[] = [];
    this.walkTypes(types, (type: APIVehicleType) => {
      result.push(type.name);
    });
    return result;
  }

  private applyTranslations(types: APIVehicleType[], translations: any) {
    this.walkTypes(types, (type: APIVehicleType) => {
      type.nameTranslated = translations[type.name];
    });
  }

  private walkTypes(
    types: APIVehicleType[],
    callback: (type: APIVehicleType) => void
  ) {
    for (const type of types) {
      callback(type);
      this.walkTypes(type.childs, callback);
    }
  }

  public getTypes(): Observable<APIVehicleType[]> {
    return this.types$;
  }

  public getTypesById(ids: number[]): Observable<APIVehicleType[]> {
    if (ids.length <= 0) {
      return of([]);
    }
    return this.types$.pipe(
      map(types => {
        const result: APIVehicleType[] = [];
        this.walkTypes(types, type => {
          if (ids.includes(type.id)) {
            result.push(type);
          }
        });
        return result;
      })
    );
  }
}
