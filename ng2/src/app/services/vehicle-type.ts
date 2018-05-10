import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';

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
  private types: APIVehicleType[];
  private typesInititalized = false;

  constructor(private http: HttpClient, private translate: TranslateService) {}

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

  private walkTypes(types: APIVehicleType[], callback: (type: APIVehicleType) => void) {
    for (const type of types) {
      callback(type);
      this.walkTypes(type.childs, callback);
    }
  }

  public getTypes(): Promise<APIVehicleType[]> {
    return new Promise<APIVehicleType[]>((resolve, reject) => {
      if (this.typesInititalized) {
        resolve(this.types);
        return;
      }
      this.http.get<APIVehicleTypesGetResponse>('/api/vehicle-types').subscribe(
        response => {
          this.types = response.items;
          const names = this.collectNames(this.types);

          this.translate.get(names).subscribe(
            (translations: any) => {
              const map = {};
              for (let i = 0; i < names.length; i++) {
                map[names[i]] = translations[i];
              }
              this.applyTranslations(this.types, map);
              this.typesInititalized = true;
              resolve(this.types);
            },
            () => reject()
          );
        },
        () => reject()
      );
    });
  }

  public getTypesById(ids: number[]): Promise<APIVehicleType[]> {
    return new Promise<APIVehicleType[]>((resolve, reject) => {
      if (ids.length <= 0) {
        resolve([]);
        return;
      }

      this.getTypes().then(
        (types: APIVehicleType[]) => {
          const result: APIVehicleType[] = [];
          this.walkTypes(types, type => {
            if (ids.includes(type.id)) {
              result.push(type);
            }
          });
          resolve(result);
        },
        () => reject()
      );
    });
  }
}
