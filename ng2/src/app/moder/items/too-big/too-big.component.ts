import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ItemService, APIItem } from '../../../services/item';
import { PageEnvService } from '../../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

@Component({
  selector: 'app-moder-items-too-big',
  templateUrl: './too-big.component.html'
})
@Injectable()
export class ModerItemsTooBigComponent {
  public loading = false;
  public items: APIItem[];

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private pageEnv: PageEnvService
  ) {
    this.loading = true;
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          }
        }),
      0
    );

    this.itemService
      .getItems({
        order: 'childs_count',
        limit: 100,
        fields: 'childs_count,name_html'
      })
      .subscribe(
        response => {
          this.items = response.items;
          this.loading = false;
        },
        () => {
          this.loading = false;
        }
      );
  }
}
