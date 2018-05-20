import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { UserService, APIUser } from '../../services/user';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';
import { PageEnvService } from '../../services/page-env.service';

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

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {
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
  }

  ngOnInit(): void {
    this.routeSub = this.route.params.subscribe(params => {
      this.http
        .get<APIInfoText>('/api/text/' + params.id, {
          params: {
            revision: params.revision
          }
        })
        .subscribe(
          response => {
            this.current = response.current;
            this.prev = response.prev;
            this.next = response.next;

            if (this.current.user_id) {
              this.userService.getUser(this.current.user_id, {}).then(
                user => {
                  this.current.user = user;
                },
                subresponse => {
                  Notify.response(subresponse);
                }
              );
            }

            if (this.prev.user_id) {
              this.userService.getUser(this.prev.user_id, {}).then(
                user => {
                  this.prev.user = user;
                },
                subresponse => {
                  Notify.response(subresponse);
                }
              );
            }

            if (this.prev.text) {
              this.doDiff();
            }
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

  doDiff() {
    const diff = JsDiff.diffChars(this.prev.text, this.current.text);

    const fragment = document.createDocumentFragment();
    for (let i = 0; i < diff.length; i++) {
      if (diff[i].added && diff[i + 1] && diff[i + 1].removed) {
        const swap = diff[i];
        diff[i] = diff[i + 1];
        diff[i + 1] = swap;
      }

      let node;
      if (diff[i].removed) {
        node = document.createElement('del');
        node.appendChild(document.createTextNode(diff[i].value));
      } else if (diff[i].added) {
        node = document.createElement('ins');
        node.appendChild(document.createTextNode(diff[i].value));
      } else {
        node = document.createTextNode(diff[i].value);
      }
      fragment.appendChild(node);
    }

    $('pre')
      .eq(1)
      .empty()
      .append(fragment);
  }
}
