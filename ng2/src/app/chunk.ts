export function chunkBy<T>(arr: T[], count: number): T[][] {
  const newArr: T[][] = [];
  const size = Math.ceil(count);
  for (let i = 0; i < arr.length; i += size) {
    newArr.push(arr.slice(i, i + size));
  }
  return newArr;
}

export function chunk<T>(arr: T[], count: number): T[][] {
  const newArr: T[][] = [];
  const size = Math.ceil(arr.length / count);
  for (let i = 0; i < arr.length; i += size) {
    newArr.push(arr.slice(i, i + size));
  }
  return newArr;
}
