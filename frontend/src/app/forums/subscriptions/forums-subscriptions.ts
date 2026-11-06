import type {OnInit} from '@angular/core';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, computed, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {ListTopicsRequest} from '@grpc/spec.pb';
import {ForumsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {ResourceStatus} from '@utils/resource-status/resource-status';

import {Paginator} from '../../paginator/paginator/paginator';
import {ForumsTopicList} from '../topic-list/forums-topic-list';
@Component({
  selector: 'app-forums-subscriptions',
  imports: [ResourceStatus, ForumsTopicList, Paginator],
  templateUrl: './forums-subscriptions.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsSubscriptions implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #grpc = inject(ForumsClient);

  readonly page = input(0, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10) || 0,
  });

  protected readonly dataResource = rxResource({
    params: () => this.page(),
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
