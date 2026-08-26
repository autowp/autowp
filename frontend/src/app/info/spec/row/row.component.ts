import type {Spec} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';

@Component({
  selector: 'app-info-spec-row',
  templateUrl: './row.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class InfoSpecRowComponent {
  readonly row = input.required<Spec>();
  readonly deep = input.required<number>();
}
