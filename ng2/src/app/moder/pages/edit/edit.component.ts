import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import {
  PageService,
  APIPageLinearized,
  APIPage
} from '../../../services/page';

// Acl.inheritsRole('pages-moder', 'unauthorized');

@Component({
  selector: 'app-moder-pages-edit',
  templateUrl: './edit.component.html'
})
@Injectable()
export class ModerPagesEditComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public item: APIPage = null;
  public loading = 0;
  public pages: APIPageLinearized[];

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router,
    private pageService: PageService
  ) {}

  ngOnInit(): void {
    /*$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/70/name',
            pageId: 70
        });*/

    this.pageService.getPages().subscribe(response => {
      this.pages = this.pageService.toPlainArray(response.items, 0);
    });

    this.routeSub = this.route.params.subscribe(params => {
      this.pageService.getPage(params.id).subscribe(
        response => (this.item = response),
        () => {
          this.router.navigate(['/error-404']);
        }
      );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }

  public save() {
    this.loading++;

    this.http
      .put<void>('/api/page/' + this.item.id, {
        parent_id: this.item.parent_id,
        name: this.item.name,
        title: this.item.title,
        breadcrumbs: this.item.breadcrumbs,
        url: this.item.url,
        is_group_node: this.item.is_group_node ? 1 : 0,
        registered_only: this.item.registered_only ? 1 : 0,
        guest_only: this.item.guest_only ? 1 : 0,
        class: this.item['class']
      })
      .subscribe(
        response => {
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }
}
