import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {Topic} from '@grpc/spec.pb';
import {AuthService, Role} from '@services/auth.service';

import {ForumsTopicListItemComponent} from './topic-list-item/topic-list-item.component';

@Component({
  selector: 'app-forums-topic-list',
  imports: [ForumsTopicListItemComponent],
  templateUrl: './topic-list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForumsTopicListComponent {
  readonly #auth = inject(AuthService);

  readonly topics = input.required<Topic[]>();

  readonly showSubscribe = input(false);

  readonly reload = output<void>();

  protected readonly forumAdmin = toSignal(this.#auth.hasRole$(Role.FORUMS_MODER), {initialValue: false});
}
