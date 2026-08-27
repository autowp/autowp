import type {OnInit} from '@angular/core';
import type {ChartParameter} from '@grpc/spec.pb';
import type {ChartOptions} from 'chart.js';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, signal} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {AttrAttributeType, ChartDataRequest} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {getAttrsTranslation} from '@utils/translations';
import {errorMessage} from 'app/grpc';
import {BaseChartDirective, provideCharts, withDefaultRegisterables} from 'ng2-charts';
import {ObjectTyped} from 'object-typed';
import {map} from 'rxjs';

import {ToastsService} from '../toasts/toasts.service';

@Component({
  selector: 'app-chart',
  imports: [RouterLink, BaseChartDirective],
  templateUrl: './chart.component.html',
  providers: [provideCharts(withDefaultRegisterables())],
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class ChartComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #cdr = inject(ChangeDetectorRef);

  protected readonly parametersResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'chart-parameters',
    stream: () =>
      this.#attrsClient.getChartParameters(new Empty()).pipe(
        map((response) =>
          (response.parameters ?? []).map((parameter) => {
            parameter.name = getAttrsTranslation(parameter.name);
            return parameter;
          }),
        ),
      ),
  });
  protected readonly activeParameter = signal(0);
  protected readonly chartOptions: ChartOptions<'line'> = {
    responsive: true,
  };

  protected readonly chart: {
    colors: {
      backgroundColor: string;
      borderColor: string;
      pointBackgroundColor: string;
      pointBorderColor: string;
      pointHoverBackgroundColor: string;
      pointHoverBorderColor: string;
    }[];
    data: {
      data: (null | number)[];
      label: string;
    }[];
    labels: number[];
  } = {
    colors: [
      {
        backgroundColor: 'rgba(41,84,109,1)',
        borderColor: 'rgba(41,84,109,1)',
        pointBackgroundColor: 'rgba(148,159,177,1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(148,159,177,0.8)',
      },
      {
        backgroundColor: 'rgba(242,80,122,1)',
        borderColor: 'rgba(242,80,122,1)',
        pointBackgroundColor: 'rgba(148,159,177,1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(148,159,177,0.8)',
      },
    ],
    data: [],
    labels: [],
  };

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }

  private loadData(id: string) {
    this.chart.data = [];
    this.#cdr.markForCheck();

    this.#attrsClient.getChartData(new ChartDataRequest({id})).subscribe({
      error: (response: unknown) => {
        this.#toastService.handleError(response);
      },
      next: (response) => {
        const datasets = response.datasets ?? [];
        const yearsSet = new Set<number>();
        datasets.forEach((dataset) => {
          ObjectTyped.keys(dataset.values).forEach((key) => yearsSet.add(key));
        });
        const years = Array.from(yearsSet.keys()).sort((a, b) => a - b);

        this.chart.labels = years;
        this.chart.data = datasets.map((dataset) => {
          const numbers: (null | number)[] = [];
          years.forEach((year) => {
            // Object.hasOwn(), not `if (value)`: without noUncheckedIndexedAccess, TS types a
            // Record's index access as always-present, so this reads as "always truthy" to the
            // type checker even though `years` is a union of keys across *all* datasets, and this
            // one genuinely may not have a value for every year in it.
            const value = Object.hasOwn(dataset.values, year) ? dataset.values[year] : undefined;
            let numberValue: null | number = null;
            if (value) {
              switch (value.type) {
                case AttrAttributeType.Id.FLOAT:
                  numberValue = value.floatValue;
                  break;
                case AttrAttributeType.Id.INTEGER:
                  numberValue = value.intValue;
                  break;
              }
            }
            numbers.push(numberValue);
          });

          return {
            data: numbers,
            label: dataset.name,
          };
        });

        this.#cdr.markForCheck();
      },
    });
  }

  protected selectParam(parameters: ChartParameter[], number: number) {
    this.activeParameter.set(number);
    this.loadData(parameters[this.activeParameter()].id);

    return false;
  }

  protected readonly errorMessage = errorMessage;
}
