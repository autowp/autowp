import {Date as GrpcDate} from '@grpc/google/type/date.pb';
import {sprintf} from 'sprintf-js';

export const parseStringToGrpcDate = (date: string): GrpcDate => {
  const parts = date.split('-');
  const year = parts.length > 0 ? parseInt(parts[0], 10) : 0;
  const month = parts.length > 1 ? parseInt(parts[1], 10) : 0;
  const day = parts.length > 2 ? parseInt(parts[2], 10) : 0;

  return new GrpcDate({day, month, year});
};

export const formatGrpcDate = (value: GrpcDate): string => {
  return formatDate(parseGrpcDate(value));
};

export const formatDate = (value: Date): string => {
  return sprintf('%04d-%02d-%02d', value.getFullYear(), value.getMonth() + 1, value.getDate());
};

export const parseGrpcDate = (value: GrpcDate): Date => {
  return new Date(value.year, value.month - 1, value.day, 0, 0, 0, 0);
};
