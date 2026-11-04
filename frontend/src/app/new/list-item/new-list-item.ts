import type {Item, Picture} from '@grpc/spec.pb';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {AuthService, Role} from '@services/auth-service';
import {ItemHeading} from '@utils/item-header/item-heading';
import {RemarkModule} from 'ngx-remark';

@Component({
  selector: 'app-new-list-item',
  imports: [ItemHeading, RouterLink, AsyncPipe, RemarkModule],
  templateUrl: './new-list-item.html',
  styleUrl: './new-list-item.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewListItem {
  readonly #auth = inject(AuthService);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  readonly item = input.required<Item>();
  readonly pictures = input.required<Picture[]>();
  readonly totalPictures = input.required<number>();
  readonly date = input.required<string>();
}
