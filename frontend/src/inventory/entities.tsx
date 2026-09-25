import type { ReactNode } from 'react'
import type { Row } from '../api/inventory'
import { testChannel } from '../api/incidents'
import { runMonitor } from '../api/inventory'
import UtilizationBar from '../components/UtilizationBar'

export interface Field {
  key: string
  label: string
  type: 'text' | 'number' | 'select' | 'ref' | 'checkbox' | 'datetime'
  required?: boolean
  /** Initial value on a new record. */
  default?: unknown
  options?: string[]
  /** For `ref` fields: the entity to pick from and how to label each choice. */
  ref?: { path: string; label: (row: Row) => string }
}

export interface Column {
  header: string
  render: (row: Row) => ReactNode
}

export interface RowAction {
  label: string
  /** May return a message to show the user. */
  run: (row: Row) => Promise<unknown>
}

export interface Entity {
  path: string
  title: string
  singular: string
  /** Sidebar section; also the first URL segment. */
  group: 'Inventory' | 'Carriers' | 'Monitoring' | 'Incidents'
  fields: Field[]
  columns: Column[]
  /** CSV header line shown on the import panel; omit for entities without CSV import. */
  csvHeader?: string
  /** Hidden from viewers (the API refuses them anyway). */
  hideFromViewers?: boolean
  /** Extra per-row buttons (shown to users who can write). */
  actions?: RowAction[]
}

export const entityUrl = (e: Entity) => `/${e.group.toLowerCase()}/${e.path}`

const site: Field = { key: 'site_id', label: 'Site', type: 'ref', required: true, ref: { path: 'sites', label: (r) => `${r.code} — ${r.name}` } }


export const entities: Entity[] = [
  {
    path: 'sites',
    title: 'Sites',
    singular: 'site',
    group: 'Inventory',
    csvHeader: 'name,code,city,country,lat,lng',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'code', label: 'Code', type: 'text', required: true },
      { key: 'city', label: 'City', type: 'text' },
      { key: 'country', label: 'Country (2 letters)', type: 'text' },
      { key: 'lat', label: 'Latitude', type: 'number' },
      { key: 'lng', label: 'Longitude', type: 'number' },
    ],
    columns: [
      { header: 'Code', render: (r) => r.code },
      { header: 'Name', render: (r) => r.name },
      { header: 'City', render: (r) => r.city },
      { header: 'Country', render: (r) => r.country },
    ],
  },
  {
    path: 'vlans',
    title: 'VLANs',
    singular: 'VLAN',
    group: 'Inventory',
    csvHeader: 'site_code,vid,name',
    fields: [site, { key: 'vid', label: 'VLAN ID (1–4094)', type: 'number', required: true }, { key: 'name', label: 'Name', type: 'text', required: true }],
    columns: [
      { header: 'Site', render: (r) => r.site?.code },
      { header: 'VID', render: (r) => r.vid },
      { header: 'Name', render: (r) => r.name },
    ],
  },
  {
    path: 'subnets',
    title: 'Subnets',
    singular: 'subnet',
    group: 'Inventory',
    csvHeader: 'site_code,vlan_vid,cidr,description,gateway',
    fields: [
      site,
      { key: 'vlan_id', label: 'VLAN (must be at the same site)', type: 'ref', ref: { path: 'vlans', label: (r) => `${r.site?.code} · ${r.vid} — ${r.name}` } },
      { key: 'cidr', label: 'CIDR (e.g. 10.1.0.0/24)', type: 'text', required: true },
      { key: 'description', label: 'Description', type: 'text' },
      { key: 'gateway', label: 'Gateway', type: 'text' },
    ],
    columns: [
      { header: 'Site', render: (r) => r.site?.code },
      { header: 'CIDR', render: (r) => r.cidr },
      { header: 'Description', render: (r) => r.description },
      { header: 'Gateway', render: (r) => r.gateway },
      { header: 'Used / usable', render: (r) => `${r.used_count} / ${r.usable_hosts}` },
      { header: 'Utilization', render: (r) => <UtilizationBar value={r.utilization} /> },
    ],
  },
  {
    path: 'ip-addresses',
    title: 'IP addresses',
    singular: 'IP address',
    group: 'Inventory',
    csvHeader: 'site_code,subnet_cidr,address,status,device_name,dns_name',
    fields: [
      { key: 'subnet_id', label: 'Subnet', type: 'ref', required: true, ref: { path: 'subnets', label: (r) => `${r.site?.code} · ${r.cidr}` } },
      { key: 'address', label: 'Address', type: 'text', required: true },
      { key: 'status', label: 'Status', type: 'select', required: true, options: ['free', 'reserved', 'assigned'] },
      { key: 'device_id', label: 'Device', type: 'ref', ref: { path: 'devices', label: (r) => r.name } },
      { key: 'dns_name', label: 'DNS name', type: 'text' },
    ],
    columns: [
      { header: 'Subnet', render: (r) => r.subnet?.cidr },
      { header: 'Address', render: (r) => r.address },
      { header: 'Status', render: (r) => r.status },
      { header: 'Device', render: (r) => r.device?.name },
      { header: 'DNS name', render: (r) => r.dns_name },
    ],
  },
  {
    path: 'devices',
    title: 'Devices',
    singular: 'device',
    group: 'Inventory',
    csvHeader: 'site_code,name,type,vendor,model,serial,mgmt_ip',
    fields: [
      site,
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'type', label: 'Type', type: 'select', required: true, options: ['router', 'switch', 'firewall', 'server', 'access_point', 'other'] },
      { key: 'vendor', label: 'Vendor', type: 'text' },
      { key: 'model', label: 'Model', type: 'text' },
      { key: 'serial', label: 'Serial', type: 'text' },
      { key: 'mgmt_ip_id', label: 'Management IP', type: 'ref', ref: { path: 'ip-addresses', label: (r) => r.address } },
    ],
    columns: [
      { header: 'Site', render: (r) => r.site?.code },
      { header: 'Name', render: (r) => r.name },
      { header: 'Type', render: (r) => r.type },
      { header: 'Vendor', render: (r) => r.vendor },
      { header: 'Model', render: (r) => r.model },
      { header: 'Mgmt IP', render: (r) => r.mgmt_ip?.address },
    ],
  },
  {
    path: 'monitors',
    title: 'Monitors',
    singular: 'monitor',
    group: 'Monitoring',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'type', label: 'Type', type: 'select', required: true, options: ['ping', 'tcp', 'http', 'dns', 'simulator'] },
      { key: 'target', label: 'Target (host/IP; URL for http; scenario for simulator: stable, flapping, latency_spike, outage_cycle)', type: 'text', required: true },
      { key: 'port', label: 'Port (tcp only)', type: 'number' },
      { key: 'interval_s', label: 'Interval (seconds, min 30)', type: 'number', required: true, default: 60 },
      { key: 'timeout_ms', label: 'Timeout (ms, shorter than interval)', type: 'number', required: true, default: 3000 },
      { key: 'device_id', label: 'Device (or circuit)', type: 'ref', ref: { path: 'devices', label: (r) => r.name } },
      { key: 'circuit_id', label: 'Circuit (or device)', type: 'ref', ref: { path: 'circuits', label: (r) => `${r.provider?.name} · ${r.name}` } },
      { key: 'enabled', label: 'Enabled', type: 'checkbox', default: true },
    ],
    columns: [
      { header: 'Name', render: (r) => r.name },
      { header: 'Type', render: (r) => r.type },
      { header: 'Target', render: (r) => (r.port ? `${r.target}:${r.port}` : r.target) },
      { header: 'Linked to', render: (r) => r.device?.name ?? r.circuit?.name },
      {
        header: 'State',
        render: (r) =>
          !r.enabled ? (
            <span className="text-gray-400">Disabled</span>
          ) : r.in_maintenance ? (
            <span className="font-medium text-sky-700">Maintenance</span>
          ) : r.state === 'unknown' ? null : (
            <span className={r.state === 'up' ? 'font-medium text-emerald-700' : 'font-medium text-red-600'}>{r.state === 'up' ? 'Up' : 'Down'}</span>
          ),
      },
      { header: 'Latency', render: (r) => (r.last_latency_ms === null ? null : `${r.last_latency_ms} ms`) },
      { header: 'Checked at', render: (r) => (r.last_checked_at ? new Date(r.last_checked_at).toLocaleTimeString() : null) },
    ],
    actions: [{ label: 'Run now', run: (r) => runMonitor(r.id) }],
  },
  {
    path: 'providers',
    title: 'Providers',
    singular: 'provider',
    group: 'Carriers',
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'noc_email', label: 'NOC email', type: 'text' },
      { key: 'noc_phone', label: 'NOC phone', type: 'text' },
      { key: 'notes', label: 'Notes', type: 'text' },
    ],
    columns: [
      { header: 'Name', render: (r) => r.name },
      { header: 'NOC email', render: (r) => r.noc_email },
      { header: 'NOC phone', render: (r) => r.noc_phone },
    ],
  },
  {
    path: 'circuits',
    title: 'Circuits',
    singular: 'circuit',
    group: 'Carriers',
    fields: [
      { key: 'provider_id', label: 'Provider', type: 'ref', required: true, ref: { path: 'providers', label: (r) => r.name } },
      { key: 'circuit_ref', label: "Provider's circuit ID", type: 'text', required: true },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'type', label: 'Type', type: 'select', required: true, options: ['iplc', 'iepl', 'dia', 'mpls', 'other'] },
      { key: 'bandwidth_mbps', label: 'Bandwidth (Mbps)', type: 'number' },
      { key: 'sla_target', label: 'SLA target (%)', type: 'number', default: 99.9 },
      { key: 'a_site_id', label: 'A-end site', type: 'ref', ref: { path: 'sites', label: (r) => `${r.code} — ${r.name}` } },
      { key: 'z_site_id', label: 'Z-end site', type: 'ref', ref: { path: 'sites', label: (r) => `${r.code} — ${r.name}` } },
    ],
    columns: [
      { header: 'Provider', render: (r) => r.provider?.name },
      { header: 'Ref', render: (r) => r.circuit_ref },
      { header: 'Name', render: (r) => r.name },
      { header: 'Type', render: (r) => r.type?.toUpperCase() },
      { header: 'Path', render: (r) => (r.a_site || r.z_site ? `${r.a_site?.code ?? '?'} ↔ ${r.z_site?.code ?? '?'}` : null) },
      { header: 'SLA target', render: (r) => `${r.sla_target}%` },
      {
        header: 'SLA (30d)',
        render: (r) =>
          r.sla_30d?.availability === null || r.sla_30d === undefined ? null : (
            <span className={r.sla_30d.meets_target ? 'font-medium text-emerald-700' : 'font-medium text-red-600'}>{r.sla_30d.availability}%</span>
          ),
      },
    ],
  },
  {
    path: 'maintenance-windows',
    title: 'Maintenance',
    singular: 'maintenance window',
    group: 'Carriers',
    fields: [
      { key: 'circuit_id', label: 'Circuit (all its monitors)', type: 'ref', ref: { path: 'circuits', label: (r) => `${r.provider?.name} · ${r.name}` } },
      { key: 'monitor_id', label: 'or a single monitor', type: 'ref', ref: { path: 'monitors', label: (r) => r.name } },
      { key: 'starts_at', label: 'Starts', type: 'datetime', required: true },
      { key: 'ends_at', label: 'Ends', type: 'datetime', required: true },
      { key: 'provider_ref', label: "Provider's change/ticket no.", type: 'text' },
      { key: 'notes', label: 'Notes', type: 'text' },
    ],
    columns: [
      { header: 'Target', render: (r) => (r.circuit ? `Circuit: ${r.circuit.name}` : r.monitor ? `Monitor: ${r.monitor.name}` : null) },
      { header: 'Starts', render: (r) => new Date(r.starts_at).toLocaleString() },
      { header: 'Ends', render: (r) => new Date(r.ends_at).toLocaleString() },
      {
        header: 'Status',
        render: (r) => <span className={r.status === 'active' ? 'font-medium text-sky-700' : r.status === 'done' ? 'text-gray-400' : ''}>{r.status}</span>,
      },
      { header: 'Ref', render: (r) => r.provider_ref },
    ],
  },
  {
    path: 'alert-channels',
    title: 'Alert channels',
    singular: 'alert channel',
    group: 'Incidents',
    hideFromViewers: true,
    fields: [
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'type', label: 'Type', type: 'select', required: true, options: ['telegram', 'email'] },
      { key: 'target', label: 'Telegram chat id (e.g. -100123…) or email address', type: 'text', required: true },
      { key: 'enabled', label: 'Enabled', type: 'checkbox', default: true },
    ],
    columns: [
      { header: 'Name', render: (r) => r.name },
      { header: 'Type', render: (r) => r.type },
      { header: 'Target', render: (r) => r.target },
      { header: 'Enabled', render: (r) => (r.enabled ? 'Yes' : 'No') },
    ],
    actions: [{ label: 'Send test', run: (r) => testChannel(r.id) }],
  },
]
