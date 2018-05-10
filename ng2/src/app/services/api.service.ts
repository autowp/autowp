import { Injectable } from '@angular/core';
import {
  HttpClient,
  HttpResponse,
  HttpErrorResponse,
  HttpHeaders
} from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { APIPicture } from './picture';

export class APIAPIClient {
  constructor(
    public client_id: string,
    public client_secret: string,
    public redirect_uri: string
  ) {}
}

export interface APIItemParentLanguageGetResponse {
  items: APIItemParentLanguage[];
}

export interface APIItemParentLanguage {
  language: string;
  name: string;
}

export interface APIItemVehicleType {
  vehicle_type_id: number;
}

export interface APIItemVehicleTypeGetResponse {
  items: APIItemVehicleType[];
}

export interface APIHotlinksHost {
  host: string;
  count: number;
  whitelisted: boolean;
  blacklisted: boolean;
  links: any[];
}

export interface APIHotlinksHostsGetResponse {
  items: APIHotlinksHost[];
}

export interface APIImage {
  src: string;
  width: number;
  height: number;
}

export interface APILanguage {
  language: string;
  name: string;
}

export interface APIPaginator {
  pageCount: number;
  itemCountPerPage: number;
  first: number;
  current: number;
  last: number;
  next: number;
  previous: number;
  pagesInRange: {[key: number]: number};
  firstPageInRange: number;
  lastPageInRange: number;
  currentItemCount: number;
  totalItemCount: number;
  firstItemNumber: number;
  lastItemNumber: number;
}

export interface APIPerspective {
  id: number;
  name: string;
}

export interface APIPerspectiveGroup {
  id: number;
  name: string;
  groups: APIPerspective[];
}

export interface APIPerspectivePage {
  id: number;
  name: string;
  groups: APIPerspectiveGroup[];
}

export interface APIPerspectivePageGetResponse {
  items: APIPerspectivePage[];
}

export interface APIPicturesGetResponse {
  paginator: APIPaginator;
  pictures?: APIPicture[];
}

export interface APILoginService {
  name: string;
  icon: string;
}

export interface APILoginServices {
  [key: string]: APIService;
}

export interface APILoginServicesGetResponse {
  items: APILoginServices;
}

export interface APILoginStartPostResponse {
  url: string;
}

@Injectable()
export class APIService {
  [x: string]: any;
  constructor(private http: HttpClient) {}

  public request<R>(
    method: string,
    url: string,
    options?: any
  ): Observable<any> {
    if (!options) {
      options = {
        withCredentials: true
      };
    } else {
      options.withCredentials = true;
    }
    return this.http.request<R>(method, environment.apiUrl + url, options);
  }
}
