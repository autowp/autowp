import type {ResourceRef} from '@angular/core';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {errorMessage} from 'app/grpc';

@Component({
  selector: 'app-resource-status',
  templateUrl: './resource-status.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResourceStatus {
  readonly resource = input.required<Pick<ResourceRef<unknown>, 'error' | 'isLoading' | 'reload'>>();
  readonly empty = input(false);
  protected readonly errorMessage = errorMessage;
}
