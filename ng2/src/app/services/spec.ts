import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

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
  private types: APISpec[];
  private typesInitialized = false;

  constructor(private http: HttpClient) {}

  public getSpecs(): Promise<APISpec[]> {
    return new Promise<APISpec[]>((resolve, reject) => {
      if (this.typesInitialized) {
        resolve(this.types);
        return;
      }
      this.http.get<APISpecGetResponse>('/api/spec').subscribe(
        response => {
          this.types = response.items;
          this.typesInitialized = true;
          resolve(this.types);
        },
        response => reject(response)
      );
    });
  }

  public getSpec(id: number): Promise<APISpec> {
    return new Promise<APISpec>((resolve, reject) => {
      this.getSpecs().then(
        (types: APISpec[]) => {
          const spec = this.findSpec(types, id);
          if (spec) {
            resolve(spec);
          } else {
            reject();
          }
        },
        () => reject()
      );
    });
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
