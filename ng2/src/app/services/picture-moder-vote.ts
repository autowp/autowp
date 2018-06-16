import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable()
export class PictureModerVoteService {
  constructor(private http: HttpClient) {}

  public vote(
    pictureId: number,
    vote: number,
    reason: string
  ): Observable<void> {
    return this.http.put<void>('/api/picture-moder-vote/' + pictureId, {
      vote: vote,
      reason: reason
    });
  }

  public cancel(pictureId: number): Observable<void> {
    return this.http.delete<void>('/api/picture-moder-vote/' + pictureId);
  }
}
