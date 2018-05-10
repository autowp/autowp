import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as moment from 'moment';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { APIItem, ItemService } from '../../services/item';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { PictureService, APIPicture } from '../../services/picture';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-new-item',
  templateUrl: './item.component.html'
})
@Injectable()
export class NewItemComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public paginator: APIPaginator;
  public pictures: APIPicture[];
  public item: APIItem;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private pictureService: PictureService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.itemService
        .getItem(params.item_id, {
          fields: 'name_html,name_text'
        })
        .subscribe(
          (item: APIItem) => {
            this.item = item;

            /*this.$scope.pageEnv({
          layout: {
            blankPage: false,
            needRight: false
          },
          name: 'page/210/name',
          pageId: 210,
          args: {
            DATE: moment(params.date).format('LL'),
            DATE_STR: params.date,
            ITEM_NAME: this.item.name_text
          }
        });*/
          },
          response => {
            Notify.response(response);
          }
        );

      this.pictureService
        .getPictures({
          fields:
            'owner,thumb_medium,moder_vote,votes,views,comments_count,name_html,name_text',
          limit: 24,
          status: 'accepted',
          accept_date: params.date,
          item_id: params.item_id,
          page: params.page
        })
        .subscribe(
          response => {
            this.pictures = response.pictures;
            this.paginator = response.paginator;
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
