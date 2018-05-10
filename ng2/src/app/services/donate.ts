import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface APIDonateCarOfDayDate {
  name: string;
  value: string;
  free: boolean;
}

export interface APIDonateVODGetResponse {
  sum: number;
  dates: APIDonateCarOfDayDate[];
}

@Injectable()
export class DonateService {
  constructor(private http: HttpClient) {}

  public getVOD(): Observable<APIDonateVODGetResponse> {
    return this.http.get<APIDonateVODGetResponse>('/api/donate/vod');
  }
}
