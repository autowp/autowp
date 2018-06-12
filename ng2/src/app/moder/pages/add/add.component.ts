import { Component, Injectable } from '@angular/core';
import { HttpClient, HttpResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { PageService, APIPage, APIPageLinearized } from '../../../services/page';
import { PageEnvService } from '../../../services/page-env.service';

// Acl.inheritsRole('pages-moder', 'unauthorized');

@Component({
  selector: 'app-moder-pages-add',
  templateUrl: './add.component.html'
})
@Injectable()
export class ModerPagesAddComponent {
  public loading = 0;
  public item: any = {};
  public pages: APIPageLinearized[];

  constructor(
    private http: HttpClient,
    private router: Router,
    private pageService: PageService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/69/name',
          pageId: 69
        }),
      0
    );

    this.pageService.getPages().subscribe(response => {
      this.pages = this.pageService.toPlainArray(response.items, 0);
    });
  }

  public save() {
    this.loading++;
    this.http
      .post<void>(
        '/api/page',
        {
          parent_id: this.item.parent_id,
          name: this.item.name,
          title: this.item.title,
          breadcrumbs: this.item.breadcrumbs,
          url: this.item.url,
          is_group_node: this.item.is_group_node ? 1 : 0,
          registered_only: this.item.registered_only ? 1 : 0,
          guest_only: this.item.guest_only ? 1 : 0,
          class: this.item['class']
        },
        {
          observe: 'response'
        }
      )
      .subscribe(
        response => {
          this.loading--;

          this.loading++;
          this.http.get<APIPage>(response.headers.get('Location')).subscribe(
            page => {
              this.loading--;
              this.router.navigate(['/moder/pages/edit'], {
                queryParams: {
                  id: page.id
                }
              });
            },
            () => {
              this.loading--;
            }
          );
        },
        () => {
          this.loading--;
        }
      );
  }
}
