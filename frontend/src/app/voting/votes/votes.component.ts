import type {User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {VotingRequest} from '@grpc/spec.pb';
import {VotingsClient} from '@grpc/spec.pbsc';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {UserService} from '@services/user';
import {catchError, combineLatest, EMPTY, map, of, switchMap} from 'rxjs';

import {ToastsService} from '../../toasts/toasts.service';
import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-voting-votes',
  imports: [UserComponent, AsyncPipe],
  templateUrl: './votes.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class VotingVotesComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #toastService = inject(ToastsService);
  readonly #votingClient = inject(VotingsClient);
  readonly #userService = inject(UserService);

  readonly votingID = input.required<number>();
  readonly variantID = input.required<number>();

  protected readonly votes$: Observable<Observable<null | User>[]> = combineLatest([
    toObservable(this.votingID),
    toObservable(this.variantID),
  ]).pipe(
    switchMap(([votingID, variantID]) =>
      votingID && variantID ? this.#votingClient.getVotingVariantVotes(new VotingRequest({id: variantID})) : of(null),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((response) => (response?.userIds ?? []).map((id) => this.#userService.getUser$(id))),
  );
}
