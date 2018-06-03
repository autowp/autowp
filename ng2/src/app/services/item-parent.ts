import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from './api.service';
import { APIItem } from './item';

export interface APIItemParentGetResponse {
  items: APIItemParent[];
  paginator: APIPaginator;
}

export interface APIItemParent {
  item_id: number;
  parent_id: number;
  type_id: number;
  item: APIItem;
  expanded?: boolean; // TODO: remove
  duplicate_parent?: APIItem;
  duplicate_child?: APIItem;
  parent?: APIItem;
  name?: string;
}

export interface APIItemParentGetItemsOptions {
  item_id?: number;
  item_type_id?: number;
  parent_id?: number;
  fields: string;
  limit: number;
  page?: number;
  concept?: boolean;
  ancestor_id?: number;
  type_id?: number;
  order?: string;
  is_group?: boolean;
  exclude_concept?: boolean;
}

@Injectable()
export class ItemParentService {
  constructor(private http: HttpClient) {}

  public getItems(
    options: APIItemParentGetItemsOptions
  ): Observable<APIItemParentGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.item_type_id) {
      params.item_type_id = options.item_type_id.toString();
    }

    if (options.parent_id) {
      params.parent_id = options.parent_id.toString();
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    if (options.page) {
      params.page = options.page.toString();
    }

    if (options.concept) {
      params.concept = '1';
    }

    if (options.ancestor_id) {
      params.ancestor_id = options.ancestor_id.toString();
    }

    if (options.type_id) {
      params.type_id = options.type_id.toString();
    }

    if (options.order) {
      params.order = options.order;
    }

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.is_group) {
      params.is_group = '1';
    }

    if (options.exclude_concept) {
      params.exclude_concept = '1';
    }

    return this.http.get<APIItemParentGetResponse>('/api/item-parent', {
      params: params
    });
  }
}
