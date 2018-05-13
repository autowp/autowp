import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../services/api.service';
import Notify from '../../../notify';
import { ItemService, APIItem } from '../../../services/item';
import { Router, ActivatedRoute } from '@angular/router';
import { UserService, APIUser } from '../../../services/user';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../../services/page-env.service';

interface APIItemInList extends APIItem {
  cssClass?: string;
}

@Component({
  selector: 'app-users-user-pictures',
  templateUrl: './pictures.component.html'
})
@Injectable()
export class UsersUserPicturesComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public user: APIUser;
  public paginator: APIPaginator;
  public brands: APIItemInList[];
  public identity: string;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private router: Router,
    private userService: UserService,
    private route: ActivatedRoute,
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
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public init() {
    this.identity = this.user.identity
      ? this.user.identity
      : 'user' + this.user.id;

    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/63/name',
      pageId: 63,
      args: {
        USER_NAME: this.user.name,
        USER_IDENTITY: this.identity
      }
    });

    this.itemService
      .getItems({
        type_id: 5,
        limit: 500,
        order: 'name_nat',
        fields: 'name_only,catname,current_pictures_count',
        descendant_pictures: {
          status: 'accepted',
          owner_id: this.user.id
        }
      })
      .subscribe(
        response => {
          this.brands = response.items;
          for (const item of this.brands) {
            item.cssClass = item.catname.replace(/\./g, '_');
          }
        },
        response => {
          Notify.response(response);
        }
      );
  }
}
