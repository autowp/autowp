import {AsyncPipe} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  ComponentRef,
  inject,
  input,
  output,
} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {
  AbstractControl,
  FormArray,
  FormControl,
  FormGroup,
  FormsModule,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import {LatLng} from '@grpc/google/type/latlng.pb';
import {
  APIItem,
  ItemType,
  Picture,
  PictureFields,
  PictureItem,
  PictureListOptions,
  PicturesRequest,
  Spec,
  VehicleType,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {NgbModal} from '@ng-bootstrap/ng-bootstrap';
import {BoolValue, Int32Value} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {SpecService} from '@services/spec';
import {VehicleTypeService} from '@services/vehicle-type';
import {InvalidParams, InvalidParamsPipe} from '@utils/invalid-params.pipe';
import {getVehicleTypeTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {map, shareReplay, switchMap} from 'rxjs/operators';
import {sprintf} from 'sprintf-js';

import {VehicleTypesModalComponent} from '../../../components/vehicle-types-modal/vehicle-types-modal.component';
import {MapPointComponent} from './map-point/map-point.component';

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
  items: number[];
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

interface Form {
  begin?: FormGroup<{
    month: FormControl<null | number>;
    year: FormControl<null | number>;
  }>;
  body?: FormControl<null | string>;
  catname?: FormControl<null | string>;
  end?: FormGroup<{
    month: FormControl<null | number>;
    today: FormControl<boolean | null>;
    year: FormControl<null | number>;
  }>;
  full_name?: FormControl<null | string>;
  is_concept?: FormControl<isConceptValue>;
  is_group?: FormControl<boolean | null>;
  items?: FormArray<FormControl<string>>;
  model_years?: FormGroup<{
    begin_year: FormControl<null | number>;
    begin_year_fraction: FormControl<string>;
    end_year: FormControl<null | number>;
    end_year_fraction: FormControl<string>;
  }>;
  name: FormControl<null | string>;
  pictures?: FormArray<FormControl<string>>;
  point?: FormControl<null | {
    lat: number;
    lng: number;
  }>;
  produced?: FormGroup<{
    count: FormControl<null | number>;
    exactly: FormControl<boolean>;
  }>;
  spec_id?: FormControl<specValue>;
  vehicle_type_id?: FormControl<string[]>;
}

interface ItemMetaFormAPISpec {
  deep?: number;
  id: 'inherited' | number;
  short_name: string;
}

interface PicturesListItem {
  picture$: Observable<Picture>;
  pictureItem: PictureItem;
  selected: boolean;
}

export function itemMetaFormResultsToAPIItem(result: ItemMetaFormResult): APIItem {
  return new APIItem({
    beginModelYear: result.model_years?.begin_year || undefined,
    beginModelYearFraction: result.model_years?.begin_year_fraction,
    beginMonth: result.begin?.month || undefined,
    beginYear: result.begin?.year || undefined,
    body: result.body,
    catname: result.catname,
    endModelYear: result.model_years?.end_year || undefined,
    endModelYearFraction: result.model_years?.end_year_fraction,
    endMonth: result.end?.month || undefined,
    endYear: result.end?.year || undefined,
    fullName: result.full_name,
    isConcept: result.is_concept === 'inherited' ? false : result.is_concept,
    isConceptInherit: result.is_concept === 'inherited',
    isGroup: result.is_group,
    location: result.point
      ? new LatLng({latitude: result.point.lat || undefined, longitude: result.point.lng || undefined})
      : undefined,
    name: result.name,
    produced: result.produced?.count ? new Int32Value({value: result.produced.count}) : undefined,
    producedExactly: result.produced?.exactly || false,
    specId: result.spec_id === 'inherited' ? undefined : result.spec_id || undefined,
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
    for (const subitem of specsToPlain(item.childs ? item.childs : [], deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

@Component({
  selector: 'app-item-meta-form',
  imports: [FormsModule, ReactiveFormsModule, MapPointComponent, AsyncPipe, InvalidParamsPipe],
  templateUrl: './item-meta-form.component.html',
  styleUrl: './styles.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemMetaFormComponent {
  readonly #specService = inject(SpecService);
  readonly #vehicleTypeService = inject(VehicleTypeService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #modalService = inject(NgbModal);
  readonly #cdr = inject(ChangeDetectorRef);

  readonly invalidParams = input.required<InvalidParams>();
  readonly submitted = output<ItemMetaFormResult>();

  readonly disableIsGroup = input<boolean>(false);
  protected readonly disableIsGroup$ = toObservable(this.disableIsGroup);

  readonly parentIsConcept = input<null | ParentIsConcept>(null);
  protected readonly parentIsConcept$ = toObservable(this.parentIsConcept);

  readonly item = input.required<APIItem>();
  protected readonly item$ = toObservable(this.item);

  readonly vehicleTypeIDs = input.required<string[]>();

  readonly items = input<APIItem[]>([]);
  protected readonly items$ = toObservable(this.items).pipe(map((items) => (items && items.length ? items : null)));

  readonly pictures = input<PictureItem[]>([]);
  readonly pictures$ = toObservable(this.pictures).pipe(
    map((pictures) =>
      pictures && pictures.length > 0
        ? pictures.map((p) => ({
            picture$: this.#picturesClient.getPicture(
              new PicturesRequest({
                fields: new PictureFields({nameText: true, thumbMedium: true}),
                language: this.#languageService.language,
                options: new PictureListOptions({id: p.pictureId}),
              }),
            ),
            pictureItem: p,
            selected: false,
          }))
        : null,
    ),
  );

  readonly #vehicleTypes$: Observable<VehicleType[]> = this.#vehicleTypeService.getTypesPlain$().pipe(
    map((types) =>
      types.map((type) => {
        type.name = getVehicleTypeTranslation(type.name);
        return type;
      }),
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
  readonly #nameMaxlength = 100; // DbTable\Item::MAX_NAME
  readonly #fullnameMaxlength = 255; // BrandModel::MAX_FULLNAME
  readonly #bodyMaxlength = 20;
  readonly #modelYearMax: number = new Date().getFullYear() + 10;
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

  protected readonly isConceptOptions$ = this.parentIsConcept$.pipe(
    map((parentIsConcept) => [
      {
        name: $localize`no`,
        value: false,
      },
      {
        name: $localize`yes`,
        value: true,
      },
      {
        name: localizeInherited(parentIsConcept),
        value: isConceptInherited,
      },
    ]),
  );

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

  protected readonly form$: Observable<FormGroup<Form>> = combineLatest([
    this.item$.pipe(switchMap((item) => (item ? of(item) : EMPTY))),
    toObservable(this.vehicleTypeIDs),
    this.disableIsGroup$,
    this.items$,
    this.pictures$,
  ]).pipe(
    // eslint-disable-next-line sonarjs/cognitive-complexity
    map(([item, vehicleTypeIDs, disableIsGroup, items, pictures]) => {
      const elements: Form = {
        name: new FormControl(item.name, {
          nonNullable: true,
          validators: [Validators.required, Validators.maxLength(this.#nameMaxlength)],
        }),
      };
      if (item.itemTypeId === ItemType.ITEM_TYPE_BRAND) {
        elements.full_name = new FormControl(item.fullName, {
          nonNullable: true,
          validators: Validators.maxLength(this.#fullnameMaxlength),
        });
      }
      if (item.itemTypeId === ItemType.ITEM_TYPE_BRAND || item.itemTypeId === ItemType.ITEM_TYPE_CATEGORY) {
        elements.catname = new FormControl(item.catname, {nonNullable: true});
      }
      if (item.itemTypeId === ItemType.ITEM_TYPE_VEHICLE || item.itemTypeId === ItemType.ITEM_TYPE_ENGINE) {
        elements.body = new FormControl(item.body, {
          nonNullable: true,
          validators: Validators.maxLength(this.#bodyMaxlength),
        });

        let spec: specValue = specInherited;
        if (!item.specInherit) {
          spec = item.specId ? item.specId : null;
        }
        elements.spec_id = new FormControl<null | specValue>(spec);
        elements.model_years = new FormGroup({
          begin_year: new FormControl(item.beginModelYear > 0 ? item.beginModelYear : null, [
            Validators.min(1700),
            Validators.max(this.#modelYearMax),
          ]),
          begin_year_fraction: new FormControl(item.beginModelYearFraction, {nonNullable: true}),
          end_year: new FormControl(item.endModelYear > 0 ? item.endModelYear : null, [
            Validators.min(1700),
            Validators.max(this.#modelYearMax),
          ]),
          end_year_fraction: new FormControl(item.endModelYearFraction, {nonNullable: true}),
        });
        elements.produced = new FormGroup({
          count: new FormControl(item.produced ? item.produced.value : null),
          exactly: new FormControl(item.producedExactly, {nonNullable: true}),
        });
        elements.is_concept = new FormControl<isConceptValue>(
          item.isConceptInherit ? isConceptInherited : item.isConcept,
          {nonNullable: true},
        );
        elements.is_group = new FormControl(
          {
            disabled: !!(item.childsCount > 0 || disableIsGroup),
            value: item.isGroup,
          },
          {nonNullable: true},
        );
      }
      if ([ItemType.ITEM_TYPE_TWINS, ItemType.ITEM_TYPE_VEHICLE].includes(item.itemTypeId)) {
        elements.vehicle_type_id = new FormControl(vehicleTypeIDs, {nonNullable: true});
      }
      if (item.itemTypeId !== ItemType.ITEM_TYPE_COPYRIGHT) {
        elements.begin = new FormGroup({
          month: new FormControl(item.beginMonth > 0 ? item.beginMonth : null),
          year: new FormControl(item.beginYear > 0 ? item.beginYear : null, [
            Validators.min(1700),
            Validators.max(this.#yearMax),
          ]),
        });
        elements.end = new FormGroup({
          month: new FormControl(item.endMonth > 0 ? item.endMonth : null),
          today: new FormControl(item.today ? item.today.value : null),
          year: new FormControl(item.endYear > 0 ? item.endYear : null, [
            Validators.min(1700),
            Validators.max(this.#yearMax),
          ]),
        });
      }
      if ([ItemType.ITEM_TYPE_FACTORY, ItemType.ITEM_TYPE_MUSEUM].includes(item.itemTypeId)) {
        const lat = item.location?.latitude || 0;
        const lng = item.location?.longitude || 0;
        elements.point = new FormControl({disabled: false, value: {lat, lng}}, {nonNullable: true});
      }
      if (items) {
        elements.items = new FormArray<FormControl<string>>([], {validators: Validators.required});
      }
      if (pictures) {
        elements.pictures = new FormArray<FormControl<string>>([], {
          validators: Validators.required,
        });
      }
      return new FormGroup<Form>(elements);
    }),
  );

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

  protected doSubmit(form: FormGroup) {
    this.submitted.emit(form.value);
    return false;
  }

  protected showVehicleTypesModal(vehicleTypeIDs: AbstractControl) {
    const modalRef = this.#modalService.open(VehicleTypesModalComponent, {
      centered: true,
      size: 'lg',
    });

    const componentRef: ComponentRef<VehicleTypesModalComponent> = modalRef['_contentRef'].componentRef;
    componentRef.setInput('ids', vehicleTypeIDs.value);

    modalRef.componentInstance.changed.subscribe((value: string[]) => {
      vehicleTypeIDs.setValue(value);
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

  protected onCheckboxChange(e: Event, ctrl: FormArray<FormControl<string>>) {
    const target = e.target as HTMLInputElement;
    if (target.checked) {
      ctrl.push(new FormControl<string>(target.value, {nonNullable: true}));
    } else {
      let i = 0;
      ctrl.controls.forEach((item) => {
        if (item.value == target.value) {
          ctrl.removeAt(i);
          return;
        }
        i++;
      });
    }
    this.#cdr.markForCheck();
  }

  protected onPictureClick(e: PicturesListItem, ctrl: FormArray<FormControl<string>>) {
    e.selected = !e.selected;
    if (e.selected) {
      ctrl.push(new FormControl<string>({disabled: false, value: e.pictureItem.pictureId}, {nonNullable: true}));
    } else {
      let i = 0;
      ctrl.controls.forEach((item) => {
        if (item.value == e.pictureItem.pictureId) {
          ctrl.removeAt(i);
          return;
        }
        i++;
      });
    }
    this.#cdr.markForCheck();
  }
}
