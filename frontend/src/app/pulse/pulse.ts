import type {OnInit} from '@angular/core';
import type {ChartConfiguration} from 'chart.js';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PulseRequest} from '@grpc/spec.pb';
import {StatisticsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {errorMessage} from 'app/grpc';
import {BaseChartDirective, provideCharts, withDefaultRegisterables} from 'ng2-charts';
import {map} from 'rxjs';

import {UserLink} from '../user/user/user-link';

interface Period {
  active: boolean;
  name: string;
  value: PulseRequest.Period;
}

@Component({
  selector: 'app-pulse',
  imports: [RouterLink, BaseChartDirective, UserLink, AsyncPipe],
  templateUrl: './pulse.html',
  providers: [provideCharts(withDefaultRegisterables())],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Pulse implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #statisticsClient = inject(StatisticsClient);
  readonly #usersService = inject(UserService);

  protected readonly periods: Period[] = [
    {active: true, name: 'Day', value: PulseRequest.Period.DEFAULT},
    {active: false, name: 'Month', value: PulseRequest.Period.MONTH},
    {active: false, name: 'Year', value: PulseRequest.Period.YEAR},
  ];

  readonly #period = signal<PulseRequest.Period>(PulseRequest.Period.DEFAULT);

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

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'pulse-page',
    params: () => this.#period(),
    stream: ({params: period}) => this.#statisticsClient.getPulse(new PulseRequest({period})),
  });

  // Guard reads of a resource that may be in an error state.
  protected readonly dataData = computed(() => (this.dataResource.hasValue() ? this.dataResource.value() : undefined));

  protected readonly legend = computed(() =>
    (this.dataData()?.legend ?? []).map((item) => ({
      color: item.color,
      user$: this.#usersService.getUser$(item.userId),
    })),
  );

  protected readonly labels = computed(() => this.dataData()?.labels);

  protected readonly gridResource = rxResource({
    params: () => this.dataData(),
    stream: ({params}) =>
      this.#usersService.getUserMap$((params.grid ?? []).map((dataset) => dataset.userId)).pipe(
        map((users) => ({
          data: (params.grid ?? []).map((dataset) => ({
            data: dataset.line,
            label: users.get(dataset.userId)?.name ?? '',
          })),
        })),
      ),
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.PULSE});
  }

  protected selectPeriod(period: Period) {
    for (const p of this.periods) {
      p.active = false;
    }
    period.active = true;
    this.#period.set(period.value);

    return false;
  }
}
