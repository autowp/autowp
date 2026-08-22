import type {JcropCrop} from './Jcrop';

export interface CropSummary {
  aspect: string;
  resolution: string;
}

// The "N:M" aspect-ratio approximation shown next to every cropper, normalized to a width of 4
// (e.g. "4:3", "4:2.3").
export function cropSummary(crop: JcropCrop): CropSummary {
  const pw = 4;
  const ph = Math.round(((pw * crop.h) / crop.w) * 10) / 10;

  return {
    aspect: `${pw}:${ph}`,
    resolution: `${Math.round(crop.w)}×${Math.round(crop.h)}`,
  };
}
