import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

export interface APIItemLanguage {
  language: string;
  name: string;
  text: string;
  full_text: string;
  text_id: number;
  full_text_id: number;
}

export interface APIItemLanguageGetResponse {
  items: APIItemLanguage[];
}

@Injectable()
export class ItemLanguageService {
  constructor(private http: HttpClient) {}

  public getItems(itemId: number): Observable<APIItemLanguageGetResponse> {
    return this.http.get<APIItemLanguageGetResponse>(
      '/api/item/' + itemId + '/language'
    );
  }
}
