import { useEffect, useState } from 'react'
import { createEcho } from '../api/echo'
import { listAll, type Row } from '../api/inventory'

export type Connection = 'connecting' | 'live' | 'offline'

interface CheckedEvent {
  id: number
  state: string
  state_changed_at: string | null
  last_success: boolean
  last_latency_ms: number | null
  last_checked_at: string
  in_maintenance: boolean
}

/** All monitors, kept current by the `monitors` socket channel. Falls back to the last fetched data if the socket drops. */
export function useLiveMonitors() {
  const [monitors, setMonitors] = useState<Row[] | null>(null)
  const [connection, setConnection] = useState<Connection>('connecting')

  useEffect(() => {
    let active = true
    const echo = createEcho()

    const apply = (e: CheckedEvent) =>
      setMonitors((rows) => rows?.map((m) => (m.id === e.id ? { ...m, state: e.state, state_changed_at: e.state_changed_at, last_success: e.last_success, last_latency_ms: e.last_latency_ms, last_checked_at: e.last_checked_at, in_maintenance: e.in_maintenance } : m)) ?? rows)

    echo.private('monitors').listen('.MonitorChecked', apply)

    // Subscribe first, then load, so an event landing between the two is not lost (it would only re-apply newer data).
    listAll('monitors').then((rows) => active && setMonitors(rows)).catch(() => active && setMonitors([]))

    const pusher = echo.connector.pusher
    pusher.connection.bind('connected', () => setConnection('live'))
    pusher.connection.bind('unavailable', () => setConnection('offline'))
    pusher.connection.bind('disconnected', () => setConnection('offline'))

    return () => {
      active = false
      echo.disconnect()
    }
  }, [])

  return { monitors, connection }
}
