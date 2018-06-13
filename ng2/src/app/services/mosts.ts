import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { APIItem } from './item';
import { APIAttrUnit } from './attrs';
import { APIPicture } from './picture';
import { APIVehicleType } from './vehicle-type';

export interface APIMostsItemsGetOptions {
  rating_catname: string;
  type_catname: string;
  years_catname: string;
}

export interface APIMostsItem {
  item: APIItem;
  value_html: string;
  value_text: string;
  unit: APIAttrUnit;
  pictures: APIPicture;
}

export interface APIMostsItemsGetResponse {
  items: APIMostsItem[];
}

export interface APIMostsMenuRating {
  name: string;
  catname: string;
}

export interface APIMostsMenuYear {
  name: string;
  catname: string;
}

export interface APIMostsMenuGetResponse {
  years: APIMostsMenuYear[];
  ratings: APIMostsMenuRating[];
  vehilce_types: APIVehicleType[];
}

@Injectable()
export class MostsService {
  private menu$: Observable<APIMostsMenuGetResponse>;

  constructor(private http: HttpClient) {
    this.menu$ = this.http.get<APIMostsMenuGetResponse>('/api/mosts/menu');
  }

  public getMenu(): Observable<APIMostsMenuGetResponse> {
    return this.menu$;
  }

  public getItems(
    options: APIMostsItemsGetOptions
  ): Observable<APIMostsItemsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.rating_catname) {
      params.rating_catname = options.rating_catname;
    }

    if (options.type_catname) {
      params.type_catname = options.type_catname;
    }

    if (options.years_catname) {
      params.years_catname = options.years_catname;
    }

    return this.http.get<APIMostsItemsGetResponse>('/api/mosts/items', {
      params: params
    });
  }
}
