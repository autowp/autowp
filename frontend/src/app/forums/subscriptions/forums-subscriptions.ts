import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute} from '@angular/router';
import {ListTopicsRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';
import {map} from 'rxjs';

import {Paginator} from '../../paginator/paginator/paginator';
import {ForumsTopicList} from '../topic-list/forums-topic-list';
@Component({
  selector: 'app-forums-subscriptions',
  imports: [ResourceStatus, ForumsTopicList, Paginator],
  templateUrl: './forums-subscriptions.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsSubscriptions implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #grpc = inject(ForumsClient);

  readonly #page = toSignal(
    this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10) || 0)),
    {requireSync: true},
  );
  protected readonly dataResource = rxResource({
    params: () => this.#page(),
    stream: ({params: page}) => this.#grpc.listTopics(new ListTopicsRequest({page, subscription: true})),
  });
  protected readonly data = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.FORUMS});
  }

  protected reload() {
    this.dataResource.reload();
  }
}
