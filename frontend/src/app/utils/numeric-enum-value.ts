export function numericEnumValue<T extends number>(values: Record<string, string | T>, value: number, fallback: T): T {
  return (
    Object.values(values).find((candidate): candidate is T => typeof candidate === 'number' && candidate === value) ??
    fallback
  );
}
