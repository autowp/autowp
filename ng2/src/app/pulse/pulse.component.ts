import { Component, Injectable } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import Notify from '../notify';
import { PageEnvService } from '../services/page-env.service';
const Raphael = require('raphael');

Raphael.fn.drawGrid = (
  x: number,
  y: number,
  w: number,
  h: number,
  wv: number,
  hv: number,
  color: string
) => {
  color = color || '#000';
  let path = [
    'M',
    Math.round(x) + 0.5,
    Math.round(y) + 0.5,
    'L',
    Math.round(x + w) + 0.5,
    Math.round(y) + 0.5,
    Math.round(x + w) + 0.5,
    Math.round(y + h) + 0.5,
    Math.round(x) + 0.5,
    Math.round(y + h) + 0.5,
    Math.round(x) + 0.5,
    Math.round(y) + 0.5
  ];
  const rowHeight = h / hv,
    columnWidth = w / wv;
  for (let i = 1; i < hv; i++) {
    path = path.concat([
      'M',
      Math.round(x) + 0.5,
      Math.round(y + i * rowHeight) + 0.5,
      'H',
      Math.round(x + w) + 0.5
    ]);
  }
  for (let i = 1; i < wv; i++) {
    path = path.concat([
      'M',
      Math.round(x + i * columnWidth) + 0.5,
      Math.round(y) + 0.5,
      'V',
      Math.round(y + h) + 0.5
    ]);
  }
  return this.path(path.join(',')).attr({ stroke: color });
};

export interface APIPulseResponse {
  legend: any;
  grid: any;
}

@Component({
  selector: 'app-pulse',
  templateUrl: './pulse.component.html'
})
@Injectable()
export class PulseComponent {
  public map: any = {};
  public legend: any;
  public grid: any;

  constructor(private http: HttpClient, private pageEnv: PageEnvService) {
    this.pageEnv.set({
      layout: {
        needRight: false
      },
      name: 'page/161/name',
      pageId: 161
    });

    this.http.get<APIPulseResponse>('/api/pulse').subscribe(
      response => {
        this.legend = response.legend;
        this.grid = response.grid;

        this.render();
      },
      response => {
        Notify.response(response);
      }
    );
  }

  private render() {
    /*$('#pulse-graph').each(() => {
      const $element = $(this);

      const values = this.grid;

      // Grab the data
      const // labels = [],
        maxes: any[] = [],
        lines: any[] = [];

      $.each(values, (userId, info) => {
        // labels = [];
        const line: any[] = [];
        $.each(info.line, (date, value) => {
          // labels.push(date);
          line.push(value);
        });

        lines.push({
          userId: userId,
          line: line,
          color: info.color
        });

        maxes.push(Math.max.apply(Math, line));
      });

      const max = Math.max.apply(Math, maxes);
      // Draw
      const width = $element.width() as number,
        height = $element.height() as number,
        leftgutter = 30,
        bottomgutter = 50,
        topgutter = 20,
        r = Raphael(this, width, height),
        labelsCount = lines[0].line.length,
        X = (width - leftgutter) / labelsCount,
        Y = (height - bottomgutter - topgutter) / max;

      r.drawGrid(
        leftgutter + X * 0.5 + 0.5,
        topgutter + 0.5,
        width - leftgutter - X,
        height - topgutter - bottomgutter,
        labelsCount - 1,
        10,
        '#000'
      );

      const columnWidth = (width - leftgutter - X) / (labelsCount - 1);

      $.map(lines, (line: any) => {
        const rects = [];

        const data = line.line;

        const color = line.color;

        const cWidth = columnWidth;
        for (let i = 0, ii = labelsCount; i < ii; i++) {
          const value = data[i],
            cHeight = Y * value,
            y = Math.round(height - bottomgutter - cHeight),
            x = Math.round(leftgutter + X * (i + 0.5));

          if (value) {
            rects.push(
              r.rect(x - cWidth / 2, y, cWidth, Math.round(cHeight)).attr({
                fill: color,
                opacity: 0.9,
                stroke: color
              })
            );
          }
        }

        this.map[line.userId] = rects;
      });
    });*/
  }

  public selectUser(id: number) {
    $.map(this.map, rects => {
      $.map(rects, rect => {
        rect.attr({
          opacity: 0.1
        });
      });
    });

    $.map(this.map[id], rect => {
      rect.attr({
        opacity: 1
      });
    });
  }

  public deselectUser() {
    $.map(this.map, rects => {
      $.map(rects, rect => {
        rect.attr({
          opacity: 0.9
        });
      });
    });
  }
}
