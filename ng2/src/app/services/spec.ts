import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, shareReplay } from 'rxjs/operators';

export interface APISpecGetResponse {
  items: APISpec[];
}

export interface APISpec {
  id: number;
  name: string;
  short_name: string;
  childs: APISpec[];
}

@Injectable()
export class SpecService {
  private specs$: Observable<APISpec[]>;

  constructor(private http: HttpClient) {
    this.specs$ = this.http.get<APISpecGetResponse>('/api/spec').pipe(
      map(response => response.items),
      shareReplay(1)
    );
  }

  public getSpecs(): Observable<APISpec[]> {
    return this.specs$;
  }

  public getSpec(id: number): Observable<APISpec> {
    return this.getSpecs().pipe(map(specs => this.findSpec(specs, id)));
  }

  private findSpec(specs: APISpec[], id: number): APISpec | null {
    let spec = null;
    for (let i = 0; i < specs.length; i++) {
      if (specs[i].id === id) {
        spec = specs[i];
        break;
      }
      spec = this.findSpec(specs[i].childs, id);
      if (spec) {
        break;
      }
    }
    return spec;
  }
}
