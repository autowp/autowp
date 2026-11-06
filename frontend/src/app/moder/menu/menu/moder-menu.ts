import type {ResourceRef} from '@angular/core';

import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {NgbDropdown, NgbDropdownMenu, NgbDropdownToggle} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth-service';
import {PictureService} from '@services/picture';
import {ResourceStatus} from '@utils/resource-status/resource-status';

import {APICommentsService} from '../../../api/comments/comments.service';
import {APIContentReportsService} from '../../../api/content-reports/content-reports.service';
import {APIGdprObjectionsService} from '../../../api/gdpr-objections/gdpr-objections.service';

interface MenuItem {
  badgeClass?: string;
  countResource?: ResourceRef<null | number | undefined>;
  icon: string;
  label: string;
  queryParams?: Record<string, string | undefined>;
  routerLink: string[];
}

@Component({
  selector: 'app-moder-menu',
  imports: [NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, RouterLink, ResourceStatus],
  templateUrl: './moder-menu.html',
  styleUrl: './moder-menu.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerMenu {
  protected readonly auth = inject(AuthService);
  readonly #pictureService = inject(PictureService);
  readonly #commentService = inject(APICommentsService);
  readonly #contentReportsService = inject(APIContentReportsService);
  readonly #gdprObjectionsService = inject(APIGdprObjectionsService);

  readonly #isModer = this.auth.hasRole(Role.MODER);
  readonly #inboxSize = this.#pictureService.getInboxSizeResource();
  readonly #similarPicturesCount = this.#pictureService.getSimilarPicturesCountResource();
  readonly #attentionCommentsCount = this.#commentService.getAttentionCommentsCountResource();
  readonly #openReportsCount = this.#contentReportsService.getOpenReportsCountResource();
  readonly #openObjectionHitsCount = this.#gdprObjectionsService.getOpenObjectionHitsCountResource();

  protected readonly items = computed<MenuItem[] | null>(() => {
    if (!this.#isModer()) return null;
    return [
      {
        countResource: this.#inboxSize,
        icon: 'bi bi-grid-3x2-gap-fill',
        label: $localize`Inbox`,
        queryParams: {
          order: '1',
          status: 'inbox',
        },
        routerLink: ['/moder/pictures'],
      },
      {
        countResource: this.#similarPicturesCount,
        icon: 'bi bi-file-earmark-image',
        label: $localize`Similar pictures`,
        routerLink: ['/moder/pictures/similar'],
      },
      {
        countResource: this.#attentionCommentsCount,
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
      {
        badgeClass: 'text-bg-danger',
        countResource: this.#openReportsCount,
        icon: 'bi bi-flag-fill',
        label: $localize`Content reports`,
        routerLink: ['/moder/content-reports'],
      },
      {
        badgeClass: 'text-bg-danger',
        countResource: this.#openObjectionHitsCount,
        icon: 'bi bi-shield-exclamation',
        label: $localize`GDPR suppression list`,
        routerLink: ['/moder/gdpr-objections'],
      },
    ];
  });
}
