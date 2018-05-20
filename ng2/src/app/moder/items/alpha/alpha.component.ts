import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../services/api.service';
import { ItemService, APIItem } from '../../../services/item';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../../services/page-env.service';

// Acl.inheritsRole('moder', 'unauthorized');

export interface APIItemAlphaGetResponse {
  groups: string[][];
}

@Component({
  selector: 'app-moder-items-alpha',
  templateUrl: './alpha.component.html'
})
@Injectable()
export class ModerItemsAlphaComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public loading = 0;
  public paginator: APIPaginator | null = null;
  public page: number | null = null;
  public groups: string[][];
  public items: APIItem[];

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/74/name',
          pageId: 74
        }),
      0
    );
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.page = params.page;

      this.http
        .get<APIItemAlphaGetResponse>('/api/item/alpha')
        .subscribe(response => {
          this.groups = response.groups;
        });

      if (params.char) {
        this.loadChar(params.char);
      }
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public selectChar(char: string) {
    this.page = null;

    /*this.$state.go(
      STATE_NAME,
      {
        char: char,
        page: this.page
      },
      {
        notify: false,
        reload: false,
        location: 'replace'
      }
    );*/

    this.loadChar(char);
  }

  public loadChar(char: string) {
    this.paginator = null;
    this.items = [];
    this.loading++;

    this.itemService
      .getItems({
        name: char + '%',
        page: this.page,
        limit: 500,
        fields: 'name_html'
      })
      .subscribe(
        response => {
          this.paginator = response.paginator;
          this.items = response.items;
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }
}
