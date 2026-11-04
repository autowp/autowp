import type {Spec} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';

@Component({
  selector: 'app-info-spec-row',
  templateUrl: './info-spec-row.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InfoSpecRow {
  readonly row = input.required<Spec>();
  readonly deep = input.required<number>();
}
