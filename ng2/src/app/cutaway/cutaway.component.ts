import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../services/api.service';
import Notify from '../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PictureService, APIPicture } from '../services/picture';
import { PageEnvService } from '../services/page-env.service';
import { switchMap } from 'rxjs/operators';

@Component({
  selector: 'app-cutaway',
  templateUrl: './cutaway.component.html'
})
@Injectable()
export class CutawayComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public pictures: APIPicture[] = [];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/109/name',
          pageId: 109
        }),
      0
    );
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams
      .pipe(
        switchMap(params =>
          this.pictureService.getPictures({
            status: 'accepted',
            fields:
              'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
            limit: 18,
            page: params.page,
            perspective_id: 9,
            order: 15
          })
        )
      )
      .subscribe(
        response => {
          this.pictures = response.pictures;
          this.paginator = response.paginator;
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
