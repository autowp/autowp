import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {VotingRequest} from '@grpc/spec.pb';
import {VotingsClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {UserService} from '@services/user';
import {map, of, switchMap, tap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserLink} from '../../user/user/user-link';

@Component({
  selector: 'app-voting-votes',
  imports: [UserLink],
  templateUrl: './voting-votes.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VotingVotes {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #toastService = inject(ToastsService);
  readonly #votingClient = inject(VotingsClient);
  readonly #userService = inject(UserService);

  readonly votingID = input.required<number>();
  readonly variantID = input.required<number>();

  protected readonly votesResource = rxResource({
    params: () => ({votingID: this.votingID(), variantID: this.variantID()}),
    stream: ({params}) =>
      params.votingID && params.variantID
        ? this.#votingClient.getVotingVariantVotes(new VotingRequest({id: params.variantID})).pipe(
            switchMap((response) => {
              const ids = response.userIds;
              return this.#userService.getUserMap$(ids).pipe(
                map((users) =>
                  ids.flatMap((id) => {
                    const user = users.get(id);
                    return user ? [user] : [];
                  }),
                ),
              );
            }),
            tap({
              error: (error: unknown) => {
                this.#toastService.handleError(error);
              },
            }),
          )
        : of([]),
  });
}
