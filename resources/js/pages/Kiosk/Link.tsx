import { Head, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import { useTranslate } from '@/lib/translate'

interface Props {
    action: string
}

/**
 * Vincular la tableta. Pantalla propia, no la de bienvenida.
 *
 * La ve quien configura, una sola vez. Mezclarla con la bienvenida dejaría un
 * campo de clave a la vista del público.
 */
export default function Link({ action }: Props) {
    const t = useTranslate()
    const { data, setData, post, processing, errors } = useForm({ key: '' })

    function enviar(event: FormEvent): void {
        event.preventDefault()
        post(action)
    }

    return (
        <div className="kiosk kiosk-centered">
            <Head title={t('interface.kiosk.link_title')} />

            <form onSubmit={enviar} className="kiosk-panel">
                <h1 className="text-xl">{t('interface.kiosk.link_title')}</h1>
                <p className="mt-2">{t('interface.kiosk.link_help')}</p>

                <div className="field mt-4">
                    <label htmlFor="key">{t('interface.kiosk.key')}</label>

                    <input
                        id="key"
                        type="text"
                        className="input input-key"
                        value={data.key}
                        // La clave va en mayúsculas y sin acentos: se acepta
                        // escrita de cualquier forma, pero se muestra como es.
                        autoCapitalize="characters"
                        autoComplete="off"
                        autoFocus
                        maxLength={40}
                        onChange={(e) => setData('key', e.target.value.toUpperCase())}
                    />

                    {errors.key && (
                        <span className="error" role="alert">
                            {errors.key}
                        </span>
                    )}
                </div>

                <button type="submit" className="btn btn-primary btn-lg" disabled={processing}>
                    {t('interface.kiosk.link')}
                </button>
            </form>
        </div>
    )
}
