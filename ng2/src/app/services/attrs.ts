import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from './api.service';
import { Observable } from 'rxjs';
import { APIUser } from './user';
import { APIItem } from './item';

export interface APIAttrListOption {
  id: number;
  name: string;
  childs?: APIAttrListOption[];
}

export interface APIttrListOptionsGetResponse {
  items: APIAttrListOption[];
}

export interface APIttrListOptionsGetOptions {
  attribute_id: number;
}

export interface APIAttrConflicsGetOptions {
  filter: string;
  page: number;
  fields: string;
}

export interface APIAttrConflictValue {
  user_id: number;
}

export interface APIAttrConflict {
  object: string;
  attribute: APIAttrAttribute;
  url: string;
  unit: APIAttrUnit;
  values: APIAttrConflictValue[];

  user?: APIUser; // TODO: remove
}

export interface APIAttrConflicsGetResponse {
  items: APIAttrConflict[];
  paginator: APIPaginator;
}

export interface APIAttrAttributesGetOptions {
  fields?: string;
  zone_id?: number;
  recursive: boolean;
}

export interface APIAttrAttributesGetResponse {
  items: APIAttrAttribute[];
}

export interface APIAttrValuesGetOptions {
  item_id: number;
  zone_id?: number;
  fields?: string;
  limit?: number;
}

export interface APIAttrValue {
  attribute_id: number;
  value: APIAttrAttributeValue;
}

export interface APIAttrValuesGetResponse {
  items: APIAttrValue[];
  paginator: APIPaginator;
}

export interface APIAttrUserValuesOptions {
  user_id?: number;
  item_id: number;
  zone_id?: number;
  page?: number;
  fields?: string;
  limit?: number;
}

export type APIAttrAttributeValue = number | string | string[];

export interface APIAttrUserValue {
  item_id: number;
  user_id: number;
  attribute_id: number;
  value: APIAttrAttributeValue;
  empty: boolean;
  value_text: string;
  user: APIUser;
  update_date: string;
  item: APIItem;
  unit: APIAttrUnit;
  path: string[];
}

export interface APIAttrUserValueGetResponse {
  items: APIAttrUserValue[];
  paginator: APIPaginator;
}

export interface APIAttrsZonesGetResponse {
  items: APIAttrZone[];
}

export interface APIAttrsAttributeTypesGetResponse {
  items: APIAttrAttributeType[];
}

export interface APIAttrsUnitGetResponse {
  items: APIAttrUnit[];
}

export interface APIAttrAttributeGetResponse {
  items: APIAttrAttribute[];
}

export interface APIAttrZone {
  id: number;
  name: string;
}

export interface APIAttrAttribute {
  id: number;
  type_id: number;
  name: string;
  description: string;
  precision: number;
  unit_id: number;
  unit: APIAttrUnit;
  childs: APIAttrAttribute[];
  options: APIAttrListOption[];
  is_multiple: boolean;
  disabled: boolean;
}

export interface APIAttrAttributeType {
  id: number;
  name: string;
}

export interface APIAttrUnit {
  id: number;
  name: string;
  abbr: string;
}

export interface GetAttributeServiceOptions {
  fields: string;
}
@Injectable()
export class AttrsService {
  constructor(private http: HttpClient) {}

  public getZone(id: number): Promise<APIAttrZone> {
    return new Promise<APIAttrZone>((resolve, reject) => {
      this.getZones().then(
        (zones: APIAttrZone[]) => {
          for (const zone of zones) {
            if (zone.id === id) {
              resolve(zone);
              return;
            }
          }
          reject();
        },
        response => reject(response)
      );
    });
  }

  public getZones(): Promise<APIAttrZone[]> {
    return new Promise<APIAttrZone[]>((resolve, reject) => {
      this.http
        .get<APIAttrsZonesGetResponse>('/api/attr/zone')
        .subscribe(
          response => resolve(response.items),
          response => reject(response)
        );
    });
  }

  public getAttribute(
    id: number,
    options?: GetAttributeServiceOptions
  ): Promise<APIAttrAttribute> {
    return new Promise((resolve, reject) => {
      this.getAttributeByLocation('/api/attr/attribute/' + id).subscribe(
        response => resolve(response),
        response => reject(response)
      );
    });
  }

  public getAttributeByLocation(
    location: string
  ): Observable<APIAttrAttribute> {
    return this.http.get<APIAttrAttribute>(location);
  }

  public getAttributeTypes(): Promise<APIAttrAttributeType[]> {
    return new Promise<APIAttrAttributeType[]>((resolve, reject) => {
      this.http
        .get<APIAttrsAttributeTypesGetResponse>('/api/attr/attribute-type')
        .subscribe(
          response => resolve(response.items),
          response => reject(response)
        );
    });
  }

  public getUnits(): Promise<APIAttrUnit[]> {
    return new Promise<APIAttrUnit[]>((resolve, reject) => {
      this.http
        .get<APIAttrsUnitGetResponse>('/api/attr/unit')
        .subscribe(
          response => resolve(response.items),
          response => reject(response)
        );
    });
  }

  public getUserValues(
    options: APIAttrUserValuesOptions
  ): Observable<APIAttrUserValueGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.zone_id) {
      params.zone_id = options.zone_id.toString();
    }

    if (options.user_id) {
      params.user_id = options.user_id.toString();
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    return this.http.get<APIAttrUserValueGetResponse>('/api/attr/user-value', {
      params: params
    });
  }

  public getValues(
    options: APIAttrValuesGetOptions
  ): Observable<APIAttrValuesGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.zone_id) {
      params.zone_id = options.zone_id.toString();
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    return this.http.get<APIAttrValuesGetResponse>('/api/attr/value', {
      params: params
    });
  }
  public getAttributes(
    options: APIAttrAttributesGetOptions
  ): Observable<APIAttrAttributesGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.zone_id) {
      params.zone_id = options.zone_id.toString();
    }

    if (options.recursive) {
      params.recursive = '1';
    }

    return this.http.get<APIAttrAttributesGetResponse>('/api/attr/attribute', {
      params: params
    });
  }

  public getConfilicts(
    options: APIAttrConflicsGetOptions
  ): Observable<APIAttrConflicsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.filter) {
      params.filter = options.filter.toString();
    }

    return this.http.get<APIAttrConflicsGetResponse>('/api/attr/conflict', {
      params: params
    });
  }

  public getListOptions(
    options: APIttrListOptionsGetOptions
  ): Observable<APIttrListOptionsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.attribute_id) {
      params.attribute_id = options.attribute_id.toString();
    }

    return this.http.get<APIttrListOptionsGetResponse>(
      '/api/attr/list-option',
      {
        params: params
      }
    );
  }
}
