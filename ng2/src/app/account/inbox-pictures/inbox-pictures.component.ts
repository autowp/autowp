import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import Notify from '../../notify';
import { ActivatedRoute } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Subscription, combineLatest, empty } from 'rxjs';
import {
  PictureService,
  APIPicture
} from '../../services/picture';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap, catchError } from 'rxjs/operators';

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
    private auth: AuthService,
    private route: ActivatedRoute,
    private pictureService: PictureService,
    private pageEnv: PageEnvService
  ) { }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/94/name',
          pageId: 94
        }),
      0
    );

    this.querySub = combineLatest(
      this.route.queryParams,
      this.auth.getUser(),
      (params, user) => ({ params, user })
    )
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(data => this.pictureService.getPictures({
          status: 'inbox',
          owner_id: data.user.id,
          fields:
            'owner,thumb_medium,votes,views,comments_count,name_html,name_text',
          limit: 15,
          page: data.params.page,
          order: 1
        })),
        catchError((err, caught) => {
          Notify.response(err);
          return empty();
        })
      )
      .subscribe(response => {
        this.pictures = response.pictures;
        this.paginator = response.paginator;
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
