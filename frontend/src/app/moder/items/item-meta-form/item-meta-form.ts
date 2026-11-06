import type {FieldTree} from '@angular/forms/signals';
import type {PictureItem, Spec} from '@grpc/spec.pb';
import type {InvalidParams} from '@utils/invalid-params.pipe';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  computed,
  inject,
  input,
  linkedSignal,
  output,
} from '@angular/core';
import {disabled, form, FormField, hidden, maxLength, required, validate} from '@angular/forms/signals';
import {LatLng} from '@grpc/google/type/latlng.pb';
import {Item, ItemType, PictureFields, PictureListOptions, PicturesRequest, VehicleType} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {BoolValue, Int32Value} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {SpecService} from '@services/spec';
import {VehicleTypeService} from '@services/vehicle-type';
import {InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {getModalComponentRef} from '@utils/modal-component-ref';
import {getVehicleTypeTranslation} from '@utils/translations';
import {map, shareReplay} from 'rxjs';
import {sprintf} from 'sprintf-js';

import {VehicleTypesModal} from '../../../components/vehicle-types-modal/vehicle-types-modal';
import {MapPoint} from './map-point/map-point';

type isConceptValue = 'inherited' | boolean;
type specValue = 'inherited' | null | number;

const isConceptInherited = 'inherited',
  specInherited = 'inherited';

export interface ItemMetaFormResult {
  begin: null | {
    month: null | number;
    year: null | number;
  };
  body: string;
  catname: string;
  end: null | {
    month: null | number;
    today: boolean | null;
    year: null | number;
  };
  full_name: string;
  is_concept: isConceptValue;
  is_group: boolean;
  items: string[];
  model_years: null | {
    begin_year: null | number;
    begin_year_fraction: string;
    end_year: null | number;
    end_year_fraction: string;
  };
  name: string;
  pictures: string[];
  point: null | {
    lat: null | number;
    lng: null | number;
  };
  produced: null | {
    count: null | number;
    exactly: boolean;
  };
  spec_id: specValue;
  vehicle_type_id: string[];
}

export interface ParentIsConcept {
  isConcept: boolean;
}

interface ItemMetaModel {
  begin: {month: string; year: string};
  body: string;
  catname: string;
  end: {month: string; today: string; year: string};
  full_name: string;
  is_concept: string;
  is_group: boolean;
  items: {id: string; selected: boolean}[];
  model_years: {begin_year: string; begin_year_fraction: string; end_year: string; end_year_fraction: string};
  name: string;
  pictures: {id: string; selected: boolean}[];
  point: {lat: number; lng: number};
  produced: {count: string; exactly: string};
  spec_id: string;
  vehicle_type_id: string[];
}

const optionalNumber = (value: string): null | number => (value === '' ? null : Number(value));

interface ItemMetaFormAPISpec {
  deep?: number;
  id: 'inherited' | null | number;
  short_name: string;
}

export function itemMetaFormResultsToAPIItem(result: ItemMetaFormResult): Item {
  return new Item({
    beginModelYear: result.model_years?.begin_year ?? undefined,
    beginModelYearFraction: result.model_years?.begin_year_fraction,
    beginMonth: result.begin?.month ?? undefined,
    beginYear: result.begin?.year ?? undefined,
    body: result.body,
    catname: result.catname,
    endModelYear: result.model_years?.end_year ?? undefined,
    endModelYearFraction: result.model_years?.end_year_fraction,
    endMonth: result.end?.month ?? undefined,
    endYear: result.end?.year ?? undefined,
    fullName: result.full_name,
    isConcept: result.is_concept === 'inherited' ? false : result.is_concept,
    isConceptInherit: result.is_concept === 'inherited',
    isGroup: result.is_group,
    location: result.point
      ? new LatLng({latitude: result.point.lat ?? undefined, longitude: result.point.lng ?? undefined})
      : undefined,
    name: result.name,
    produced:
      result.produced?.count !== null && result.produced?.count !== undefined
        ? new Int32Value({value: result.produced.count})
        : undefined,
    producedExactly: result.produced?.exactly ?? false,
    specId: result.spec_id === 'inherited' ? undefined : (result.spec_id ?? undefined),
    specInherit: result.spec_id === 'inherited',
    today: result.end?.today === null ? undefined : new BoolValue({value: result.end?.today}),
  });
}

function localizeInherited(parentIsConcept: null | ParentIsConcept) {
  if (!parentIsConcept) {
    return $localize`inherited`;
  }
  return parentIsConcept.isConcept ? $localize`inherited (yes)` : $localize`inherited (no)`;
}

function specsToPlain(options: Spec[], deep: number): ItemMetaFormAPISpec[] {
  const result: ItemMetaFormAPISpec[] = [];
  for (const item of options) {
    result.push({
      deep,
      id: item.id,
      short_name: item.shortName,
    });
    for (const subitem of specsToPlain(item.childs ?? [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

@Component({
  selector: 'app-item-meta-form',
  imports: [FormField, MapPoint, AsyncPipe, InvalidParamsPipe],
  templateUrl: './item-meta-form.html',
  styleUrl: './item-meta-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemMetaForm {
  readonly #specService = inject(SpecService);
  readonly #vehicleTypeService = inject(VehicleTypeService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #modalService = inject(NgbModal);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly invalidParams = input.required<InvalidParams>();
  protected readonly serverErrors = computed(() => [
    ...new Set(Object.values(this.invalidParams()).flatMap((errors) => Object.values(errors))),
  ]);
  readonly submitted = output<ItemMetaFormResult>();

  // Set by the parent while its own submit-handling observable is in flight - this component has
  // no visibility into that (it just emits `submitted` and is done), so it can't track it itself.
  readonly sending = input<boolean>(false);

  readonly disableIsGroup = input<boolean>(false);

  readonly parentIsConcept = input<null | ParentIsConcept>(null);

  readonly item = input.required<Item>();

  readonly vehicleTypeIDs = input.required<string[]>();

  readonly items = input<Item[]>([]);
  // items has a default value ([]), so the input itself is never nullish - only its length varies.
  protected readonly itemsData = computed(() => (this.items().length ? this.items() : null));

  readonly pictures = input<PictureItem[]>([]);
  // pictures has a default value ([]), so the input itself is never nullish.
  protected readonly picturesData = computed(() => {
    const pictures = this.pictures();
    return pictures.length > 0
      ? pictures.map((p) => ({
          picture$: this.#picturesClient.getPicture(
            new PicturesRequest({
              fields: new PictureFields({nameText: true, thumbMedium: true}),
              language: this.#languageService.language,
              options: new PictureListOptions({id: p.pictureId}),
            }),
          ),
          pictureItem: p,
        }))
      : null;
  });

  readonly #vehicleTypes$: Observable<VehicleType[]> = this.#vehicleTypeService.getTypesPlain$().pipe(
    map((types) =>
      types.map((type) => new VehicleType({...type.toObject(), name: getVehicleTypeTranslation(type.name)})),
    ),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly todayOptions = [
    {
      name: '--',
      value: null,
    },
    {
      name: $localize`ended`,
      value: false,
    },
    {
      name: $localize`continue in pr.`,
      value: true,
    },
  ];

  protected readonly producedOptions = [
    {
      name: $localize`about`,
      value: false,
    },
    {
      name: $localize`exactly`,
      value: true,
    },
  ];

  protected readonly modelYearFractionOptions = [
    {
      name: '-',
      value: '',
    },
    {
      name: '¼',
      value: '¼',
    },
    {
      name: '½',
      value: '½',
    },
    {
      name: '¾',
      value: '¾',
    },
  ];
  readonly #nameMaxlength = 150; // schema.ItemNameMaxLength (goautowp)
  readonly #fullnameMaxlength = 255; // BrandModel::MAX_FULLNAME
  readonly #bodyMaxlength = 20;
  readonly #yearMax: number = new Date().getFullYear() + 10;

  protected readonly monthOptions: {
    name: string;
    value: null | number;
  }[] = [
    {
      name: '--',
      value: null,
    },
  ];

  protected readonly isConceptOptions = computed(() => [
    {
      name: $localize`no`,
      value: false,
    },
    {
      name: $localize`yes`,
      value: true,
    },
    {
      name: localizeInherited(this.parentIsConcept()),
      value: isConceptInherited,
    },
  ]);

  protected readonly specs$ = this.#specService.specs$.pipe(
    map((specs) =>
      (
        [
          {
            deep: 0,
            id: null,
            short_name: '--',
          },
          {
            deep: 0,
            id: specInherited,
            short_name: 'inherited',
          },
        ] as ItemMetaFormAPISpec[]
      ).concat(specsToPlain(specs, 0)),
    ),
  );

  readonly #vehicle = computed(() =>
    [ItemType.ITEM_TYPE_ENGINE, ItemType.ITEM_TYPE_VEHICLE].includes(this.item().itemTypeId),
  );
  readonly #model = linkedSignal<ItemMetaModel>(() => {
    const item = this.item();
    const spec = item.specId ? String(item.specId) : '';
    return {
      name: item.name,
      full_name: item.fullName,
      catname: item.catname,
      body: item.body,
      spec_id: item.specInherit ? specInherited : spec,
      model_years: {
        begin_year: item.beginModelYear ? String(item.beginModelYear) : '',
        begin_year_fraction: item.beginModelYearFraction,
        end_year: item.endModelYear ? String(item.endModelYear) : '',
        end_year_fraction: item.endModelYearFraction,
      },
      produced: {count: item.produced ? String(item.produced.value) : '', exactly: String(item.producedExactly)},
      is_concept: item.isConceptInherit ? isConceptInherited : String(item.isConcept),
      is_group: item.isGroup,
      vehicle_type_id: [...this.vehicleTypeIDs()],
      begin: {
        month: item.beginMonth ? String(item.beginMonth) : '',
        year: item.beginYear ? String(item.beginYear) : '',
      },
      end: {
        month: item.endMonth ? String(item.endMonth) : '',
        year: item.endYear ? String(item.endYear) : '',
        today: item.today ? String(item.today.value) : '',
      },
      point: {lat: item.location?.latitude ?? 0, lng: item.location?.longitude ?? 0},
      items: this.items().map((child) => ({id: child.id, selected: false})),
      pictures: this.pictures().map((picture) => ({id: picture.pictureId, selected: false})),
    };
  });
  protected readonly editForm = form(this.#model, (path) => {
    disabled(path, {when: () => this.sending()});
    required(path.name);
    maxLength(path.name, this.#nameMaxlength);
    maxLength(path.full_name, this.#fullnameMaxlength);
    maxLength(path.body, this.#bodyMaxlength);
    hidden(path.full_name, {when: () => this.item().itemTypeId !== ItemType.ITEM_TYPE_BRAND});
    hidden(path.catname, {
      when: () => ![ItemType.ITEM_TYPE_BRAND, ItemType.ITEM_TYPE_CATEGORY].includes(this.item().itemTypeId),
    });
    hidden(path.body, {when: () => !this.#vehicle()});
    hidden(path.spec_id, {when: () => !this.#vehicle()});
    hidden(path.model_years, {when: () => !this.#vehicle()});
    hidden(path.produced, {when: () => !this.#vehicle()});
    hidden(path.is_concept, {when: () => !this.#vehicle()});
    hidden(path.is_group, {when: () => !this.#vehicle()});
    hidden(path.vehicle_type_id, {
      when: () => ![ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(this.item().itemTypeId),
    });
    hidden(path.begin, {when: () => this.item().itemTypeId === ItemType.ITEM_TYPE_COPYRIGHT});
    hidden(path.end, {when: () => this.item().itemTypeId === ItemType.ITEM_TYPE_COPYRIGHT});
    hidden(path.point, {
      when: () => ![ItemType.ITEM_TYPE_FACTORY, ItemType.ITEM_TYPE_MUSEUM].includes(this.item().itemTypeId),
    });
    disabled(path.is_group, {when: () => this.item().childsCount > 0 || this.disableIsGroup()});
    for (const year of [path.begin.year, path.end.year, path.model_years.begin_year, path.model_years.end_year]) {
      maxLength(year, 4);
      validate(year, ({value}) =>
        value() !== '' && (!/^\d{4}$/.test(value()) || Number(value()) < 1700 || Number(value()) > this.#yearMax)
          ? {kind: 'year'}
          : undefined,
      );
    }
    validate(path.produced.count, ({value}) =>
      value() !== '' && (!/^\d+$/.test(value()) || Number(value()) > 100000000) ? {kind: 'count'} : undefined,
    );
    hidden(path.items, {when: () => this.items().length === 0});
    hidden(path.pictures, {when: () => this.pictures().length === 0});
    validate(path.items, ({value}) => (value().some((row) => row.selected) ? undefined : {kind: 'required'}));
    validate(path.pictures, ({value}) => (value().some((row) => row.selected) ? undefined : {kind: 'required'}));
  });

  constructor() {
    const date = new Date(Date.UTC(2000, 1, 1, 0, 0, 0, 0));
    for (let i = 0; i < 12; i++) {
      date.setMonth(i);
      const language = this.#languageService.language;
      if (language) {
        const month = date.toLocaleString(language, {month: 'long'});
        this.monthOptions.push({
          name: sprintf('%02d - %s', i + 1, month),
          value: i + 1,
        });
      }
    }
  }

  protected doSubmit(field: FieldTree<ItemMetaModel>): void {
    field().markAsTouched();
    if (this.sending() || field().invalid()) return;
    const value = this.#model();
    const isConcept = value.is_concept === isConceptInherited ? isConceptInherited : value.is_concept === 'true';
    this.submitted.emit({
      name: value.name,
      full_name: value.full_name,
      catname: value.catname,
      body: value.body,
      spec_id: value.spec_id === specInherited ? specInherited : optionalNumber(value.spec_id),
      model_years: field.model_years().hidden()
        ? null
        : {
            ...value.model_years,
            begin_year: optionalNumber(value.model_years.begin_year),
            end_year: optionalNumber(value.model_years.end_year),
          },
      produced: field.produced().hidden()
        ? null
        : {count: optionalNumber(value.produced.count), exactly: value.produced.exactly === 'true'},
      is_concept: !field.is_concept().hidden() && isConcept,
      is_group: value.is_group,
      vehicle_type_id: [...value.vehicle_type_id],
      begin: field.begin().hidden()
        ? null
        : {year: optionalNumber(value.begin.year), month: optionalNumber(value.begin.month)},
      end: field.end().hidden()
        ? null
        : {
            year: optionalNumber(value.end.year),
            month: optionalNumber(value.end.month),
            today: value.end.today === '' ? null : value.end.today === 'true',
          },
      point: field.point().hidden() ? null : {...value.point},
      items: value.items.filter((row) => row.selected).map((row) => row.id),
      pictures: value.pictures.filter((row) => row.selected).map((row) => row.id),
    });
  }

  protected showVehicleTypesModal(vehicleTypeIDs: FieldTree<string[]>) {
    if (this.sending()) return;
    const modalRef = this.#modalService.open(VehicleTypesModal, {
      centered: true,
      size: 'lg',
    });

    const componentRef = getModalComponentRef<VehicleTypesModal>(modalRef);
    componentRef.setInput('ids', vehicleTypeIDs().value());

    componentRef.instance.changed.subscribe((value: string[]) => {
      if (this.sending()) return;
      vehicleTypeIDs().value.set([...value]);
      this.#cdr.markForCheck();
    });
  }

  protected vehicleTypeName$(typeID: string): Observable<string> {
    return this.#vehicleTypes$.pipe(
      map((types) => {
        const type = types.find((t) => t.id === typeID);
        return type ? type.name : '#' + typeID;
      }),
    );
  }

  protected onPictureClick(selected: FieldTree<boolean>): void {
    if (this.sending()) return;
    selected().value.update((value) => !value);
  }
}
