import {HttpErrorResponse, HttpResponseBase} from '@angular/common/http';
import {Injectable, signal} from '@angular/core';
import {GrpcStatusEvent} from '@ngx-grpc/common';

export interface Toast {
  icon: string;
  message: string;
  type: string;
}

@Injectable({providedIn: 'root'})
export class ToastsService {
  public readonly toasts = signal<Toast[]>([]);

  public handleError(error: unknown) {
    if (typeof error === 'string') {
      this.error(error);
      return;
    }

    if (typeof error === 'object') {
      if (error instanceof HttpErrorResponse) {
        this.errorResponse(error);
        return;
      }

      if (error instanceof GrpcStatusEvent) {
        this.grpcErrorResponse(error);
        return;
      }

      if (error instanceof Error) {
        this.error(error.message);
        return;
      }
    }

    console.error(error);
    this.error('undefined');
  }

  public show(options: Toast) {
    this.toasts.update((values) => [...values, options]);
  }

  public error(message: string) {
    this.show({
      icon: 'bi bi-exclamation-triangle',
      message,
      type: 'danger',
    });
  }

  public success(message: string) {
    this.show({
      icon: 'bi bi-check',
      message,
      type: 'success',
    });
  }

  public response(response: HttpResponseBase) {
    this.error(response.status + ': ' + response.statusText);
  }

  public errorResponse(response: HttpErrorResponse) {
    this.error(response.status + ': ' + response.statusText);
  }

  public grpcErrorResponse(event: GrpcStatusEvent) {
    this.error(event.statusMessage);
  }

  public remove(toast: Toast) {
    this.toasts.update((values) => values.filter((t) => t !== toast));
  }
}
