import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import {
  APIBrandsGetResponse,
  APIBrandsLines
} from '../services/brands.service';

// import { BrandPopover } from '../brand-popover';

@Component({
  selector: 'app-brands',
  templateUrl: './brands.component.html'
})
@Injectable()
export class BrandsComponent {
  public items: APIBrandsLines;

  constructor(private http: HttpClient) {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/61/name',
      pageId: 61
    });*/

    this.http.get<APIBrandsGetResponse>('/api/brands').subscribe(
      response => {
        this.items = response.items;
        Object.entries(this.items).forEach((line, key) => {
          for (const info of line) {
            for (const item of info.brands) {
              item.cssClass = item.catname.replace(/\./g, '_');
            }
          }
        });
        // BrandPopover.apply('.popover-handler');
      },
      response => {
        Notify.response(response);
      }
    );
  }
}
