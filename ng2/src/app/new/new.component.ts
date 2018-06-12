import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import { chunkBy } from '../chunk';
import Notify from '../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { APIPicture } from '../services/picture';
import { PageEnvService } from '../services/page-env.service';
import { debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';

export interface APINewGroup {
  type: string;
  pictures: APIPicture[];
}

export interface APINewGetResponse {
  paginator: APIPaginator;
  prev: {
    date: string;
    count: number;
  };
  next: {
    date: string;
    count: number;
  };
  current: {
    date: string;
    count: number;
  };
  groups: APINewGroup[];
}

@Component({
  selector: 'app-new',
  templateUrl: './new.component.html'
})
@Injectable()
export class NewComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public paginator: APIPaginator;
  public groups: any[] = [];
  public date: string;
  public prev: {
    date: string;
    count: number;
  };
  public next: {
    date: string;
    count: number;
  };
  public current: {
    date: string;
    count: number;
  };

  constructor(
    private http: HttpClient,
    private router: Router,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          params => {
            const query: {
              date?: string;
              page?: string;
              fields: string;
            } = {
              fields:
                'pictures.owner,pictures.thumb_medium,pictures.votes,pictures.views,' +
                'pictures.comments_count,pictures.name_html,pictures.name_text,' +
                'item_pictures.thumb_medium,item_pictures.name_html,item_pictures.name_text,' +
                'item.name_html,item.name_default,item.description,item.produced,' +
                'item.design,item.url,item.spec_editor_url,item.specs_url,' +
                'item.categories.url,item.categories.name_html,item.twins_groups'
            };
            if (params.date) {
              query.date = params.date;
            }
            if (params.page) {
              query.page = params.page;
            }
            return this.http.get<APINewGetResponse>('/api/new', {
              params: query
            });
          },
          (params, response) => ({ params, response })
        )
      )
      .subscribe(
        data => {
          if (data.params.date !== data.response.current.date) {
            this.router.navigate(['/new', data.response.current.date]);
            return;
          }

          this.date = data.params.date;

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/51/name',
            pageId: 51
          });

          this.paginator = data.response.paginator;
          this.prev = data.response.prev;
          this.current = data.response.current;
          this.next = data.response.next;
          this.groups = [];

          const repackedGroups: any = [];
          for (const group of data.response.groups) {
            let repackedGroup: any;

            switch (group.type) {
              case 'item':
                repackedGroup = group;
                break;
              case 'pictures':
                repackedGroup = {
                  type: group.type,
                  chunks: chunkBy(group.pictures, 6)
                };
                break;
            }

            repackedGroups.push(repackedGroup);
          }
          this.groups = repackedGroups;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
