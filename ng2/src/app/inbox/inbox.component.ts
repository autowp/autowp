import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import Notify from '../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { Subscription } from 'rxjs';
import { PictureService, APIPicture } from '../services/picture';
import { InboxService } from '../services/inbox';
import { APIItem } from '../services/item';

const ALL_BRANDS = 'all';

// url: '/inbox/:brand/:date/:page',

@Component({
  selector: 'app-inbox',
  templateUrl: './inbox.component.html'
})
@Injectable()
export class InboxComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public pictures: APIPicture[] = [];
  public paginator: APIPaginator;
  public brand_id = 0;
  public current: {
    date: string;
    count: number;
  };
  public prev: {
    date: string;
    count: number;
  } | null;
  public next: {
    date: string;
    count: number;
  } | null;
  public brands: APIItem[];

  constructor(
    private http: HttpClient,
    private router: Router,
    private auth: AuthService,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private inboxService: InboxService
  ) {}

  ngOnInit(): void {
    if (!this.auth.user) {
      this.router.navigate(['/signin']);
      return;
    }

    this.routeSub = this.route.params.subscribe(params => {
      if (!params.brand) {
        this.router.navigate(['/inbox', ALL_BRANDS]);
        return;
      }

      if (params.brand === ALL_BRANDS) {
        this.brand_id = 0;
      } else {
        this.brand_id = params.brand ? parseInt(params.brand, 10) : 0;
      }

      /*this.$scope.pageEnv({
        layout: {
          blankPage: false,
          needRight: false
        },
        name: 'page/76/name',
        pageId: 76
      });*/

      this.inboxService.get(this.brand_id, params.date, params.page)
        .subscribe(
          response => {
            this.paginator = response.paginator;
            this.prev = response.prev;
            this.current = response.current;
            this.next = response.next;
            this.brands = response.brands;

            if (params.date !== this.current.date) {
              this.router.navigate([
                '/inbox',
                this.brand_id,
                this.current.date
              ]);
              return;
            }

            this.pictureService
              .getPictures({
                status: 'inbox',
                fields:
                  'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                limit: 30,
                page: params.page,
                item_id: this.brand_id,
                add_date: this.current.date,
                order: 1
              })
              .subscribe(
                subresponse => {
                  this.pictures = subresponse.pictures;
                  this.paginator = subresponse.paginator;
                },
                subresponse => {
                  Notify.response(subresponse);
                }
              );
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

  public changeBrand() {
    this.router.navigate(['/inbox', this.brand_id]);
  }
}
