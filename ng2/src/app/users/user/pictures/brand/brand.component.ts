import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../../services/api.service';
import Notify from '../../../../notify';
import { ItemService, APIItem } from '../../../../services/item';
import { UserService, APIUser } from '../../../../services/user';
import { Router, ActivatedRoute, Params } from '@angular/router';
import { Subscription, combineLatest } from 'rxjs';
import { PictureService, APIPicture } from '../../../../services/picture';
import { PageEnvService } from '../../../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  tap,
  map
} from 'rxjs/operators';

@Component({
  selector: 'app-users-user-pictures-brand',
  templateUrl: './brand.component.html'
})
@Injectable()
export class UsersUserPicturesBrandComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public pictures: APIPicture[];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private userService: UserService,
    private router: Router,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = combineLatest(
      this.route.params.pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => {
          return combineLatest(
            this.userService.getByIdentity(params.identity, {
              fields: 'identity'
            }),
            this.itemService
              .getItems({
                type_id: 5,
                limit: 1,
                catname: params.brand,
                fields: 'name_only,catname'
              })
              .pipe(
                map(
                  response => (response.items.length ? response.items[0] : null)
                )
              ),
            (user: APIUser, brand: APIItem) => ({
              user,
              brand
            })
          );
        }),
        tap(data => {
          if (data.user.deleted) {
            this.router.navigate(['/error-404']);
            return;
          }

          const identity = data.user.identity
            ? data.user.identity
            : 'user' + data.user.id;

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/141/name',
            pageId: 141,
            args: {
              USER_NAME: data.user.name,
              USER_IDENTITY: identity,
              BRAND_NAME: data.brand.name_only,
              BRAND_CATNAME: data.brand.catname
            }
          });
        })
      ),
      this.route.queryParams.pipe(
        distinctUntilChanged(),
        debounceTime(30)
      ),
      (route, query: Params) => ({
        route,
        query
      })
    )
      .pipe(
        switchMap(data =>
          this.pictureService.getPictures({
            status: 'accepted',
            fields:
              'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
            limit: 30,
            page: data.query.page,
            item_id: data.route.brand.id,
            owner_id: data.route.user.id,
            order: 1
          })
        )
      )
      .subscribe(
        response => {
          this.pictures = response.pictures;
          this.paginator = response.paginator;
        },
        subresponse => {
          Notify.response(subresponse);
        }
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
