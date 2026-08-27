import type {OnInit} from '@angular/core';
import type {User} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {CurrencyPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {DonationsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {errorMessage} from 'app/grpc';
import {catchError, map, of, switchMap} from 'rxjs';

import {UserComponent} from '../../user/user/user.component';

interface DonateLogItem {
  createdAt: Date | undefined;
  currency: string;
  purpose: string;
  sum: number;
  userId: string;
}

interface DonateLogData {
  items: DonateLogItem[];
  usersById: Record<string, User>;
}

@Component({
  selector: 'app-donate-log',
  imports: [RouterLink, NgbTooltip, UserComponent, CurrencyPipe, DatePipe, TimeAgoPipe],
  templateUrl: './log.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateLogComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #donations = inject(DonationsClient);

  // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
  // Donors are resolved into a plain Record alongside items, not a per-item user$ Observable (the
  // previous shape here): an Observable doesn't survive the TransferState JSON round-trip - RxJS
  // Observable instances serialize to '{}' (see timestamp.ts for the equivalent issue with
  // Timestamp/.toDate()), and AsyncPipe throws on that non-Observable, non-Promise value on
  // hydration.
  protected readonly itemsResource = rxResource({
    id: 'donate-log-items',
    stream: (): Observable<DonateLogData> =>
      this.#donations.getTransactions(new Empty()).pipe(
        switchMap((response) => {
          const items = (response.items ?? []).map((item) => ({
            createdAt: timestampToDate(item.createTime),
            currency: item.currency,
            purpose: item.purpose,
            sum: item.sum / 100,
            userId: item.userId,
          }));

          const userIds = [...new Set(items.map((item) => item.userId).filter((id) => id && id !== '0'))];
          if (userIds.length === 0) {
            return of({items, usersById: {}});
          }

          return this.#userService.getUserMap$(userIds).pipe(
            map((userMap) => ({items, usersById: Object.fromEntries(userMap)})),
            // getUserMap$ leaves out users the backend doesn't return (deleted accounts), so
            // this only catches a genuine RPC failure - degrade to showing no donor rather than
            // erroring the whole resource over it.
            catchError(() => of({items, usersById: {}})),
          );
        }),
      ),
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.DONATE});
  }

  protected readonly errorMessage = errorMessage;
}
