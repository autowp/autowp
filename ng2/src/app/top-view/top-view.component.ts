import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';

@Component({
  selector: 'app-top-view',
  templateUrl: './top-view.component.html'
})
@Injectable()
export class TopViewComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public pictures: APIPicture[] = [];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private pictureService: PictureService
  ) {}

  ngOnInit(): void {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/201/name',
      pageId: 201
    });*/
    this.querySub = this.route.queryParams.subscribe(params => {
      this.pictureService
        .getPictures({
          status: 'accepted',
          fields:
            'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
          limit: 18,
          page: params.page,
          perspective_id: 18,
          order: 15
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
    this.querySub.unsubscribe();
  }
}
