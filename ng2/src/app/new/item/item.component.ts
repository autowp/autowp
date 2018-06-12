import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as moment from 'moment';
import { APIPaginator } from '../../services/api.service';
import { APIItem, ItemService } from '../../services/item';
import Notify from '../../notify';
import { Subscription, combineLatest, empty } from 'rxjs';
import { PictureService, APIPicture } from '../../services/picture';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import {
  debounceTime,
  distinctUntilChanged,
  switchMap,
  catchError
} from 'rxjs/operators';

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
    private itemService: ItemService,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(
          params => {
            return combineLatest(
              this.itemService
                .getItem(params.item_id, {
                  fields: 'name_html,name_text'
                })
                .pipe(
                  catchError((err, caught) => {
                    if (err.status !== -1) {
                      Notify.response(err);
                    }
                    return empty();
                  })
                ),
              this.route.queryParams.pipe(
                distinctUntilChanged(),
                debounceTime(30),
                switchMap(query =>
                  this.pictureService.getPictures({
                    fields:
                      'owner,thumb_medium,moder_vote,votes,views,comments_count,name_html,name_text',
                    limit: 24,
                    status: 'accepted',
                    accept_date: params.date,
                    item_id: params.item_id,
                    page: query.page
                  })
                ),
                catchError((err, caught) => {
                  if (err.status !== -1) {
                    Notify.response(err);
                  }
                  return empty();
                })
              ),
              (item, pictures) => ({ item, pictures })
            );
          },
          (params, data) => ({
            params: params,
            item: data.item,
            pictures: data.pictures
          })
        )
      )
      .subscribe(data => {
        this.item = data.item;

        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/210/name',
          pageId: 210,
          args: {
            DATE: moment(data.params.date).format('LL'),
            DATE_STR: data.params.date,
            ITEM_NAME: this.item.name_text
          }
        });

        this.pictures = data.pictures.pictures;
        this.paginator = data.pictures.paginator;
      });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
