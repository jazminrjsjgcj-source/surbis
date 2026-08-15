import { Head, Link, useForm } from '@inertiajs/react'
import { useState } from 'react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import AdminShell from '@/Layouts/AdminShell'
import { useTranslate } from '@/lib/translate'

interface ChannelInfo {
    value: string
    requires_device: boolean
    scopes: string[]
}

interface BranchOption {
    ulid: string
    name: string
    areas: { ulid: string; name: string }[]
}

interface DeviceOption {
    ulid: string
    name: string
    location: string
}

interface Props {
    survey: { ulid: string; name: string }
    version: { number: number; published_at: string | null } | null
    channels: ChannelInfo[]
    branches: BranchOption[]
    devices: DeviceOption[]
    action: string
    cancelUrl: string
}

type Paso = 'channel' | 'scope' | 'validity'

/**
 * Asistente para crear una aplicacion. RF-AO-DEP-002 y 003.
 *
 * Por pasos y no en un formulario largo: cada decision condiciona la
 * siguiente —elegir quiosco fija el alcance en "dispositivo"— y mostrarlo
 * todo a la vez obligaria a deshabilitar campos que aun no se pueden decidir.
 *
 * La version NO se elige: es contexto de la ruta. Se crea desde la encuesta,
 * y se usa su version publicada actual.
 */
export default function Wizard({
    survey,
    version,
    channels,
    branches,
    devices,
    action,
    cancelUrl,
}: Props) {
    const t = useTranslate()
    const [paso, setPaso] = useState<Paso>('channel')

    const { data, setData, post, processing, errors } = useForm({
        channel: '',
        scope: '',
        branch_ulid: '',
        area_ulid: '',
        device_ulid: '',
        starts_at: '',
        ends_at: '',
    })

    const canal = channels.find((c) => c.value === data.channel)

    /*
     * Sin version publicada no hay formulario que ensenar.
     *
     * RF-AO-DEP-003. Mostrar el asistente y fallar al enviar seria hacer
     * rellenar cuatro pasos para nada.
     */
    if (version === null) {
        return (
            <AdminShell>
                <Head title={t('interface.deployments.new')} />

                <div className="page-header">
                    <h1>{t('interface.deployments.new')}</h1>
                    <p className="hint mt-1">{survey.name}</p>
                </div>

                <div className="card card-pad max-w-140">
                    <p>{t('interface.deployments.no_published_version')}</p>

                    <div className="actions">
                        <Link href={cancelUrl} className="btn btn-primary">
                            {t('interface.deployments.back_to_survey')}
                        </Link>
                    </div>
                </div>
            </AdminShell>
        )
    }

    function elegirCanal(valor: string): void {
        const elegido = channels.find((c) => c.value === valor)

        setData('channel', valor)

        /*
         * Si el canal admite un solo alcance, se fija y se limpian los demas.
         *
         * El quiosco solo admite "dispositivo": preguntarlo seria una
         * pregunta con una sola respuesta posible.
         */
        if (elegido?.scopes.length === 1) {
            setData('scope', elegido.scopes[0] ?? '')
        } else {
            setData('scope', '')
        }

        setData('branch_ulid', '')
        setData('area_ulid', '')
        setData('device_ulid', '')
        setPaso('scope')
    }

    function submit(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    const areas = branches.find((b) => b.ulid === data.branch_ulid)?.areas ?? []

    return (
        <AdminShell>
            <Head title={t('interface.deployments.new')} />

            <div className="page-header">
                <Link href={cancelUrl} className="text-primary text-sm">
                    {survey.name}
                </Link>

                <h1 className="mt-1">{t('interface.deployments.new')}</h1>
                <p className="hint mt-1">
                    {t('interface.deployments.using_version', { number: version.number })}
                </p>
            </div>

            <ErrorSummary errors={errors} />

            <div className="card card-pad max-w-140">
                <form onSubmit={submit}>
                    {/* Paso 1: el canal. */}
                    <fieldset className="border-0 p-0">
                        <legend className="text-sm font-semibold">
                            {t('interface.deployments.step_channel')}
                        </legend>

                        {channels.map((c) => (
                            <label key={c.value} className="panel flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="channel"
                                    value={c.value}
                                    checked={data.channel === c.value}
                                    onChange={() => elegirCanal(c.value)}
                                />

                                <span>
                                    <span className="block font-semibold">
                                        {t(`interface.deployments.channel_${c.value}`)}
                                    </span>
                                    <span className="hint">
                                        {t(`interface.deployments.channel_help_${c.value}`)}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    {/* Paso 2: el alcance. Solo aparece con canal elegido. */}
                    {canal && paso !== 'channel' && (
                        <fieldset className="mt-5 border-0 p-0">
                            <legend className="text-sm font-semibold">
                                {t('interface.deployments.step_scope')}
                            </legend>

                            {canal.scopes.length === 1 ? (
                                <p className="hint">
                                    {t('interface.deployments.scope_fixed', {
                                        scope: t(`interface.deployments.scope_${canal.scopes[0]}`),
                                    })}
                                </p>
                            ) : (
                                <div className="field">
                                    <label htmlFor="scope">
                                        {t('interface.deployments.scope')}
                                    </label>
                                    <select
                                        id="scope"
                                        className="input"
                                        value={data.scope}
                                        onChange={(e) => {
                                            setData('scope', e.target.value)
                                            setData('branch_ulid', '')
                                            setData('area_ulid', '')
                                        }}
                                    >
                                        <option value="">
                                            {t('interface.deployments.choose_scope')}
                                        </option>
                                        {canal.scopes.map((s) => (
                                            <option key={s} value={s}>
                                                {t(`interface.deployments.scope_${s}`)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {(data.scope === 'branch' || data.scope === 'area') && (
                                <div className="field">
                                    <label htmlFor="branch">
                                        {t('interface.deployments.branch')}
                                    </label>
                                    <select
                                        id="branch"
                                        className="input"
                                        value={data.branch_ulid}
                                        onChange={(e) => {
                                            setData('branch_ulid', e.target.value)
                                            setData('area_ulid', '')
                                        }}
                                    >
                                        <option value="">
                                            {t('interface.deployments.choose_branch')}
                                        </option>
                                        {branches.map((b) => (
                                            <option key={b.ulid} value={b.ulid}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {data.scope === 'area' && data.branch_ulid !== '' && (
                                <div className="field">
                                    <label htmlFor="area">{t('interface.deployments.area')}</label>
                                    <select
                                        id="area"
                                        className="input"
                                        value={data.area_ulid}
                                        onChange={(e) => {
                                            // El alcance es UNO solo: al elegir
                                            // area, la sucursal deja de viajar.
                                            setData('area_ulid', e.target.value)
                                        }}
                                    >
                                        <option value="">
                                            {t('interface.deployments.choose_area')}
                                        </option>
                                        {areas.map((a) => (
                                            <option key={a.ulid} value={a.ulid}>
                                                {a.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {data.scope === 'device' && (
                                <div className="field">
                                    <label htmlFor="device">
                                        {t('interface.deployments.device')}
                                    </label>
                                    <select
                                        id="device"
                                        className="input"
                                        value={data.device_ulid}
                                        onChange={(e) => setData('device_ulid', e.target.value)}
                                    >
                                        <option value="">
                                            {t('interface.deployments.choose_device')}
                                        </option>
                                        {devices.map((d) => (
                                            <option key={d.ulid} value={d.ulid}>
                                                {d.location}
                                            </option>
                                        ))}
                                    </select>

                                    {devices.length === 0 && (
                                        <span className="error">
                                            {t('interface.deployments.no_devices')}
                                        </span>
                                    )}
                                </div>
                            )}
                        </fieldset>
                    )}

                    {/* Paso 3: la vigencia. Los dos extremos opcionales. */}
                    {canal && paso !== 'channel' && (
                        <fieldset className="mt-5 border-0 p-0">
                            <legend className="text-sm font-semibold">
                                {t('interface.deployments.step_validity')}
                            </legend>

                            <p className="hint mb-2">{t('interface.deployments.validity_help')}</p>

                            <div className="field">
                                <label htmlFor="starts_at">
                                    {t('interface.deployments.starts_at')}
                                </label>
                                <input
                                    id="starts_at"
                                    type="date"
                                    className="input"
                                    value={data.starts_at}
                                    onChange={(e) => setData('starts_at', e.target.value)}
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="ends_at">
                                    {t('interface.deployments.ends_at')}
                                </label>
                                <input
                                    id="ends_at"
                                    type="date"
                                    className="input"
                                    value={data.ends_at}
                                    onChange={(e) => setData('ends_at', e.target.value)}
                                />
                            </div>
                        </fieldset>
                    )}

                    <div className="actions mt-5">
                        <button
                            type="submit"
                            className="btn btn-primary"
                            disabled={processing || data.channel === '' || data.scope === ''}
                        >
                            {t('interface.deployments.create')}
                        </button>

                        <Link href={cancelUrl} className="btn btn-ghost">
                            {t('interface.deployments.cancel')}
                        </Link>
                    </div>
                </form>
            </div>
        </AdminShell>
    )
}
