import { Head, useForm } from '@inertiajs/react'
import { useCallback, useState } from 'react'
import type { FormEvent } from 'react'

import QueueStatus from '@/Components/QueueStatus'
import { useTranslate } from '@/lib/translate'

interface Props {
    device: { name: string; branch: string | null }
    survey: { name: string }
    staff: { ulid: string; name: string }[]
    current: string | null
    action: string

    /** Los limites de offline, decididos en el servidor. */
    offline: { limitDays: number; limitCount: number; warnAt: number }
}

/**
 * Preparar la estación: quién va a ser evaluado. RF-COL-001 a 006.
 *
 * La ve el colaborador al empezar su turno, no el ciudadano. Es un paso
 * aparte de la vinculación a propósito: vincular lo hace quien administra una
 * vez, y esto se hace cada turno.
 */
export default function Prepare({ device, survey, staff, current, action, offline }: Props) {
    const t = useTranslate()
    const { data, setData, post, processing } = useForm({ staff: current ?? '' })

    /*
     * Con la cola llena NO se puede abrir turno. Decision del area usuaria.
     *
     * El quiosco deja de iniciar encuestas nuevas y se queda aqui hasta
     * recuperar conexion. Es preferible no recoger respuestas a recogerlas
     * sin poder guardarlas.
     */
    const [bloqueado, setBloqueado] = useState(false)

    const alBloquear = useCallback((valor: boolean) => setBloqueado(valor), [])

    function enviar(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <div className="kiosk kiosk-centered">
            <Head title={t('interface.kiosk.prepare_title')} />

            <form onSubmit={enviar} className="kiosk-panel">
                <h1 className="text-xl">{t('interface.kiosk.prepare_title')}</h1>

                <p className="hint mt-1">
                    {device.name}
                    {device.branch && ` · ${device.branch}`}
                </p>

                <p className="mt-3">{survey.name}</p>

                {staff.length === 0 ? (
                    /*
                     * Sin personal evaluable se puede empezar igual: hay
                     * quioscos que miden el servicio de una ventanilla sin
                     * atribuirlo a nadie.
                     */
                    <p className="hint mt-4">{t('interface.kiosk.no_staff')}</p>
                ) : (
                    <fieldset className="mt-4 border-0 p-0">
                        <legend className="renderer-legend">
                            {t('interface.kiosk.who')}
                        </legend>

                        <p className="hint">{t('interface.kiosk.who_help')}</p>

                        <div className="renderer-choices mt-2">
                            {staff.map((person) => (
                                <button
                                    key={person.ulid}
                                    type="button"
                                    className="renderer-choice"
                                    aria-pressed={data.staff === person.ulid}
                                    onClick={() =>
                                        setData('staff', data.staff === person.ulid ? '' : person.ulid)
                                    }
                                >
                                    {person.name}
                                </button>
                            ))}
                        </div>
                    </fieldset>
                )}

                <QueueStatus
                    limitDays={offline.limitDays}
                    limitCount={offline.limitCount}
                    warnAt={offline.warnAt}
                    onBlocked={alBloquear}
                />

                <button
                    type="submit"
                    className="btn btn-primary btn-lg mt-4"
                    disabled={processing || bloqueado}
                >
                    {t('interface.kiosk.start')}
                </button>

                {bloqueado && (
                    <p className="error mt-2" role="alert">
                        {t('interface.queue.blocked_help')}
                    </p>
                )}

                {/*
                    Cambiar de persona CIERRA la sesión anterior y abre otra.
                    Se avisa antes: si el turno cambió, las respuestas
                    siguientes son del nuevo colaborador.
                */}
                {current !== null && (
                    <p className="hint mt-3">{t('interface.kiosk.replaces')}</p>
                )}
            </form>
        </div>
    )
}
