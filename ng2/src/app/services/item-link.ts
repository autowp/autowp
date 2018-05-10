import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export interface APIItemLink {
  id: number;
  url: string;
  name: string;
  type_id: number;
}

export interface APIItemLinkGetResponse {
  items: APIItemLink[];
}

export interface APIItemLinkGetItemsOptions {
  item_id: number;
}

@Injectable()
export class ItemLinkService {
  constructor(private http: HttpClient) {}

  public getItems(
    options: APIItemLinkGetItemsOptions
  ): Observable<APIItemLinkGetResponse> {
    const params: { [param: string]: string } = {};

    if (options.item_id) {
      params.item_id = options.item_id.toString();
    }

    return this.http.get<APIItemLinkGetResponse>('/api/item-link', {
      params: params
    });
  }
}
