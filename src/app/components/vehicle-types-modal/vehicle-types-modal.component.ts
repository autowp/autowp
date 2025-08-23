import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, input, output} from '@angular/core';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {VehicleTypeService} from '@services/vehicle-type';
import {getVehicleTypeTranslation} from '@utils/translations';
import {map, shareReplay} from 'rxjs/operators';
import {VehicleType} from '@rest/model/vehicleType';

const translateNames = (types: VehicleType[]): VehicleType[] => {
  types.forEach((type) => {
    type.name = getVehicleTypeTranslation(type.name);
    type.childs = translateNames(type.childs ? type.childs : []);
  });
  return types;
};

@Component({
  selector: 'app-vehicle-types-modal',
  imports: [AsyncPipe],
  templateUrl: './vehicle-types-modal.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleTypesModalComponent {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #vehicleTypeService = inject(VehicleTypeService);

  readonly ids = input.required<string[]>();
  readonly changed = output<string[]>();
  protected readonly types$ = this.#vehicleTypeService.getTypes$().pipe(
    map((types) => translateNames(types)),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected isActive(id: string): boolean {
    return (this.ids() || []).indexOf(id) > -1;
  }

  protected toggle(id: string) {
    const index = (this.ids() || []).indexOf(id);
    if (index > -1) {
      this.ids().splice(index, 1);
    } else {
      this.ids().push(id);
    }

    this.changed.emit(this.ids());
  }
}
