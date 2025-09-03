import {NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {GoautowpSpec} from '@rest/model/goautowpSpec';

@Component({
  selector: 'app-info-spec-row',
  imports: [NgStyle],
  templateUrl: './row.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoSpecRowComponent {
  readonly row = input.required<GoautowpSpec>();
  readonly deep = input.required<number>();
}
