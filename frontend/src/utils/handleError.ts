import { useFlashStore } from '@/stores/flash'

export function handleError(
  error: unknown,
  message: string = 'Une erreur est survenue',
): void {
  const flash = useFlashStore()

  flash.error(message)

  if (import.meta.env.DEV) {
    console.error(error)
  }
}
