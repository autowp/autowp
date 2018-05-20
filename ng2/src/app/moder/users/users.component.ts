import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { UserService, APIUser } from '../../services/user';
import { APIPaginator } from '../../services/api.service';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-moder-users',
  templateUrl: './users.component.html'
})
@Injectable()
export class ModerUsersComponent {
  public paginator: APIPaginator;
  public loading = 0;
  public users: APIUser[] = [];
  private page = 1;

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/203/name',
          pageId: 203
        }),
      0
    );

    this.load();
  }

  private load() {
    this.loading++;
    this.users = [];

    this.userService
      .get({
        page: this.page,
        limit: 30,
        fields: 'image,reg_date,last_online,email,login'
      })
      .subscribe(
        response => {
          this.users = response.items;
          this.paginator = response.paginator;
          this.loading--;
        },
        response => {
          console.log(response);
          this.loading--;
        }
      );
  }
}
