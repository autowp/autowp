import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Subscription, empty } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  tap,
  switchMap,
  catchError,
  finalize
} from 'rxjs/operators';

export interface APIRatingUser {
  user: APIUser;
  brands: {
    url: string;
    name: string;
  }[];
  fans: {
    volume: number;
    user: APIUser;
  }[];
  volume: number;
  weight: number;
}

export interface APIUsersRatingGetResponse {
  users: APIRatingUser[];
}

@Component({
  selector: 'app-users-rating',
  templateUrl: './rating.component.html'
})
@Injectable()
export class UsersRatingComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public rating: string;
  public loading = 0;
  public valueTitle: string;
  public users: APIRatingUser[];

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/173/name',
          pageId: 173
        }),
      0
    );

    this.routeSub = this.route.params
      .pipe(
        debounceTime(30),
        distinctUntilChanged(),
        switchMap(params => {
          this.rating = params.rating || 'specs';

          switch (this.rating) {
            case 'specs':
              this.valueTitle = 'users/rating/specs-volume';
              break;
            case 'pictures':
              this.valueTitle = 'users/rating/pictures';
              break;
            case 'likes':
              this.valueTitle = 'users/rating/likes';
              break;
            case 'picture-likes':
              this.valueTitle = 'users/rating/picture-likes';
              break;
          }

          this.loading++;
          return this.http
            .get<APIUsersRatingGetResponse>('/api/rating/' + this.rating)
            .pipe(
              finalize(() => {
                this.loading--;
              }),
              catchError((err, caught) => {
                Notify.response(err);
                return empty();
              })
            );
        })
      )
      .subscribe(response => {
        this.users = response.users;
      });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
