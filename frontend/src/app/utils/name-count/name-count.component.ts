import type {TemplateRef} from '@angular/core';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {NgbPopover} from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-name-count',
  imports: [RouterLink, NgbPopover],
  templateUrl: './name-count.component.html',
  styleUrl: './name-count.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class NameCountComponent {
  readonly routerLink = input.required<string | string[]>();
  readonly name = input.required<string>();
  readonly count = input.required<number>();
  readonly newCount = input(0);
  readonly popover = input<null | TemplateRef<unknown>>(null);
  readonly popoverTitle = input<null | string>(null);
}
