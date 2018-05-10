export interface APIBrandsGetResponse {
  items: APIBrandsLines;
}

export interface APIBrandsLines {
  cyrillic: APIBrandsChar[];
  latin: APIBrandsChar[];
  numbers: APIBrandsChar[];
  other: APIBrandsChar[];
}

export interface APIBransLine {
  [key: string]: APIBrandsChar;
}

export interface APIBrandsChar {
  char: string;
  id: number;
  brands: APIBrandsBrand[];
}

export interface APIBrandsBrand {
  catname: string;
  id: number;
  logo_id: number;
  name: string;
  newCars: number;
  new_cars_url: string;
  totalCars: number;
  totalPictures: number;
  url: string;
}
