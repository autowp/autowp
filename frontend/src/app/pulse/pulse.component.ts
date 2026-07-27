import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {rxResource, toObservable} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PulseRequest} from '@grpc/spec.pb';
import {StatisticsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {ChartConfiguration} from 'chart.js';
import {BaseChartDirective, provideCharts, withDefaultRegisterables} from 'ng2-charts';
import {combineLatest, EMPTY, of} from 'rxjs';
import {map, switchMap} from 'rxjs/operators';

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
  providers: [provideCharts(withDefaultRegisterables())],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PulseComponent implements OnInit {
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
    params: () => this.#period(),
    stream: ({params: period}) => this.#statisticsClient.getPulse(new PulseRequest({period})),
  });

  protected readonly legend$ = toObservable(computed(() => this.dataResource.value())).pipe(
    map((response) =>
      (response?.legend ? response.legend : []).map((item) => ({
        color: item.color,
        user$: this.#usersService.getUser$(item.userId),
      })),
    ),
  );

  protected readonly labels = computed(() => this.dataResource.value()?.labels);

  protected readonly gridData$ = toObservable(computed(() => this.dataResource.value())).pipe(
    switchMap((response) => {
      if (!response) {
        return EMPTY;
      }
      return combineLatest(
        (response.grid ? response.grid : []).map((dataset) =>
          combineLatest([this.#usersService.getUser$(dataset.userId), of(dataset)]),
        ),
      ).pipe(
        map((rows) => ({
          data: rows.map(([user, dataset]) => ({
            data: dataset.line,
            label: user ? user.name : '',
          })),
        })),
      );
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 161});
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
