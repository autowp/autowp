import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

export interface APIContentLanguageGetResponse {
  items: string[];
}

@Injectable()
export class ContentLanguageService {
  private cache: string[];

  constructor(private http: HttpClient) {}

  public getList(): Promise<string[]> {
    return new Promise<string[]>((resolve, reject) => {
      if (this.cache) {
        resolve(this.cache);
        return;
      }

      this.http
        .get<APIContentLanguageGetResponse>('/api/content-language')
        .subscribe(
          response => {
            this.cache = response.items;
            resolve(this.cache);
          },
          () => reject()
        );
    });
  }
}
