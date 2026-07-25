import {AsyncPipe, DecimalPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIUser,
  APIUsersRatingResponse,
  APIUsersRatingUser,
  UserRatingBrandsResponse,
  UserRatingDetailsRequest,
} from '@grpc/spec.pb';
import {RatingClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {Observable} from 'rxjs';
import {map} from 'rxjs/operators';

import {UserComponent} from '../../user/user/user.component';

enum Rating {
  COMMENT_LIKES = 'likes',
  PICTURE_LIKES = 'picture-likes',
  PICTURES = 'pictures',
  SPECS = 'specs',
}

@Component({
  selector: 'app-users-rating',
  imports: [RouterLink, UserComponent, AsyncPipe, DecimalPipe],
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

  private getRatingFans$(rating: Rating, userId: string) {
    if (rating == Rating.PICTURE_LIKES) {
      return this.#ratingClient.getUserPictureLikesRatingFans(new UserRatingDetailsRequest({userId})).pipe(
        map((response) =>
          (response.fans ? response.fans : []).map((fan) => ({
            user$: this.#userService.getUser$(fan.userId),
            volume: fan.volume,
          })),
        ),
      );
    }
    if (rating == Rating.COMMENT_LIKES) {
      return this.#ratingClient.getUserCommentsRatingFans(new UserRatingDetailsRequest({userId})).pipe(
        map((response) =>
          (response.fans ? response.fans : []).map((fan) => ({
            user$: this.#userService.getUser$(fan.userId),
            volume: fan.volume,
          })),
        ),
      );
    }
    return null;
  }

  private getRatingBrands$(rating: Rating, userId: string) {
    if (rating == Rating.PICTURES) {
      return this.#ratingClient.getUserPicturesRatingBrands(
        new UserRatingDetailsRequest({language: this.#languageService.language, userId}),
      );
    }
    if (rating == Rating.SPECS) {
      return this.#ratingClient.getUserSpecsRatingBrands(
        new UserRatingDetailsRequest({language: this.#languageService.language, userId}),
      );
    }
    return null;
  }

  protected readonly usersResource = rxResource({
    stream: () => {
      const rating = this.rating();
      let o$: Observable<APIUsersRatingResponse>;
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
      return o$.pipe(
        map((response) => (response.users ? response.users : []).map((user) => this.#mapUser(rating, user))),
      );
    },
  });

  #mapUser(
    rating: Rating,
    user: APIUsersRatingUser,
  ): {
    brands$: null | Observable<UserRatingBrandsResponse>;
    fans$: null | Observable<{user$: Observable<APIUser | null>; volume: string}[]>;
    user$: Observable<APIUser | null>;
    volume: string;
    weight: number;
  } {
    return {
      brands$: this.getRatingBrands$(rating, user.userId),
      fans$: this.getRatingFans$(rating, user.userId),
      user$: this.#userService.getUser$(user.userId),
      volume: user.volume,
      weight: user.weight,
    };
  }

  protected readonly Rating = Rating;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 173});
  }
}
