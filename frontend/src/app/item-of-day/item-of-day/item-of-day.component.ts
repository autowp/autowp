import type {Item, ItemOfDayPicture, User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {map, switchMap} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-item-of-day',
  imports: [UserComponent, RouterLink, AsyncPipe],
  templateUrl: './item-of-day.component.html',
  styleUrl: './item-of-day.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ItemOfDayComponent {
  readonly item$ = input.required<Observable<Item>>();
  protected readonly _item$: Observable<Item> = toObservable(this.item$).pipe(switchMap((item$) => item$));

  protected readonly itemOfDayPictures$?: Observable<null | {
    first: ItemOfDayPicture[];
    others: ItemOfDayPicture[];
  }> = this._item$.pipe(
    map((item) => ({
      first: (item.itemOfDayPictures ?? []).slice(0, 1),
      others: (item.itemOfDayPictures ?? []).slice(1, 5),
    })),
  );

  public readonly user$ = input.required<Observable<null | User>>();

  protected readonly ItemType = ItemType;
}
