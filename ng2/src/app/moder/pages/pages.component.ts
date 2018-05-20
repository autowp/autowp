import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../services/acl.service';
import { PageService, APIPage, APIPageLinearized } from '../../services/page';
import { PageEnvService } from '../../services/page-env.service';

// Acl.inheritsRole('pages-moder', 'unauthorized');

@Component({
  selector: 'app-moder-pages',
  templateUrl: './pages.component.html'
})
@Injectable()
export class ModerPagesComponent {
  public items: APIPageLinearized[] = [];
  public canManage = false;

  constructor(
    private http: HttpClient,
    private acl: ACLService,
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
          name: 'page/68/name',
          pageId: 68
        }),
      0
    );

    this.acl.isAllowed('hotlinks', 'manage').then(
      allow => {
        this.canManage = !!allow;
      },
      () => {
        this.canManage = false;
      }
    );

    this.load();
  }

  private load() {
    this.pageService.getPages().subscribe(response => {
      this.items = this.pageService.toPlainArray(response.items, 0);
    });
  }

  public move(page: APIPage, direction: any) {
    this.http
      .put<void>('/api/page/' + page.id, {
        position: direction
      })
      .subscribe(response => {
        this.load();
      });
  }

  public deletePage(ev: any, page: APIPage) {
    if (window.confirm('Would you like to delete page?')) {
      this.http.delete('/api/page/' + page.id).subscribe(response => {
        this.load();
      });
    }
  }
}
