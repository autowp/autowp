import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Subscription } from 'rxjs';
import {
  APIPictureGetResponse,
  PictureService,
  APIPicture
} from '../../services/picture';

@Component({
  selector: 'app-account-inbox-pictures',
  templateUrl: './inbox-pictures.component.html'
})
@Injectable()
export class AccountInboxPicturesComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public pictures: APIPicture[] = [];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private router: Router,
    private auth: AuthService,
    private route: ActivatedRoute,
    private pictureService: PictureService
  ) {}

  ngOnInit(): void {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/94/name',
      pageId: 94
    });*/

    this.querySub = this.route.queryParams.subscribe(params => {
      this.pictureService
        .getPictures({
          status: 'inbox',
          owner_id: this.auth.user.id,
          fields:
            'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
          limit: 15,
          page: params.page,
          order: 1
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
