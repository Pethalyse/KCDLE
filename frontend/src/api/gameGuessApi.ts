import api from '@/api'
import type {
  GameCode,
  GuessResponse,
  TodayGuessResult,
} from '@/types/gameGuess'

export async function sendGuess(
  game: GameCode,
  playerId: number,
  guessesCount: number,
) {
  const { data } = await api.post<GuessResponse>(`/games/${game}/guess`, {
    player_id: playerId,
    guesses: guessesCount,
  })

  return data
}

export async function fetchTodayGuessState(game: GameCode) {
  const { data } = await api.get<TodayGuessResult>(
    `/user/games/${game}/today`,
  )

  return data
}
