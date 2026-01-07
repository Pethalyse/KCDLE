import { useFlashStore } from '@/stores/flash'

export function handleError(
  error: unknown,
  message: string = 'Une erreur est survenue',
  title?: string
): void {
  const flash = useFlashStore()

  flash.error(message, title)

  if (import.meta.env.DEV) {
    console.error(error)
  }
}
