import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {APIForumsTopic, APIGetForumsTopicsRequest, Pages} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ToastsService} from '../../toasts/toasts.service';
import {ForumsTopicListComponent} from '../topic-list/topic-list.component';

@Component({
  selector: 'app-forums-subscriptions',
  imports: [ForumsTopicListComponent, PaginatorComponent, AsyncPipe],
  templateUrl: './subscriptions.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsSubscriptionsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #grpc = inject(ForumsClient);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly data$: Observable<{items?: APIForumsTopic[]; paginator?: Pages}> = combineLatest([
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
    ),
    this.#reload$,
  ]).pipe(
    switchMap(([page]) => this.#grpc.getTopics(new APIGetForumsTopicsRequest({page, subscription: true}))),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 42}), 0);
  }

  protected reload() {
    this.#reload$.next();
  }
}
