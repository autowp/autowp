import {inject, Injectable} from '@angular/core';
import {DeleteModerVoteRequest, UpdateModerVoteRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {Observable} from 'rxjs';

@Injectable({
  providedIn: 'root',
})
export class PictureModerVoteService {
  readonly #picturesClient = inject(PicturesClient);

  public vote$(pictureId: string, vote: number, reason: string): Observable<Empty> {
    return this.#picturesClient.updateModerVote(new UpdateModerVoteRequest({pictureId, reason, vote}));
  }

  public cancel$(pictureId: string): Observable<Empty> {
    return this.#picturesClient.deleteModerVote(new DeleteModerVoteRequest({pictureId}));
  }
}
