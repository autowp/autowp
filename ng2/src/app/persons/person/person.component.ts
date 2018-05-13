import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem } from '../../services/item';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import Notify from '../../notify';
import { PictureService, APIPicture } from '../../services/picture';
import { ItemLinkService, APIItemLink } from '../../services/item-link';
import { APIACL } from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';

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
    this.acl
      .inheritsRole('moder')
      .then(isModer => (this.isModer = isModer), () => (this.isModer = false));

    this.routeSub = this.route.params.subscribe(params => {
      this.itemService
        .getItem(params.id, {
          fields: ['name_text', 'name_html', 'description'].join(',')
        })
        .subscribe(
          (item: APIItem) => {
            this.item = item;

            if (this.item.item_type_id !== 8) {
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
                PERSON_ID: this.item.id + '',
                PERSON_NAME: this.item.name_text
              }
            });

            this.itemLinkService
              .getItems({
                item_id: this.item.id
              })
              .subscribe(
                response => {
                  this.links = response.items;
                },
                response => {
                  Notify.response(response);
                }
              );

            this.pictureService
              .getPictures({
                status: 'accepted',
                exact_item_id: this.item.id,
                exact_item_link_type: 2,
                fields:
                  'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                limit: 24,
                order: 12,
                page: params.page
              })
              .subscribe(
                response => {
                  this.authorPictures = response.pictures;
                  this.authorPicturesPaginator = response.paginator;
                },
                response => {
                  Notify.response(response);
                }
              );

            this.pictureService
              .getPictures({
                status: 'accepted',
                exact_item_id: this.item.id,
                exact_item_link_type: 1,
                fields:
                  'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
                limit: 24,
                order: 12,
                page: params.page
              })
              .subscribe(
                response => {
                  this.contentPictures = response.pictures;
                  this.contentPicturesPaginator = response.paginator;
                },
                response => {
                  Notify.response(response);
                }
              );
          },
          () => {
            this.router.navigate(['/error-404']);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
