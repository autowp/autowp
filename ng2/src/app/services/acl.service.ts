import { APIService } from './api.service';
import { Injectable } from '@angular/core';
import { AuthService } from './auth.service';
import { Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';

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

  public isAllowed(resource: string, privilege: string): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      this.api
        .request<APIACLIsAllowed>('GET', 'acl/is-allowed', {
          params: {
            resource: resource,
            privilege: privilege
          }
        })
        .subscribe(
          response => {
            resolve(response.result);
          },
          response => {
            reject();
          }
        );
    });
  }

  public inheritsRole(role: string): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      this.api
        .request('GET', 'acl/inherit-roles', {
          params: {
            roles: role
          }
        })
        .subscribe(
          response => {
            resolve(response[role]);
          },
          response => {
            reject();
          }
        );
    });
  }

  public getRoles(recursive: boolean): Promise<APIACLRoles> {
    return new Promise<APIACLRoles>((resolve, reject) => {
      this.http
        .get<APIACLRoles>('/api/acl/roles', {
          params: {
            recursive: recursive ? '1' : ''
          }
        })
        .subscribe(
          response => {
            resolve(response);
          },
          error => {
            reject();
          }
        );
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
  private cache: Map<string, boolean> = new Map<string, boolean>();
  private isAllowedCache: Map<string, boolean> = new Map<string, boolean>();

  constructor(private apiACL: APIACL, private auth: AuthService) {
    this.auth.getUser().subscribe(() => {
      this.flush();
    });
  }

  public isAllowed(resource: string, privilege: string): Promise<boolean> {
    return new Promise<boolean>((resolve, reject) => {
      const key = resource + '/' + privilege;

      if (!this.isAllowedCache.has(key)) {
        this.apiACL.isAllowed(resource, privilege).then(
          result => {
            this.isAllowedCache.set(key, result);
            resolve(result);
          },
          () => {
            this.isAllowedCache.set(key, false);
            reject();
          }
        );
      } else {
        resolve(this.isAllowedCache.get(key));
      }
    });
  }

  public inheritsRole(role: string) {
    return new Promise<boolean>((resolve, reject) => {
      if (!this.cache.has(role)) {
        this.apiACL.inheritsRole(role).then(
          result => {
            this.cache.set(role, result);
            resolve(result);
          },
          () => {
            this.cache.set(role, false);
            reject();
          }
        );
      } else {
        resolve(this.cache.get(role));
      }
    });
  }

  public flush() {
    this.cache.clear();
    this.isAllowedCache.clear();
  }
}
