export interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

export interface JcropOptions {
  boxHeight: number;
  boxWidth: number;
  keySupport: boolean;
  minSize: number[];
  onSelect: (crop: JcropCrop) => void;
  setSelect: number[];
  trueSize: number[];
}

export interface JcropInstance {
  setSelect(coords: number[]): void;
}

export default function Jcrop(img: HTMLImageElement, options: JcropOptions): JcropInstance;
