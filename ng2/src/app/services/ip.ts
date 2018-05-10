import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIUser } from './user';

export interface APIIP {
  address: string;
  hostname: string;
  blacklist: {
    reason: string;
    up_to: string;
    user: APIUser;
  };
  whitelist: {
    reason: string;
  };
  rights: {
    add_to_blacklist: boolean;
    remove_from_blacklist: boolean;
  };
}

@Injectable()
export class IpService {
  private hostnames: Map<string, string> = new Map<string, string>();

  constructor(private http: HttpClient) {}

  public getHostByAddr(ip: string): Promise<string> {
    return new Promise<string>((resolve, reject) => {
      const hostname = this.hostnames.get(ip);
      if (hostname !== undefined) {
        resolve(hostname);
        return;
      }

      this.http
        .get<APIIP>('/api/ip/' + ip, {
          params: {
            fields: 'hostname'
          }
        })
        .subscribe(
          response => {
            this.hostnames.set(ip, response.hostname);
            resolve(response.hostname);
          },
          response => reject(response)
        );
    });
  }
}
