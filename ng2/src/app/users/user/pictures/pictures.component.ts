import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import Notify from '../../../notify';
import { ItemService, APIItem } from '../../../services/item';
import { ActivatedRoute } from '@angular/router';
import { UserService, APIUser } from '../../../services/user';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../../services/page-env.service';
import { switchMap, tap } from 'rxjs/operators';

@Component({
  selector: 'app-users-user-pictures',
  templateUrl: './pictures.component.html'
})
@Injectable()
export class UsersUserPicturesComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public brands: APIItem[];
  public identity: string;

  constructor(
    private itemService: ItemService,
    private userService: UserService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params
      .pipe(
        switchMap(params =>
          this.userService.getByIdentity(params.identity, {
            fields: 'identity'
          })
        ),
        tap(user => {
          this.identity = user.identity ? user.identity : 'user' + user.id;
          setTimeout(
            () =>
              this.pageEnv.set({
                layout: {
                  needRight: false
                },
                name: 'page/63/name',
                pageId: 63,
                args: {
                  USER_NAME: user.name,
                  USER_IDENTITY: this.identity
                }
              }),
            0
          );
        }),
        switchMap(user =>
          this.itemService.getItems({
            type_id: 5,
            limit: 500,
            order: 'name_nat',
            fields: 'name_only,catname,current_pictures_count',
            descendant_pictures: {
              status: 'accepted',
              owner_id: user.id
            }
          })
        )
      )
      .subscribe(
        response => (this.brands = response.items),
        response => Notify.response(response)
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public cssClass(item: APIItem) {
    return item.catname.replace(/\./g, '_');
  }
}
