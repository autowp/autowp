import type {Item, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, ErrorHandler, inject, input} from '@angular/core';
import {toObservable, toSignal} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {catchError, of, switchMap} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-item-of-day',
  imports: [UserComponent, RouterLink, AsyncPipe],
  templateUrl: './item-of-day.component.html',
  styleUrl: './item-of-day.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemOfDayComponent {
  readonly #errorHandler = inject(ErrorHandler);
  readonly item$ = input.required<Observable<Item>>();
  protected readonly item = toSignal(
    toObservable(this.item$).pipe(
      switchMap((item$) =>
        item$.pipe(
          catchError((error: unknown) => {
            this.#errorHandler.handleError(error);
            return of(undefined);
          }),
        ),
      ),
    ),
  );

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
