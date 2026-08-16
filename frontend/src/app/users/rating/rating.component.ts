import type {OnInit} from '@angular/core';
import type {
  User,
  UserRatingBrandsResponse,
  UsersRatingResponse,
  UsersRatingUser,
  UsersRatingUserFan,
} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {DecimalPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {UserRatingDetailsRequest} from '@grpc/spec.pb';
import {RatingClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {errorMessage} from 'app/grpc';
import {catchError, forkJoin, map, of} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

enum Rating {
  COMMENT_LIKES = 'likes',
  PICTURE_LIKES = 'picture-likes',
  PICTURES = 'pictures',
  SPECS = 'specs',
}

// Only the top N rows expand into per-user brands/fans detail in the template.
const EXPANDED_ROWS_COUNT = 10;

interface RatingRow {
  userId: string;
  volume: string;
  weight: number;
}

@Component({
  selector: 'app-users-rating',
  imports: [RouterLink, UserComponent, DecimalPipe],
  templateUrl: './rating.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersRatingComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #ratingClient = inject(RatingClient);
  readonly #userService = inject(UserService);
  readonly #languageService = inject(LanguageService);

  protected readonly rating = toSignal(
    this.#route.paramMap.pipe(
      map((params) => {
        switch (params.get('rating')) {
          case Rating.COMMENT_LIKES:
          case Rating.PICTURE_LIKES:
          case Rating.PICTURES:
          case Rating.SPECS:
            return params.get('rating') as Rating;
          default:
            return Rating.SPECS;
        }
      }),
    ),
    {requireSync: true},
  );

  protected readonly valueTitle = computed(() => {
    switch (this.rating()) {
      case Rating.COMMENT_LIKES:
        return $localize`Likes`;
      case Rating.PICTURE_LIKES:
        return $localize`Picture likes`;
      case Rating.PICTURES:
        return $localize`Pictures`;
      case Rating.SPECS:
        return $localize`Specs volume`;
    }
  });

  protected readonly usersResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    // Suffixed with the rating route param read once at construction time - a static id would
    // let a second instance of this component, created by navigating away and to a different
    // rating page before Angular's whenStable() ever resolves, match TransferState's
    // still-present entry from the first rating and seed itself with the wrong data. Every other
    // resource below is downstream of `rating`/`usersResource` and gets the same treatment.
    id: `users-rating-${this.rating()}`,
    params: () => this.rating(),
    stream: ({params: rating}) => {
      let o$: Observable<UsersRatingResponse>;
      switch (rating) {
        case Rating.COMMENT_LIKES:
          o$ = this.#ratingClient.getUserCommentsRating(new Empty());
          break;
        case Rating.PICTURE_LIKES:
          o$ = this.#ratingClient.getUserPictureLikesRating(new Empty());
          break;
        case Rating.PICTURES:
          o$ = this.#ratingClient.getUserPicturesRating(new Empty());
          break;
        case Rating.SPECS:
          o$ = this.#ratingClient.getUserSpecsRating(new Empty());
          break;
      }
      return o$.pipe(map((response) => (response.users ?? []).map((user) => this.#mapUser(user))));
    },
  });

  #mapUser(user: UsersRatingUser): RatingRow {
    return {
      userId: user.userId,
      volume: user.volume,
      weight: user.weight,
    };
  }

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, so the chained resources/computeds below don't blow up on a non-NOT_FOUND
  // usersResource error (surfaced generically by the template instead).
  protected readonly usersData = computed(() =>
    this.usersResource.hasValue() ? this.usersResource.value() : undefined,
  );

  // Per-item lookups here used to be raw Observables created inside #mapUser and consumed via
  // `| async` in the template. That races Angular's SSR whenStable() check (the outer resource's
  // pending task completes before the template's deferred change-detection pass ever subscribes
  // to the nested Observables), so the data can silently be missing from SSR output. Chaining
  // resources off usersResource keeps every lookup inside Angular's pending-task tracking.
  protected readonly usersByIdResource = rxResource({
    id: `users-rating-users-${this.rating()}`,
    params: () => this.usersData()?.map((row) => row.userId) ?? [],
    // A plain object rather than a Map: TransferState round-trips resource values through
    // JSON.stringify/JSON.parse for hydration, and Map instances serialize to '{}' (no own
    // enumerable properties, no toJSON), losing all entries.
    stream: ({params: userIds}): Observable<Record<string, User>> => {
      if (userIds.length === 0) {
        return of({});
      }
      return this.#userService.getUserMap$(userIds).pipe(
        map((userMap) => Object.fromEntries(userMap)),
        // getUserMap$ throws if the backend can't find a requested user. Degrade to showing no
        // user rather than erroring the whole resource over one stale reference.
        catchError(() => of({})),
      );
    },
  });

  readonly #topUserIds = computed(
    () =>
      this.usersData()
        ?.slice(0, EXPANDED_ROWS_COUNT)
        .map((row) => row.userId) ?? [],
  );

  protected readonly brandsResource = rxResource({
    id: `users-rating-brands-${this.rating()}`,
    params: () => ({rating: this.rating(), userIds: this.#topUserIds()}),
    stream: ({params: {rating, userIds}}): Observable<Record<string, UserRatingBrandsResponse>> => {
      if (userIds.length === 0 || (rating !== Rating.PICTURES && rating !== Rating.SPECS)) {
        return of({});
      }
      const requests = userIds.map((userId) => {
        const request = new UserRatingDetailsRequest({language: this.#languageService.language, userId});
        const response$ =
          rating === Rating.PICTURES
            ? this.#ratingClient.getUserPicturesRatingBrands(request)
            : this.#ratingClient.getUserSpecsRatingBrands(request);

        return response$.pipe(map((response) => [userId, response] as const));
      });

      return forkJoin(requests).pipe(
        map((entries) => Object.fromEntries(entries)),
        catchError(() => of({})),
      );
    },
  });

  protected readonly fansResource = rxResource({
    id: `users-rating-fans-${this.rating()}`,
    params: () => ({rating: this.rating(), userIds: this.#topUserIds()}),
    stream: ({params: {rating, userIds}}): Observable<Record<string, UsersRatingUserFan[]>> => {
      if (userIds.length === 0 || (rating !== Rating.PICTURE_LIKES && rating !== Rating.COMMENT_LIKES)) {
        return of({});
      }
      const requests = userIds.map((userId) => {
        const request = new UserRatingDetailsRequest({userId});
        const response$ =
          rating === Rating.PICTURE_LIKES
            ? this.#ratingClient.getUserPictureLikesRatingFans(request)
            : this.#ratingClient.getUserCommentsRatingFans(request);

        return response$.pipe(map((response) => [userId, response.fans ?? []] as const));
      });

      return forkJoin(requests).pipe(
        map((entries) => Object.fromEntries(entries)),
        catchError(() => of({})),
      );
    },
  });

  protected readonly fansUsersResource = rxResource({
    id: `users-rating-fans-users-${this.rating()}`,
    params: () => {
      const fans = this.fansResource.value();
      if (!fans) {
        return [];
      }
      return [...new Set(Object.values(fans).flatMap((list) => list.map((fan) => fan.userId)))];
    },
    stream: ({params: userIds}): Observable<Record<string, User>> => {
      if (userIds.length === 0) {
        return of({});
      }
      return this.#userService.getUserMap$(userIds).pipe(
        map((userMap) => Object.fromEntries(userMap)),
        catchError(() => of({})),
      );
    },
  });

  protected readonly Rating = Rating;
  protected readonly EXPANDED_ROWS_COUNT = EXPANDED_ROWS_COUNT;
  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 173});
  }
}
