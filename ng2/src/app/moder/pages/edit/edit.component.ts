import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { ActivatedRoute, Router } from '@angular/router';
import {
  PageService,
  APIPageLinearized,
  APIPage
} from '../../../services/page';
import { PageEnvService } from '../../../services/page-env.service';
import { switchMap, distinctUntilChanged, debounceTime } from 'rxjs/operators';

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
    private pageService: PageService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/70/name',
          pageId: 70
        }),
      0
    );

    this.pageService.getPages().subscribe(response => {
      this.pages = this.pageService.toPlainArray(response.items, 0);
    });

    this.routeSub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params => this.pageService.getPage(params.id))
      )
      .subscribe(
        response => (this.item = response),
        () => {
          this.router.navigate(['/error-404']);
        }
      );
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
