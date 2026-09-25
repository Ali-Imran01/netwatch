import type { ReactNode } from 'react'
import type { Row } from '../api/inventory'
import { runMonitor } from '../api/inventory'
import UtilizationBar from '../components/UtilizationBar'

export interface Field {
  key: string
  label: string
  type: 'text' | 'number' | 'select' | 'ref' | 'checkbox'
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
  run: (row: Row) => Promise<unknown>
}

export interface Entity {
  path: string
  title: string
  singular: string
  /** Sidebar section; also the first URL segment. */
  group: 'Inventory' | 'Monitoring'
  fields: Field[]
  columns: Column[]
  /** CSV header line shown on the import panel; omit for entities without CSV import. */
  csvHeader?: string
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
      { key: 'type', label: 'Type', type: 'select', required: true, options: ['ping', 'tcp', 'http', 'dns'] },
      { key: 'target', label: 'Target (host/IP, or URL for http)', type: 'text', required: true },
      { key: 'port', label: 'Port (tcp only)', type: 'number' },
      { key: 'interval_s', label: 'Interval (seconds, min 30)', type: 'number', required: true, default: 60 },
      { key: 'timeout_ms', label: 'Timeout (ms, shorter than interval)', type: 'number', required: true, default: 3000 },
      { key: 'device_id', label: 'Device', type: 'ref', ref: { path: 'devices', label: (r) => r.name } },
      { key: 'enabled', label: 'Enabled', type: 'checkbox', default: true },
    ],
    columns: [
      { header: 'Name', render: (r) => r.name },
      { header: 'Type', render: (r) => r.type },
      { header: 'Target', render: (r) => (r.port ? `${r.target}:${r.port}` : r.target) },
      { header: 'Device', render: (r) => r.device?.name },
      {
        header: 'Last check',
        render: (r) =>
          !r.enabled ? (
            <span className="text-gray-400">Disabled</span>
          ) : r.last_success === null ? null : (
            <span className={r.last_success ? 'font-medium text-emerald-700' : 'font-medium text-red-600'}>{r.last_success ? 'Up' : 'Down'}</span>
          ),
      },
      { header: 'Latency', render: (r) => (r.last_latency_ms === null ? null : `${r.last_latency_ms} ms`) },
      { header: 'Checked at', render: (r) => (r.last_checked_at ? new Date(r.last_checked_at).toLocaleTimeString() : null) },
    ],
    actions: [{ label: 'Run now', run: (r) => runMonitor(r.id) }],
  },
]
