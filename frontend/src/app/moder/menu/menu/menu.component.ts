import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {PictureService} from '@services/picture';
import {map, Observable} from 'rxjs';

import {APICommentsService} from '../../../api/comments/comments.service';

interface MenuItem {
  count$?: Observable<null | number>;
  icon: string;
  label: string;
  queryParams?: Record<string, string | undefined>;
  routerLink: string[];
}

@Component({
  selector: 'app-moder-menu',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, RouterLink, AsyncPipe],
  templateUrl: './menu.component.html',
  styleUrl: './menu.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MenuComponent {
  protected readonly auth = inject(AuthService);
  readonly #pictureService = inject(PictureService);
  readonly #commentService = inject(APICommentsService);

  protected readonly items$: Observable<MenuItem[] | null> = this.auth.hasRole$(Role.MODER).pipe(
    map((isModer) => {
      if (!isModer) {
        return null;
      }

      return [
        {
          count$: this.#pictureService.inboxSize$,
          icon: 'bi bi-grid-3x2-gap-fill',
          label: $localize`Inbox`,
          queryParams: {
            order: '1',
            status: 'inbox',
          },
          routerLink: ['/moder/pictures'],
        },
        {
          count$: this.#pictureService.similarPicturesCount$,
          icon: 'bi bi-file-earmark-image',
          label: $localize`Similar pictures`,
          routerLink: ['/moder/pictures/similar'],
        },
        {
          count$: this.#commentService.attentionCommentsCount$,
          icon: 'bi bi-chat-fill',
          label: $localize`Comments`,
          queryParams: {
            moderator_attention: '1',
          },
          routerLink: ['/moder/comments'],
        },
        {
          icon: 'bi bi-car-front',
          label: $localize`Items`,
          routerLink: ['/moder/items'],
        },
      ];
    }),
  );
}
