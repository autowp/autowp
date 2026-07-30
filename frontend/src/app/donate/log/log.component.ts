import {AsyncPipe, CurrencyPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {DonationsClient} from '@grpc/spec.pbsc';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {map} from 'rxjs/operators';

import {UserComponent} from '../../user/user/user.component';

@Component({
  selector: 'app-donate-log',
  imports: [RouterLink, NgbTooltip, UserComponent, AsyncPipe, CurrencyPipe, DatePipe, TimeAgoPipe],
  templateUrl: './log.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DonateLogComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #pageEnv = inject(PageEnvService);
  readonly #donations = inject(DonationsClient);

  protected readonly items$ = this.#donations.getTransactions(new Empty()).pipe(
    map((response) =>
      (response.items || []).map((item) => ({
        createdAt: item.createTime?.toDate(),
        currency: item.currency,
        purpose: item.purpose,
        sum: item.sum / 100,
        user$: this.#userService.getUser$(item.userId),
      })),
    ),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 196});
  }
}
