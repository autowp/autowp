import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  APIItemVehicleTypeGetResponse,
  APIPaginator,
  APIImage
} from './api.service';
import { Observable, of, forkJoin } from 'rxjs';
import { APIPicture } from './picture';
import { switchMap } from 'rxjs/operators';

export interface APIItemsGetResponse {
  items: APIItem[];
  paginator: APIPaginator;
}

export interface APIItem {
  name: string;
  id: number;
  item_type_id: number;
  is_group: boolean;
  name_text: string;
  name_html: string;
  full_name: string;
  catname: string;
  body: string;
  lat: number;
  lng: number;
  is_concept: boolean;
  today: boolean | null;
  begin_month: number;
  begin_year: number;
  end_month: number;
  end_year: number;
  begin_model_year: number;
  end_model_year: number;
  produced: number;
  produced_exactly: boolean;
  spec_id: number | string | null;
  logo: APIImage;
  engine_id: number | null;
  engine_inherit: boolean | null;
  attr_zone_id: number;

  engine_vehicles_count: number;
  pictures_count: number;
  childs_count: number;
  parents_count: number;
  links_count: number;
  item_language_count: number;
  subscription: boolean;

  related_group_pictures: APIItemRelatedGroupItem[];

  description: string;
  is_compiles_item_of_day: boolean;
  item_of_day_pictures: APIPicture[];
  design: APIItem;
  name_default: string;
  specs_url: string;
  twins_groups: APIItem[];
  categories: APIItem[];
  url: string;
  spec_editor_url: string;
  name_only: string;
  current_pictures_count: number;
  specifications_count: number;
  brands: APIItem[];
  public_urls?: string[];

  has_text?: boolean;

  preview_pictures: {
    picture: APIPicture;
    url: string;
  }[];

  engine_vehicles?: [
    {
      url: string;
      name_html: string;
    }
  ];

  tuning?: {
    url: string;
    count: number;
  };
  sport?: {
    url: string;
    count: number;
  };
  more_pictures_url?: string;
  total_pictures?: number;
}

export interface APIItemRelatedGroupItem {
  name: string;
  src: string;
  url: string;
}

export interface GetItemServiceOptions {
  fields?: string;
}

export interface GetItemsServiceOptions {
  id?: number;
  fields: string;
  type_id?: number;
  parent_id?: number;
  order?: string;
  is_group?: boolean;
  limit: number;
  name?: string | null;
  name_exclude?: string | null;
  dateless?: boolean;
  page?: number;
  have_childs_of_type?: number;
  autocomplete?: string;
  vehicle_type_id?: number;
  vehicle_childs_type_id?: number;
  spec?: number;
  no_parent?: boolean;
  text?: string;
  from_year?: number;
  to_year?: number;
  ancestor_id?: number;
  suggestions_to?: number;
  engine_id?: number;
  have_common_childs_with?: number;
  have_childs_with_parent_of_type?: number;
  last_item?: boolean;
  related_groups_of?: number;
  catname?: string;
  exclude_self_and_childs?: number;
  parent_types_of?: number;
  descendant_pictures?: {
    status?: string;
    type_id?: number;
    owner_id?: number;
  };
  preview_pictures?: {
    type_id?: number;
  };
}

@Injectable()
export class ItemService {
  constructor(private http: HttpClient) {}

  public setItemVehicleTypes(itemId: number, ids: number[]): Observable<void> {
    return this.http
      .get<APIItemVehicleTypeGetResponse>('/api/item-vehicle-type', {
        params: {
          item_id: itemId.toString()
        }
      })
      .pipe(
        switchMap(response => {
          const promises: Observable<void>[] = [];

          const currentIds: number[] = [];
          for (const itemVehicleType of response.items) {
            currentIds.push(itemVehicleType.vehicle_type_id);
            if (!ids.includes(itemVehicleType.vehicle_type_id)) {
              promises.push(
                this.http.delete<void>(
                  '/api/item-vehicle-type/' +
                    itemId +
                    '/' +
                    itemVehicleType.vehicle_type_id
                )
              );
            }
          }

          for (const vehicleTypeId of ids) {
            if (!currentIds.includes(vehicleTypeId)) {
              promises.push(
                this.http.post<void>(
                  '/api/item-vehicle-type/' + itemId + '/' + vehicleTypeId,
                  {}
                )
              );
            }
          }

          if (promises.length <= 0) {
            promises.push(of(null));
          }

          return forkJoin(...promises);
        })
      );
  }

  public getItemByLocation(
    url: string,
    options: GetItemServiceOptions
  ): Observable<APIItem> {
    return this.http.get<APIItem>(url, {
      params: this.convertItemOptions(options)
    });
  }

  public getItem(
    id: number,
    options?: GetItemServiceOptions
  ): Observable<APIItem> {
    if (!id) {
      return of(null as APIItem);
    }
    return this.getItemByLocation('/api/item/' + id, options);
  }

  private convertItemOptions(
    options: GetItemServiceOptions
  ): { [param: string]: string } {
    const params: { [param: string]: string } = {};

    if (!options) {
      options = {};
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    return params;
  }

  private converItemsOptions(
    options: GetItemsServiceOptions
  ): { [param: string]: string } {
    const params: { [param: string]: string } = {};

    if (options.id) {
      params.id = options.id.toString();
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.type_id) {
      params.type_id = options.type_id.toString();
    }

    if (options.parent_id) {
      params.parent_id = options.parent_id.toString();
    }

    if (options.order) {
      params.order = options.order;
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    if (options.name) {
      params.name = options.name;
    }

    if (options.name_exclude) {
      params.name_exclude = options.name_exclude;
    }

    if (options.dateless) {
      params.dateless = '1';
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.have_childs_of_type) {
      params.have_childs_of_type = options.have_childs_of_type.toString();
    }

    if (options.name) {
      params.name = options.name;
    }

    if (options.autocomplete) {
      params.autocomplete = options.autocomplete;
    }

    if (options.vehicle_type_id) {
      params.vehicle_type_id = options.vehicle_type_id.toString();
    }

    if (options.vehicle_childs_type_id) {
      params.vehicle_childs_type_id = options.vehicle_childs_type_id.toString();
    }

    if (options.spec) {
      params.spec = options.spec.toString();
    }

    if (options.no_parent) {
      params.no_parent = '1';
    }

    if (options.text) {
      params.text = options.text;
    }

    if (options.from_year) {
      params.from_year = options.from_year.toString();
    }

    if (options.to_year) {
      params.to_year = options.to_year.toString();
    }

    if (options.ancestor_id) {
      params.ancestor_id = options.ancestor_id.toString();
    }

    if (options.suggestions_to) {
      params.suggestions_to = options.suggestions_to.toString();
    }

    if (options.engine_id) {
      params.engine_id = options.engine_id.toString();
    }

    if (options.exclude_self_and_childs) {
      params.exclude_self_and_childs = options.exclude_self_and_childs.toString();
    }

    if (options.parent_types_of) {
      params.parent_types_of = options.parent_types_of.toString();
    }

    if (options.is_group) {
      params.is_group = '1';
    }

    if (options.have_common_childs_with) {
      params.have_common_childs_with = options.have_common_childs_with.toString();
    }

    if (options.have_childs_with_parent_of_type) {
      params.have_childs_with_parent_of_type = options.have_childs_with_parent_of_type.toString();
    }

    if (options.last_item) {
      params.last_item = '1';
    }

    if (options.related_groups_of) {
      params.related_groups_of = options.related_groups_of.toString();
    }

    if (options.catname) {
      params.catname = options.catname;
    }

    if (options.descendant_pictures) {
      if (options.descendant_pictures.status) {
        params['descendant_pictures[status]'] =
          options.descendant_pictures.status;
      }

      if (options.descendant_pictures.type_id) {
        params[
          'descendant_pictures[type_id]'
        ] = options.descendant_pictures.type_id.toString();
      }

      if (options.descendant_pictures.owner_id) {
        params[
          'descendant_pictures[owner_id]'
        ] = options.descendant_pictures.owner_id.toString();
      }
    }

    if (options.preview_pictures) {
      if (options.preview_pictures.type_id) {
        params[
          'preview_pictures[type_id]'
        ] = options.preview_pictures.type_id.toString();
      }
    }

    return params;
  }

  public getItems(
    options?: GetItemsServiceOptions
  ): Observable<APIItemsGetResponse> {
    return this.http.get<APIItemsGetResponse>('/api/item', {
      params: this.converItemsOptions(options)
    });
  }
}
