import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ItemService, APIItem } from '../../../services/item';

// Acl.inheritsRole('moder', 'unauthorized');

@Component({
  selector: 'app-moder-items-too-big',
  templateUrl: './too-big.component.html'
})
@Injectable()
export class ModerItemsTooBigComponent {
  public loading = false;
  public items: APIItem[];

  constructor(private http: HttpClient, private itemService: ItemService) {
    this.loading = true;
    /*this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            }
        });*/

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
