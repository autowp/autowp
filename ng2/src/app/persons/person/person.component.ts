import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem } from '../../services/item';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription, empty, combineLatest, of } from 'rxjs';
import Notify from '../../notify';
import { PictureService, APIPicture } from '../../services/picture';
import { ItemLinkService, APIItemLink } from '../../services/item-link';
import { APIACL } from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';
import {
  distinctUntilChanged,
  debounceTime,
  switchMap,
  catchError,
  tap
} from 'rxjs/operators';

@Component({
  selector: 'app-persons-person',
  templateUrl: './person.component.html'
})
@Injectable()
export class PersonsPersonComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public links: APIItemLink[] = [];
  public authorPictures: APIPicture[] = [];
  public authorPicturesPaginator: APIPaginator;
  public contentPictures: APIPicture[] = [];
  public contentPicturesPaginator: APIPaginator;
  public item: APIItem;
  public isModer = false;
  private aclSub: Subscription;

  constructor(
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private itemLinkService: ItemLinkService,
    private acl: APIACL,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .inheritsRole('moder')
      .subscribe(isModer => (this.isModer = isModer));

    this.routeSub = this.route.params
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params =>
          this.itemService.getItem(params.id, {
            fields: ['name_text', 'name_html', 'description'].join(',')
          })
        ),
        catchError((err, caught) => {
          Notify.response(err);
          this.router.navigate(['/error-404']);
          return empty();
        }),
        tap(item => {
          if (item.item_type_id !== 8) {
            this.router.navigate(['/error-404']);
            return;
          }

          this.pageEnv.set({
            layout: {
              needRight: false
            },
            name: 'page/213/name',
            pageId: 213,
            args: {
              PERSON_ID: item.id + '',
              PERSON_NAME: item.name_text
            }
          });
        }),
        switchMap(
          item => this.route.queryParams,
          (item, params) => ({ item, params })
        ),
        switchMap(
          data =>
            combineLatest(
              this.itemLinkService
                .getItems({
                  item_id: data.item.id
                })
                .pipe(
                  catchError((err, caught) => {
                    Notify.response(err);
                    return of(null);
                  })
                ),
              this.pictureService
                .getPictures({
                  status: 'accepted',
                  exact_item_id: data.item.id,
                  exact_item_link_type: 2,
                  fields:
                    'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                  limit: 24,
                  order: 12,
                  page: data.params.page
                })
                .pipe(
                  catchError((err, caught) => {
                    Notify.response(err);
                    return of(null);
                  })
                ),
              this.pictureService
                .getPictures({
                  status: 'accepted',
                  exact_item_id: data.item.id,
                  exact_item_link_type: 1,
                  fields:
                    'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                  limit: 24,
                  order: 12,
                  page: data.params.page
                })
                .pipe(
                  catchError((err, caught) => {
                    Notify.response(err);
                    return of(null);
                  })
                )
            ),
          (data, responses) => ({
            item: data.item,
            links: responses[0].items,
            authorPicures: responses[1],
            contentPictures: responses[2]
          })
        )
      )

      .subscribe(data => {
        this.item = data.item;
        this.links = data.links;
        this.authorPictures = data.authorPicures.pictures;
        this.authorPicturesPaginator = data.authorPicures.paginator;
        this.contentPictures = data.contentPictures.pictures;
        this.contentPicturesPaginator = data.contentPictures.paginator;
      });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
    this.aclSub.unsubscribe();
  }
}
