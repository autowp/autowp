import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';

@Injectable()
export class PictureModerVoteService {
  constructor(private http: HttpClient) {}

  public vote(pictureId: number, vote: number, reason: string): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http
        .put<void>('/api/picture-moder-vote/' + pictureId, {
          vote: vote,
          reason: reason
        })
        .subscribe(() => resolve(), () => reject());
    });
  }

  public cancel(pictureId: number): Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.http
        .delete('/api/picture-moder-vote/' + pictureId)
        .subscribe(() => resolve(), () => reject());
    });
  }
}
