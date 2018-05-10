import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface APIReCaptchaGetResponse {
  publicKey: string;
  success: boolean;
}

@Injectable()
export class ReCaptchaService {
  constructor(private http: HttpClient) {}

  public get(): Observable<APIReCaptchaGetResponse> {
    return this.http.get<APIReCaptchaGetResponse>('/api/recaptcha');
  }
}
