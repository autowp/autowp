import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../../services/api.service';
import { ItemService, APIItem } from '../../../services/item';
import { Subscription, empty, combineLatest, of, forkJoin } from 'rxjs';
import { ActivatedRoute, Params } from '@angular/router';
import { PageEnvService } from '../../../services/page-env.service';
import { switchMap, map } from 'rxjs/operators';

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
  public char: string;
  private querySub: Subscription;
  public loading = 0;
  public paginator: APIPaginator | null = null;
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
    this.querySub = combineLatest(
      this.route.queryParams,
      this.http.get<APIItemAlphaGetResponse>('/api/item/alpha'),
      (query: Params, groups: APIItemAlphaGetResponse) => ({
        groups,
        query
      })
    )
      .pipe(
        switchMap(data => {
          if (!data.query.char) {
            return of({
              char: null,
              groups: data.groups.groups,
              items: {
                items: [],
                paginator: null
              }
            });
          }
          return forkJoin(
            of(data.groups.groups),
            this.itemService.getItems({
              name: data.query.char + '%',
              page: data.query.page,
              limit: 10,
              fields: 'name_html'
            })
          ).pipe(
            map(data2 => {
              return {
                char: data.query.char,
                groups: data2[0],
                items: data2[1]
              };
            })
          );
        })
      )
      .subscribe(data => {
        this.char = data.char;
        this.groups = data.groups;
        this.paginator = data.items.paginator;
        this.items = data.items.items;
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
