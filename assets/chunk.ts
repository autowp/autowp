export function chunkBy(arr: any[], count: number): any[] {
    var newArr: any[] = [];
    var size = Math.ceil(count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}

export function chunk(arr: any[], count: number): any[] {
    var newArr: any[] = [];
    var size = Math.ceil(arr.length / count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}