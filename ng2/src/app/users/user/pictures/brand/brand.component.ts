import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../../services/api.service';
import Notify from '../../../../notify';
import { ItemService, APIItem } from '../../../../services/item';
import { UserService, APIUser } from '../../../../services/user';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { PictureService, APIPicture } from '../../../../services/picture';
import { PageEnvService } from '../../../../services/page-env.service';

@Component({
  selector: 'app-users-user-pictures-brand',
  templateUrl: './brand.component.html'
})
@Injectable()
export class UsersUserPicturesBrandComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  private querySub: Subscription;
  public user: APIUser;
  public pictures: APIPicture[];
  public paginator: APIPaginator;
  public brand: APIItem;
  public identity: string;
  private brandCatname: string;
  private page: number;

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
    this.routeSub = this.route.params.subscribe(params => {
      const result = params.identity.match(/^user([0-9]+)$/);

      if (result) {
        this.userService
          .getUser(result[1], {
            fields: 'identity'
          })
          .then(
            response => {
              this.user = response;
              this.init();
            },
            response => {
              Notify.response(response);
            }
          );
      } else {
        this.userService
          .get({
            identity: params.identity,
            limit: 1,
            fields: 'identity'
          })
          .subscribe(
            response => {
              if (response.items.length <= 0) {
                this.router.navigate(['/error-404']);
                return;
              }
              this.user = response.items[0];
              this.init();
            },
            response => {
              Notify.response(response);
            }
          );
      }
    });
    this.querySub = this.route.queryParams.subscribe(params => {
      this.brandCatname = params.brand;
      this.page = params.page;
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.querySub.unsubscribe();
  }

  public init() {
    if (this.user.deleted) {
      this.router.navigate(['/error-404']);
      return;
    }

    this.identity = this.user.identity
      ? this.user.identity
      : 'user' + this.user.id;

    this.itemService
      .getItems({
        type_id: 5,
        limit: 1,
        catname: this.brandCatname,
        fields: 'name_only,catname'
      })
      .subscribe(
        response => {
          if (response.items.length <= 0) {
            this.router.navigate(['/error-404']);
            return;
          }
          this.brand = response.items[0];

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/141/name',
            pageId: 141,
            args: {
              USER_NAME: this.user.name,
              USER_IDENTITY: this.identity,
              BRAND_NAME: this.brand.name_only,
              BRAND_CATNAME: this.brand.catname
            }
          });

          this.pictureService
            .getPictures({
              status: 'accepted',
              fields:
                'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
              limit: 30,
              page: this.page,
              item_id: this.brand.id,
              owner_id: this.user.id,
              order: 1
            })
            .subscribe(
              subresponse => {
                this.pictures = subresponse.pictures;
                this.paginator = subresponse.paginator;
              },
              subresponse => {
                Notify.response(subresponse);
              }
            );
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
