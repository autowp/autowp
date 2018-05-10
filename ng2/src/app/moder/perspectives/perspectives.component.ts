import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPerspectivePage, APIPerspectivePageGetResponse } from '../../services/api.service';

@Component({
  selector: 'app-moder-perspectives',
  templateUrl: './perspectives.component.html'
})
@Injectable()
export class ModerPerspectivesComponent {
  public pages: APIPerspectivePage[];

  constructor(private http: HttpClient) {
    /*this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/202/name',
            pageId: 202
        });*/

    this.http
      .get<APIPerspectivePageGetResponse>('/api/perspective-page', {
        params: {
          fields: 'groups.perspectives'
        }
      })
      .subscribe(
        response => {
          this.pages = response.items;
        },
        response => console.log(response)
      );
  }
}
