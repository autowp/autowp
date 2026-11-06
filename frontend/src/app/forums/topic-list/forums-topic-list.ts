import type {Topic} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {AuthService, Role} from '@services/auth-service';

import {ForumsTopicListItem} from './topic-list-item/forums-topic-list-item';
@Component({
  selector: 'app-forums-topic-list',
  imports: [ForumsTopicListItem],
  templateUrl: './forums-topic-list.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsTopicList {
  readonly #auth = inject(AuthService);

  readonly topics = input.required<Topic[]>();

  readonly showSubscribe = input(false);

  readonly reload = output();

  protected readonly forumAdmin = this.#auth.hasRole(Role.FORUMS_MODER);
}
