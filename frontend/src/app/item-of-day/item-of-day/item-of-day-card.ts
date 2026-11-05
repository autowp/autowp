import type {Item, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, ErrorHandler, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {tap} from 'rxjs';

import {UserLink} from '../../user/user/user-link';

@Component({
  selector: 'app-item-of-day',
  imports: [UserLink, RouterLink, AsyncPipe],
  templateUrl: './item-of-day-card.html',
  styleUrl: './item-of-day-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemOfDayCard {
  readonly #errorHandler = inject(ErrorHandler);
  readonly item$ = input.required<Observable<Item>>();
  protected readonly itemResource = rxResource({
    params: () => this.item$(),
    stream: ({params}) =>
      params.pipe(
        tap({
          error: (error: unknown) => {
            this.#errorHandler.handleError(error);
          },
        }),
      ),
  });
  protected readonly item = computed(() => (this.itemResource.hasValue() ? this.itemResource.value() : undefined));

  protected readonly itemOfDayPictures = computed(() => {
    const item = this.item();
    if (!item) {
      return null;
    }
    const pictures = item.itemOfDayPictures ?? [];
    return {first: pictures.slice(0, 1), others: pictures.slice(1, 5)};
  });

  public readonly user$ = input.required<Observable<null | User>>();

  protected readonly ItemType = ItemType;
}
