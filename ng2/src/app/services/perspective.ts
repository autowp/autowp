import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

export interface APIPerspective {
  id: number;
  name: string;
}

export interface APIPerspectiveGetResponse {
  items: APIPerspective[];
}

@Injectable()
export class PerspectiveService {
  private promise: Promise<APIPerspective[]> | null = null;
  private perspectives: APIPerspective[];
  private perspectivesInitialized = false;

  constructor(private http: HttpClient) {}

  public getPerspectives(): Promise<APIPerspective[]> {
    if (this.promise) {
      return this.promise;
    }

    this.promise = new Promise<APIPerspective[]>((resolve, reject) => {
      if (this.perspectivesInitialized) {
        resolve(this.perspectives);
        return;
      }

      this.http.get<APIPerspectiveGetResponse>('/go-api/perspective').subscribe(
        response => {
          this.perspectives = response.items;
          this.perspectivesInitialized = true;
          resolve(this.perspectives);
          this.promise = null;
        },
        () => {
          reject();
          this.promise = null;
        }
      );
    });

    return this.promise;
  }
}
