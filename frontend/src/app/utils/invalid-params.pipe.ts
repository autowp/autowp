import {Pipe, PipeTransform} from '@angular/core';

export type InvalidParams = Record<string, Record<string, string>>;

@Pipe({
  name: 'invalidParams',
  standalone: true,
})
export class InvalidParamsPipe implements PipeTransform {
  transform(errors: Record<string, Record<string, string>> | undefined, field: string): string[] {
    let errorsArr: string[] = [];

    if (errors?.[field]) {
      errorsArr = Object.values(errors[field]);
    }

    return errorsArr;
  }
}
