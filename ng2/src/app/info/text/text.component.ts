import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { UserService, APIUser } from '../../services/user';
import Notify from '../../notify';
import { Subscription, combineLatest } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

const JsDiff = require('diff');

export interface APIInfoText {
  current: {
    user_id: number;
    user?: APIUser;
    revision: number;
    text: string;
  };
  prev: {
    user_id: number;
    user?: APIUser;
    revision: number;
    text: string;
  };
  next: {
    user_id: number;
    user?: APIUser;
    revision: number;
  };
}

@Component({
  selector: 'app-info-text',
  templateUrl: './text.component.html'
})
@Injectable()
export class InfoTextComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public prev: {
    user_id: number;
    user?: APIUser;
    revision: number;
    text: string;
  };
  public current: {
    user_id: number;
    user?: APIUser;
    revision: number;
    text: string;
  };
  public next: {
    user_id: number;
    user?: APIUser;
    revision: number;
  };
  public diff: any[] = [];

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/197/name',
          pageId: 197
        }),
      0
    );

    this.routeSub = combineLatest(
      this.route.params,
      this.route.queryParams,
      (route, query) => ({ route, query })
    )
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params =>
          this.http.get<APIInfoText>('/api/text/' + params.route.id, {
            params: {
              revision: params.query.revision
            }
          })
        )
      )
      .subscribe(
        response => {
          this.current = response.current;
          this.prev = response.prev;
          this.next = response.next;

          if (this.current.user_id) {
            this.userService.getUser(this.current.user_id, {}).subscribe(
              user => {
                this.current.user = user;
              },
              subresponse => {
                Notify.response(subresponse);
              }
            );
          }

          if (this.prev.user_id) {
            this.userService.getUser(this.prev.user_id, {}).subscribe(
              user => {
                this.prev.user = user;
              },
              subresponse => {
                Notify.response(subresponse);
              }
            );
          }

          this.diff = JsDiff.diffChars(
            this.prev.text ? this.prev.text : '',
            this.current.text
          );
        },
        response => {
          Notify.response(response);
        }
      );
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
