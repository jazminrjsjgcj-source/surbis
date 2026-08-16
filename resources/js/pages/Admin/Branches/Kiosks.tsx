import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import type { FormEvent } from 'react'

import ConfirmDialog from '@/Components/ConfirmDialog'
import StatusMessage from '@/Components/StatusMessage'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface DeviceRow {
    ulid: string
    name: string
    code: string
    areaName: string | null
    isActive: boolean
    survey: string | null
    versionNumber: number | null
    isApplying: boolean
    notApplyingReason: string | null
    key: { state: string; expires_at: string | null }
    generateKeyUrl: string
    revokeKeyUrl: string
    suspendUrl: string | null
    activateUrl: string | null
}

interface Props {
    branch: { ulid: string; name: string }
    switch: { state: string; total: number; active: number }
    devices: DeviceRow[]
    versions: { ulid: string; name: string; versionNumber: number }[]
    activateUrl: string
    suspendUrl: string
    backUrl: string
}

interface PageProps {
    station_key?: string
    station_key_device?: string
    [key: string]: unknown
}

/**
 * Los quioscos de una sucursal.
 *
 * El interruptor de arriba es una operación en LOTE sobre los deployments de
 * cada tableta, no un deployment de sucursal. Por debajo cada una conserva
 * el suyo, con su clave y su revocación.
 */
export default function Kiosks({
    branch,
    switch: estado,
    devices,
    versions,
    activateUrl,
    suspendUrl,
    backUrl,
}: Props) {
    const t = useTranslate()
    const { props } = usePage<PageProps>()

    const [confirmando, setConfirmando] = useState(false)
    const { data, setData, processing } = useForm({ version: versions[0]?.ulid ?? '' })

    function activar(event: FormEvent): void {
        event.preventDefault()

        /*
         * Se confirma porque activar la sucursal CIERRA los deployments que
         * tengan otra encuesta. Decisión del área usuaria: activar "toda la
         * sucursal" y que tres tabletas siguieran con otra sería un resultado
         * que nadie espera — pero hay que avisarlo antes, no después.
         */
        setConfirmando(true)
    }

    return (
        <AdminShell>
            <Head title={t('interface.kiosks.title')} />

            <div className="page-header">
                <Link href={backUrl} className="text-primary text-sm">
                    {branch.name}
                </Link>

                <h1 className="mt-1">{t('interface.kiosks.title')}</h1>
                <p className="hint mt-1">{t('interface.kiosks.subtitle')}</p>
            </div>

            <StatusMessage />

            {/*
                La clave aparece UNA vez, al generarla. En la base solo queda
                su hash: quien recargue ya no la verá.
            */}
            {props.station_key && (
                <div className="alert alert-neutral mb-4 max-w-140" role="status">
                    <p className="alert-title">{t('interface.kiosks.key_title')}</p>

                    <p className="font-mono text-lg">{props.station_key}</p>

                    <p className="hint">{t('interface.kiosks.key_once')}</p>
                </div>
            )}

            <div className="card card-pad max-w-140">
                <h2 className="text-lg">{t('interface.kiosks.switch')}</h2>

                <p className="hint mt-1">
                    {t(`interface.kiosks.state_${estado.state}`, {
                        active: estado.active,
                        total: estado.total,
                    })}
                </p>

                {estado.total === 0 ? (
                    <p className="mt-3">{t('interface.kiosks.no_devices')}</p>
                ) : versions.length === 0 ? (
                    <p className="mt-3">{t('interface.kiosks.no_versions')}</p>
                ) : (
                    <form onSubmit={activar} className="mt-3">
                        <div className="field">
                            <label htmlFor="version">{t('interface.kiosks.version')}</label>
                            <select
                                id="version"
                                className="input"
                                value={data.version}
                                onChange={(e) => setData('version', e.target.value)}
                            >
                                {versions.map((v) => (
                                    <option key={v.ulid} value={v.ulid}>
                                        {v.name} — {t('interface.kiosks.version_number', {
                                            number: v.versionNumber,
                                        })}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="actions">
                            <button type="submit" className="btn btn-primary" disabled={processing}>
                                {t('interface.kiosks.activate_all')}
                            </button>

                            {estado.active > 0 && (
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => router.post(suspendUrl, {}, { preserveScroll: true })}
                                >
                                    {t('interface.kiosks.suspend_all')}
                                </button>
                            )}
                        </div>
                    </form>
                )}
            </div>

            <h2 className="mt-6 text-lg">{t('interface.kiosks.devices')}</h2>

            <div className="mt-3 grid gap-3">
                {devices.map((device) => (
                    <div key={device.ulid} className="card card-pad">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <span className="block font-semibold">{device.name}</span>
                                <span className="hint">
                                    {device.code}
                                    {device.areaName && ` · ${device.areaName}`}
                                </span>
                            </div>

                            <span
                                className={`badge ${device.isApplying ? 'badge-active' : 'badge-archived'}`}
                            >
                                {device.isApplying
                                    ? t('interface.kiosks.applying')
                                    : t('interface.kiosks.not_applying')}
                            </span>
                        </div>

                        {device.survey ? (
                            <p className="mt-2">
                                {device.survey} ·{' '}
                                {t('interface.kiosks.version_number', {
                                    number: device.versionNumber ?? 0,
                                })}
                            </p>
                        ) : (
                            <p className="hint mt-2">{t('interface.kiosks.no_deployment')}</p>
                        )}

                        {/*
                            El estado de la clave, nunca la clave. Y los tres
                            motivos se distinguen porque lo que hay que hacer
                            es distinto en cada uno.
                        */}
                        <p className="hint mt-2">
                            {t(`interface.kiosks.key_${device.key.state}`)}
                        </p>

                        <div className="actions">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() =>
                                    router.post(device.generateKeyUrl, {}, { preserveScroll: true })
                                }
                            >
                                {device.key.state === 'never_set'
                                    ? t('interface.kiosks.generate_key')
                                    : t('interface.kiosks.regenerate_key')}
                            </button>

                            {device.key.state === 'usable' && (
                                <button
                                    type="button"
                                    className="btn btn-ghost btn-danger"
                                    onClick={() =>
                                        router.post(device.revokeKeyUrl, {}, { preserveScroll: true })
                                    }
                                >
                                    {t('interface.kiosks.revoke_key')}
                                </button>
                            )}

                            {device.isApplying && device.suspendUrl && (
                                <button
                                    type="button"
                                    className="btn btn-ghost ms-auto"
                                    onClick={() =>
                                        router.post(device.suspendUrl ?? '', {}, { preserveScroll: true })
                                    }
                                >
                                    {t('interface.kiosks.suspend_one')}
                                </button>
                            )}

                            {!device.isApplying && device.activateUrl && (
                                <button
                                    type="button"
                                    className="btn btn-ghost ms-auto"
                                    onClick={() =>
                                        router.post(device.activateUrl ?? '', {}, { preserveScroll: true })
                                    }
                                >
                                    {t('interface.kiosks.activate_one')}
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            <ConfirmDialog
                open={confirmando}
                title={t('interface.kiosks.confirm_title')}
                body={t('interface.kiosks.confirm_body')}
                confirmLabel={t('interface.kiosks.activate_all')}
                onConfirm={() => {
                    router.post(activateUrl, { version: data.version }, { preserveScroll: true })
                    setConfirmando(false)
                }}
                onCancel={() => setConfirmando(false)}
            />
        </AdminShell>
    )
}
