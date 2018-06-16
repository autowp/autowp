import { APIService } from './api.service';
import { Injectable } from '@angular/core';
import { AuthService } from './auth.service';
import { Observable, of, observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { catchError, map, shareReplay } from 'rxjs/operators';

export interface APIACLRule {
  role: string;
  resource: string;
  privilege: string;
  allowed: boolean;
}

export interface APIACLRulesGetResponse {
  items: APIACLRule[];
}

export interface APIACLResource {
  name: string;
  privileges: {
    name: string;
  }[];
}

export interface APIACLResourcesGetResponse {
  items: APIACLResource[];
}

interface APIACLIsAllowedResponse {
  result: boolean;
}

interface APIACLIsAllowed {
  result: boolean;
}

export interface APIACLRole {
  name: string;
  childs: APIACLRole[];
}

export interface APIACLRoles {
  items: APIACLRole[];
}

@Injectable()
export class APIACL {
  constructor(private api: APIService, private http: HttpClient) {}

  public isAllowed(resource: string, privilege: string): Observable<boolean> {
    return this.api
      .request<APIACLIsAllowed>('GET', 'acl/is-allowed', {
        params: {
          resource: resource,
          privilege: privilege
        }
      })
      .pipe(
        map(response => response.result),
        catchError(err => {
          return of(false);
        }),
        shareReplay(1)
      );
  }

  public inheritsRole(role: string): Observable<boolean> {
    return this.api
      .request('GET', 'acl/inherit-roles', {
        params: {
          roles: role
        }
      })
      .pipe(
        map(response => response[role]),
        catchError(err => {
          return of(false);
        }),
        shareReplay(1)
      );
  }

  public getRoles(recursive: boolean): Observable<APIACLRoles> {
    return this.http.get<APIACLRoles>('/api/acl/roles', {
      params: {
        recursive: recursive ? '1' : ''
      }
    });
  }

  public getResources(): Observable<APIACLResourcesGetResponse> {
    return this.http.get<APIACLResourcesGetResponse>('/api/acl/resources');
  }

  public getRules(): Observable<APIACLRulesGetResponse> {
    return this.http.get<APIACLRulesGetResponse>('/api/acl/rules');
  }
}

@Injectable()
export class ACLService {
  private cache = new Map<string, Observable<boolean>>();
  private isAllowedCache = new Map<string, Observable<boolean>>();

  constructor(private apiACL: APIACL, private auth: AuthService) {
    this.auth.getUser().subscribe(() => {
      this.flush();
    });
  }

  public isAllowed(resource: string, privilege: string): Observable<boolean> {
    const key = resource + '/' + privilege;

    if (this.isAllowedCache.has(key)) {
      return this.isAllowedCache.get(key);
    }

    const o = this.apiACL.isAllowed(resource, privilege);
    this.isAllowedCache.set(key, o);
    return o;
  }

  public inheritsRole(role: string): Observable<boolean> {
    if (this.cache.has(role)) {
      return this.cache.get(role);
    }

    const o = this.apiACL.inheritsRole(role);
    this.cache.set(role, o);
    return o;
  }

  public flush() {
    this.cache.clear();
    this.isAllowedCache.clear();
  }
}
