import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { APIItem } from './item';

export interface APIPictureItemAreaPostData {
  left: number;
  top: number;
  width: number;
  height: number;
}

export interface APIPictureItemPostData {
  perspective_id?: number;
}

export interface APIPictureItemGetOptions {
  fields: string;
}

export interface APIPictureItemsGetOptions {
  item_id: number;
  limit: number;
  fields: string;
  order: string;
}

export interface APIPictureItemsGetResponse {
  items: APIPictureItem[];
}

export interface APIPictureItem {
  picture_id: number;
  item_id: number;
  type: number;
  perspective_id: number;
  item: APIItem;
  area: {
    left: number;
    top: number;
    width: number;
    height: number;
  };
}

@Injectable()
export class PictureItemService {
  constructor(private http: HttpClient) {}

  public setPerspective(
    pictureId: number,
    itemId: number,
    type: number,
    perspectiveId: number
  ): Observable<void> {
    return this.http.put<void>(
      '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
      {
        perspective_id: perspectiveId.toString()
      }
    );
  }

  public setArea(
    pictureId: number,
    itemId: number,
    type: number,
    area: APIPictureItemAreaPostData
  ): Observable<void> {
    return this.http.put<void>(
      '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
      {
        area: area
      }
    );
  }

  public create(
    pictureId: number,
    itemId: number,
    type: number,
    data: APIPictureItemPostData
  ): Observable<void> {
    return this.http.post<void>(
      '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
      data
    );
  }

  public remove(
    pictureId: number,
    itemId: number,
    type: number
  ): Observable<void> {
    return this.http.delete<void>(
      '/api/picture-item/' + pictureId + '/' + itemId + '/' + type
    );
  }

  public changeItem(
    pictureId: number,
    type: number,
    srcItemId: number,
    dstItemId: number
  ): Observable<void> {
    return this.http.put<void>(
      '/api/picture-item/' + pictureId + '/' + srcItemId + '/' + type,
      {
        item_id: dstItemId
      }
    );
  }

  public get(
    pictureId: number,
    itemId: number,
    type: number,
    options: APIPictureItemGetOptions
  ): Observable<APIPictureItem> {
    const params: { [param: string]: string } = {};

    if (options.fields) {
      params.fields = options.fields;
    }

    return this.http.get<APIPictureItem>(
      '/api/picture-item/' + pictureId + '/' + itemId + '/' + type,
      {
        params: params
      }
    );
  }

  public getItems(
    options: APIPictureItemsGetOptions
  ): Observable<APIPictureItemsGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    if (options.limit) {
      params.limit = options.limit.toString();
    }

    if (options.fields) {
      params.fields = options.fields;
    }

    if (options.order) {
      params.order = options.order;
    }

    return this.http.get<APIPictureItemsGetResponse>('/api/picture-item', {
      params: params
    });
  }
}
