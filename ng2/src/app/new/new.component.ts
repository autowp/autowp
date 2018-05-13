import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import { chunkBy } from '../chunk';
import Notify from '../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { APIPicture } from '../services/picture';
import { PageEnvService } from '../services/page-env.service';

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
    this.routeSub = this.route.params.subscribe(params => {
      this.date = params.date;

      this.pageEnv.set({
        layout: {
          needRight: false
        },
        name: 'page/51/name',
        pageId: 51
      });

      this.http
        .get<APINewGetResponse>('/api/new', {
          params: {
            date: params.date,
            page: params.page,
            fields:
              'pictures.owner,pictures.thumb_medium,pictures.votes,pictures.views,' +
              'pictures.comments_count,pictures.name_html,pictures.name_text,' +
              'item_pictures.thumb_medium,item_pictures.name_html,item_pictures.name_text,' +
              'item.name_html,item.name_default,item.description,item.produced,' +
              'item.design,item.url,item.spec_editor_url,item.specs_url,' +
              'item.categories.url,item.categories.name_html,item.twins_groups'
          }
        })
        .subscribe(
          response => {
            this.paginator = response.paginator;
            this.prev = response.prev;
            this.current = response.current;
            this.next = response.next;
            this.groups = [];

            const repackedGroups: any = [];
            for (const group of response.groups) {
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

            if (params.date !== this.current.date) {
              this.router.navigate(['/new', this.current.date]);
              return;
            }
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
