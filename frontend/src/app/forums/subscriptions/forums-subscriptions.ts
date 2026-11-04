import type {OnInit} from '@angular/core';
import type {Pages, Topic} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {ListTopicsRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {BehaviorSubject, catchError, combineLatest, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';
import {ToastsService} from '../../toasts/toasts.service';
import {ForumsTopicList} from '../topic-list/forums-topic-list';

@Component({
  selector: 'app-forums-subscriptions',
  imports: [ForumsTopicList, Paginator, AsyncPipe],
  templateUrl: './forums-subscriptions.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsSubscriptions implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #grpc = inject(ForumsClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly data$: Observable<{items?: Topic[]; paginator?: Pages}> = combineLatest([
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
    ),
    this.#reload$,
  ]).pipe(
    switchMap(([page]) => this.#grpc.listTopics(new ListTopicsRequest({page, subscription: true}))),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FORUMS});
  }

  protected reload() {
    this.#reload$.next();
  }
}
