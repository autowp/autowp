import {ChangeDetectionStrategy, Component, inject, output, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {AcceptTermsRequest} from '@grpc/spec.pb';
import {UsersClient} from '@grpc/spec.pbsc';

import {ToastsService} from '../toasts/toasts.service';

// Shown by AppComponent when Me() reports `termsAcceptanceRequired` - a blocking overlay that a
// signed-in user clears once by accepting the current Terms version. The backend records its own
// current version (AcceptTerms takes no arguments), so there is nothing to pass.
@Component({
  selector: 'app-terms-gate',
  imports: [RouterLink],
  templateUrl: './terms-gate.component.html',
  styleUrl: './terms-gate.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TermsGateComponent {
  readonly #usersClient = inject(UsersClient);
  readonly #toasts = inject(ToastsService);

  protected readonly submitting = signal(false);

  public readonly accepted = output();

  protected accept(): void {
    if (this.submitting()) {
      return;
    }

    this.submitting.set(true);
    this.#usersClient.acceptTerms(new AcceptTermsRequest()).subscribe({
      error: (err: unknown) => {
        this.submitting.set(false);
        this.#toasts.handleError(err);
      },
      next: () => {
        this.accepted.emit();
      },
    });
  }
}
