import type {Empty} from '@ngx-grpc/well-known-types';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {DeleteModerVoteRequest, UpdateModerVoteRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';

@Service()
export class PictureModerVoteService {
  readonly #picturesClient = inject(PicturesClient);

  public vote$(pictureId: string, vote: number, reason: string): Observable<Empty> {
    return this.#picturesClient.updateModerVote(new UpdateModerVoteRequest({pictureId, reason, vote}));
  }

  public cancel$(pictureId: string): Observable<Empty> {
    return this.#picturesClient.deleteModerVote(new DeleteModerVoteRequest({pictureId}));
  }
}
