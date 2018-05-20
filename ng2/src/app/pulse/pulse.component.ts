import { Component, Injectable, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { PageEnvService } from '../services/page-env.service';
import { APIUser } from '../services/user';

export interface APIPulseResponse {
  legend: {
    color: string;
    user: APIUser;
  }[];
  grid: {
    line: number[];
    color: string;
    label: string;
  }[];
  labels: string[];
}

@Component({
  selector: 'app-pulse',
  templateUrl: './pulse.component.html'
})
@Injectable()
export class PulseComponent implements OnInit {

  public legend: {
    color: string;
    user: APIUser;
  }[] = [];

  public periods = [
    {
      value: 'day',
      name: 'Day',
      active: true
    },
    {
      value: 'month',
      name: 'Month',
      active: false
    },
    {
      value: 'year',
      name: 'Year',
      active: false
    }
  ];

  private period = 'day';

  public chart = {
    data: [],
    labels: [],
    options: {
      responsive: true,
      scales: {
        xAxes: [
          {
            stacked: true
          }
        ],
        yAxes: [
          {
            stacked: true,
            ticks: {
              beginAtZero: true
            }
          }
        ]
      }
    },
    colors: []
  };

  constructor(private http: HttpClient, private pageEnv: PageEnvService) {

  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: false
          },
          name: 'page/161/name',
          pageId: 161
        }),
      0
    );
    this.loadData();
  }

  private loadData() {
    this.chart.data = [];

    this.http.get<APIPulseResponse>('/api/pulse', {params: {period: this.period}}).subscribe(
      response => {
        const datasets: any[] = [];
        const colors: any[] = [];

        for (const dataset of response.grid) {
          datasets.push({
            label: dataset.label,
            data: dataset.line
          });
          colors.push({
            backgroundColor: dataset.color,
            borderColor: dataset.color,
            pointBackgroundColor: 'rgba(148,159,177,1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(148,159,177,0.8)'
          });
        }

        this.chart.data = datasets;
        this.chart.labels = response.labels;
        this.chart.colors = colors;
        this.legend = response.legend;
      },
      response => {
        Notify.response(response);
      }
    );
  }

  public selectUser(i: number) {
    this.chart.colors[i].backup = this.chart.colors[i].backgroundColor;
    this.chart.colors[i].backgroundColor = 'blue';
  }

  public deselectUser(i: number) {
    this.chart.colors[i].backgroundColor = this.chart.colors[i].backup;
  }

  public selectPeriod(period) {
    for (const p of this.periods) {
      p.active = false;
    }
    period.active = true;
    this.period = period.value;
    this.loadData();

    return false;
  }
}
