import {ChangeDetectionStrategy, Component, inject, input, linkedSignal, output, signal} from '@angular/core';
import {form, FormField, maxLength, pattern, readonly as readonlyField, required, submit} from '@angular/forms/signals';
import {CreateTrafficSourceRequest, TrafficSourceItem, UpdateTrafficSourceRequest} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {errorMessage} from 'app/grpc';
import {firstValueFrom} from 'rxjs';

@Component({
  selector: 'app-traffic-source-editor',
  imports: [FormField],
  templateUrl: './source-editor.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SourceEditor {
  readonly #client = inject(TrafficClient);
  readonly source = input<TrafficSourceItem>();
  readonly saved = output();
  readonly cancelled = output();
  protected readonly model = linkedSignal(() => ({
    id: this.source()?.id ?? '',
    name: this.source()?.name ?? '',
    networks: this.source()?.networks.join('\n') ?? '',
    domains: this.source()?.dnsSuffixes.join('\n') ?? '',
    references: this.source()?.references.join('\n') ?? '',
  }));
  protected readonly fields = form(this.model, (path) => {
    required(path.id);
    readonlyField(path.id, {when: () => !!this.source()});
    maxLength(path.id, 64);
    pattern(path.id, /^[a-z][a-z0-9_-]{0,63}$/);
    required(path.name);
    maxLength(path.name, 200);
  });
  protected readonly failure = signal('');

  protected save(event: Event): void {
    event.preventDefault();
    void submit(this.fields, async () => {
      this.failure.set('');
      const data = this.model();
      const lines = (value: string) =>
        value
          .split('\n')
          .map((line) => line.trim())
          .filter(Boolean);
      const source = new TrafficSourceItem({
        id: data.id,
        name: data.name,
        networks: lines(data.networks),
        dnsSuffixes: lines(data.domains),
        references: lines(data.references),
      });
      try {
        await firstValueFrom(
          this.source()
            ? this.#client.updateTrafficSource(
                new UpdateTrafficSourceRequest({
                  source,
                  updateMask: new FieldMask({paths: ['name', 'networks', 'dns_suffixes', 'references']}),
                }),
              )
            : this.#client.createTrafficSource(new CreateTrafficSourceRequest({source})),
        );
        this.saved.emit();
      } catch (error) {
        this.failure.set(errorMessage(error) || $localize`Unknown error`);
      }
    });
  }
}
