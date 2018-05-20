import { Component, Injectable } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { PageEnvService } from '../services/page-env.service';

// const ChartJS = require('chart');

export interface APIChartParameter {
  name: string;
  active: boolean;
}

export interface APIChartParameters {
  parameters: APIChartParameter[];
}

export interface APIChartDataset {
  name: string;
  values: number[];
}

export interface APIChartData {
  datasets: APIChartDataset[];
  years: any[];
}

@Component({
  selector: 'app-chart',
  templateUrl: './chart.component.html'
})
@Injectable()
export class ChartComponent {
  public parameters: APIChartParameter[] = [];
  private chart: any;

  constructor(private http: HttpClient, private pageEnv: PageEnvService) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/1/name',
          title: 'page/1/title',
          pageId: 1
        }),
      0
    );

    const $chart = $('.chart');

    /* this.chart = new ChartJS($chart[0], {
      type: 'line',
      data: {
        labels: [],
        datasets: []
      }
    });*/

    this.http.get<APIChartParameters>('/api/chart/parameters').subscribe(
      response => {
        this.parameters = response.parameters;
        this.selectParam(this.parameters[0]);
      },
      response => {
        Notify.response(response);
      }
    );
  }

  private loadData(id: number) {
    const colors = ['rgba(41,84,109,1)', 'rgba(242,80,122,1)'];

    this.http
      .get<APIChartData>('/api/chart/data', {
        params: { id: id.toString() }
      })
      .subscribe(
        response => {
          const datasets: any[] = [];
          $.map(response.datasets, (dataset: APIChartDataset, i: number) => {
            datasets.push({
              label: dataset.name,
              fill: false,

              // String - the color to fill the area under the line with if fill is true
              backgroundColor: 'rgba(220,220,220,1)',

              // String or array - Line color
              borderColor: colors[i % colors.length],

              // String - cap style of the line. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/lineCap
              borderCapStyle: 'butt',

              borderDash: [],

              borderDashOffset: 0.0,

              // String - line join style. See https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/lineJoin
              borderJoinStyle: 'miter',

              // String or array - Point stroke color
              pointBorderColor: 'rgba(220,220,220,1)',

              // String or array - Point fill color
              pointBackgroundColor: '#fff',

              // Number or array - Stroke width of point border
              pointBorderWidth: 1,

              // Number or array - Radius of point when hovered
              pointHoverRadius: 5,

              // String or array - point background color when hovered
              pointHoverBackgroundColor: 'rgba(220,220,220,1)',

              // Point border color when hovered
              pointHoverBorderColor: 'rgba(220,220,220,1)',

              // Number or array - border width of point when hovered
              pointHoverBorderWidth: 2,

              // Tension - bezier curve tension of the line. Set to 0 to draw straight Wlines connecting points
              tension: 0.5,

              // The actual data
              data: dataset.values
            });
          });

          const data = {
            labels: response.years,
            datasets: datasets
          };

          this.chart.chart.config.data = data;

          this.chart.update();
        },
        response => {
          Notify.response(response);
        }
      );
  }

  public selectParam(param: any) {
    for (const p of this.parameters) {
      p.active = false;
    }
    param.active = true;
    this.loadData(param.id);
  }
}
