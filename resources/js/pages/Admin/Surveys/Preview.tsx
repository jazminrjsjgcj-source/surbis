import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

import SurveyRenderer from '@/Components/Renderer/SurveyRenderer'
import AdminShell from '@/Layouts/AdminShell'
import type { RenderableSurvey } from '@/lib/renderer'
import { useTranslate } from '@/lib/translate'

interface Props {
    survey: { ulid: string; name: string }
    version: { number: number; isDraft: boolean } | null
    rendered: RenderableSurvey | null
    layout?: string
    layouts: string[]
    previewUrl?: string
    backUrl: string
}

/**
 * Vista previa. RF-AO-BLD-008 y RF-AO-PUB-004.
 *
 * El MISMO componente que ve quien contesta. Si fuera otro, esta pantalla
 * dejaria de predecir lo que se vera de verdad, y entonces no sirve para
 * nada (RNF-AO-BLD-004).
 */
export default function Preview({
    survey,
    version,
    rendered,
    layout,
    layouts,
    previewUrl,
    backUrl,
}: Props) {
    const t = useTranslate()
    const [terminada, setTerminada] = useState(false)

    return (
        <AdminShell>
            <Head title={t('interface.preview.title')} />

            <div className="page-header">
                <Link href={backUrl} className="text-primary text-sm">
                    {survey.name}
                </Link>

                <h1 className="mt-1">{t('interface.preview.title')}</h1>

                {version && (
                    <p className="hint mt-1">
                        {version.isDraft
                            ? t('interface.preview.showing_draft', { number: version.number })
                            : t('interface.preview.showing_published', { number: version.number })}
                    </p>
                )}
            </div>

            {rendered === null ? (
                <div className="card card-pad max-w-140">
                    <p>{t('interface.preview.nothing')}</p>
                </div>
            ) : (
                <>
                    {/*
                        El selector de canal. RF-AO-PUB-004 pide simular
                        quiosco, telefono, tableta y widget: una encuesta puede
                        aplicarse por varios a la vez, y sin esto la vista
                        previa no enseñaria como se ve en un enlace.
                    */}
                    <div className="field max-w-140">
                        <label htmlFor="layout">{t('interface.preview.layout')}</label>
                        <select
                            id="layout"
                            className="input"
                            value={layout}
                            onChange={(e) => {
                                setTerminada(false)
                                router.get(
                                    previewUrl ?? '',
                                    { layout: e.target.value },
                                    { preserveState: false },
                                )
                            }}
                        >
                            {layouts.map((l) => (
                                <option key={l} value={l}>
                                    {t(`interface.preview.layout_${l}`)}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/*
                        El marco simula el ancho del dispositivo. No es
                        decoracion: una pregunta con cinco caritas se ve
                        distinta en 360 px que en pantalla completa, y eso es
                        justo lo que hay que poder comprobar.
                    */}
                    <div className={`preview-frame preview-frame-${layout}`}>
                        {terminada ? (
                            <div className="text-center">
                                <p>{rendered.thankYou ?? t('interface.public.done_body')}</p>

                                <button
                                    type="button"
                                    className="btn btn-ghost mt-4"
                                    onClick={() => setTerminada(false)}
                                >
                                    {t('interface.preview.restart')}
                                </button>
                            </div>
                        ) : (
                            <>
                                {rendered.introduction && (
                                    <p className="mb-4">{rendered.introduction}</p>
                                )}

                                <SurveyRenderer
                                    survey={rendered}
                                    // RF-AO-DEP-007: previsualizar NO registra
                                    // respuestas oficiales. Aqui no se guarda
                                    // nada en ningun sitio.
                                    onComplete={() => setTerminada(true)}
                                />
                            </>
                        )}
                    </div>

                    <p className="hint mt-3">{t('interface.preview.not_real')}</p>
                </>
            )}
        </AdminShell>
    )
}
