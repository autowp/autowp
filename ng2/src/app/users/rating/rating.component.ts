import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { APIUser } from '../../services/user';
import { PageEnvService } from '../../services/page-env.service';

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
    this.pageEnv.set({
      layout: {
        needRight: true
      },
      name: 'page/173/name',
      pageId: 173
    });

    this.routeSub = this.route.params.subscribe(params => {
      this.rating = params.rating || 'specs-volume';

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
      this.http
        .get<APIUsersRatingGetResponse>('/api/rating/' + this.rating)
        .subscribe(
          response => {
            this.loading--;
            this.users = response.users;
          },
          response => {
            this.loading--;
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
