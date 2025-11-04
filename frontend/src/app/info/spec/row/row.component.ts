import {NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {Spec} from '@grpc/spec.pb';

@Component({
  selector: 'app-info-spec-row',
  imports: [NgStyle],
  templateUrl: './row.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoSpecRowComponent {
  readonly row = input.required<Spec>();
  readonly deep = input.required<number>();
}
