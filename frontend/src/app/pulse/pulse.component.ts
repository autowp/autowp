import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PulseRequest} from '@grpc/spec.pb';
import {StatisticsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {ChartConfiguration} from 'chart.js';
import {BaseChartDirective} from 'ng2-charts';
import {BehaviorSubject, combineLatest, EMPTY, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {UserComponent} from '../user/user/user.component';

interface Period {
  active: boolean;
  name: string;
  value: PulseRequest.Period;
}

@Component({
  selector: 'app-pulse',
  imports: [RouterLink, BaseChartDirective, UserComponent, AsyncPipe],
  templateUrl: './pulse.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PulseComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #statisticsClient = inject(StatisticsClient);
  readonly #usersService = inject(UserService);

  protected readonly periods: Period[] = [
    {
      active: true,
      name: 'Day',
      value: PulseRequest.Period.DEFAULT,
    },
    {
      active: false,
      name: 'Month',
      value: PulseRequest.Period.MONTH,
    },
    {
      active: false,
      name: 'Year',
      value: PulseRequest.Period.YEAR,
    },
  ];

  readonly #period$ = new BehaviorSubject<PulseRequest.Period>(PulseRequest.Period.DEFAULT);

  protected readonly chartOptions: ChartConfiguration<'bar', never, never>['options'] = {
    responsive: true,
    scales: {
      x: {
        stacked: true,
      },
      y: {
        stacked: true,
      },
    },
  };

  readonly #data$ = this.#period$.pipe(
    debounceTime(10),
    distinctUntilChanged(),
    switchMap((period) => this.#statisticsClient.getPulse(new PulseRequest({period}))),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly legend$ = this.#data$.pipe(
    map((response) => {
      return (response.legend ? response.legend : []).map((item) => ({
        color: item.color,
        user$: this.#usersService.getUser$(item.userId),
      }));
    }),
  );

  protected readonly labels$ = this.#data$.pipe(map((response) => response.labels));

  protected readonly gridData$ = this.#data$.pipe(
    switchMap((response) =>
      combineLatest(
        (response.grid ? response.grid : []).map((dataset) =>
          combineLatest([this.#usersService.getUser$(dataset.userId), of(dataset)]),
        ),
      ),
    ),
    map((response) => ({
      data: response.map(([user, dataset]) => ({
        data: dataset.line,
        label: user ? user.name : '',
      })),
    })),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 161});
  }

  protected selectPeriod(period: Period) {
    for (const p of this.periods) {
      p.active = false;
    }
    period.active = true;
    this.#period$.next(period.value);

    return false;
  }
}
