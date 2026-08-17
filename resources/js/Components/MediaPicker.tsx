import { router } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import type { ChangeEvent } from 'react'

import { useTranslate } from '@/lib/translate'

export interface MediaOption {
    ulid: string
    name: string
    url: string
    alt_text: string | null
    is_system: boolean
}

interface Props {
    open: boolean
    media: MediaOption[]
    selected: string | null

    /** Dónde subir. Sin ella, el diálogo solo deja elegir lo que ya hay. */
    uploadUrl?: string

    onSelect: (ulid: string | null) => void
    onClose: () => void
}

/**
 * Elegir una imagen de la biblioteca. RF-AO-BLD-004.
 *
 * Cuadrícula de miniaturas y no un desplegable con nombres: elegir una carita
 * por su nombre, sin verla, no tiene sentido. Para imágenes, ver es el punto.
 *
 * <dialog> nativo por lo mismo que ConfirmDialog: el navegador resuelve el
 * foco atrapado, Escape y la inercia del resto de la página.
 */
export default function MediaPicker({
    open,
    media,
    selected,
    uploadUrl,
    onSelect,
    onClose,
}: Props) {
    const t = useTranslate()
    const dialog = useRef<HTMLDialogElement>(null)
    const archivo = useRef<HTMLInputElement>(null)
    const [busqueda, setBusqueda] = useState('')
    const [subiendo, setSubiendo] = useState(false)
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        const elemento = dialog.current

        if (!elemento) {
            return
        }

        if (open && !elemento.open) {
            elemento.showModal()
        }

        if (!open && elemento.open) {
            elemento.close()
        }
    }, [open])

    function subir(event: ChangeEvent<HTMLInputElement>): void {
        const elegido = event.target.files?.[0]

        if (!elegido || !uploadUrl) {
            return
        }

        setSubiendo(true)
        setError(null)

        router.post(
            uploadUrl,
            { file: elegido },
            {
                /*
                 * forceFormData: sin esto Inertia serializa a JSON y el
                 * archivo llega vacío. Un archivo necesita multipart.
                 */
                forceFormData: true,

                /*
                 * preserveState mantiene el diálogo abierto y lo escrito en el
                 * buscador; only recarga SOLO la biblioteca.
                 *
                 * Sin `only`, Inertia traería la página entera —preguntas,
                 * opciones, todo— y lo que está a medio escribir en el
                 * constructor se perdería.
                 */
                preserveState: true,
                preserveScroll: true,
                only: ['media'],

                onSuccess: (pagina) => {
                    /*
                     * Se elige lo recién subido.
                     *
                     * Quien acaba de subir una imagen la quiere usar; hacerle
                     * buscarla entre las demás sería trabajo por nada.
                     */
                    const subido = (pagina.props as { uploaded_media?: string }).uploaded_media

                    if (typeof subido === 'string') {
                        onSelect(subido)
                        onClose()
                    }
                },

                onError: (errores) => {
                    // El motivo del servidor: dice si fue el tipo o el tamaño.
                    setError(errores.file ?? t('interface.media.upload_failed'))
                },

                onFinish: () => {
                    setSubiendo(false)

                    // Se vacía el input: sin esto, elegir el MISMO archivo otra
                    // vez no dispara el evento y parece que no pasa nada.
                    if (archivo.current) {
                        archivo.current.value = ''
                    }
                },
            },
        )
    }

    const filtradas = media.filter((item) =>
        `${item.name} ${item.alt_text ?? ''}`.toLowerCase().includes(busqueda.toLowerCase()),
    )

    return (
        <dialog ref={dialog} className="media-picker" onClose={onClose}>
            <h2 className="text-lg">{t('interface.media.pick_title')}</h2>

            {uploadUrl && (
                <div className="field mt-3">
                    <label htmlFor="media-upload">{t('interface.media.upload')}</label>

                    <input
                        ref={archivo}
                        id="media-upload"
                        type="file"
                        className="input"
                        accept="image/jpeg,image/png,image/webp"
                        disabled={subiendo}
                        onChange={subir}
                    />

                    <span className="hint">{t('interface.media.upload_help')}</span>

                    {subiendo && (
                        <span className="hint" role="status">
                            {t('interface.media.uploading')}
                        </span>
                    )}

                    {error && (
                        <span className="error" role="alert">
                            {error}
                        </span>
                    )}
                </div>
            )}

            <div className="field mt-3">
                <label htmlFor="media-search">{t('interface.media.search')}</label>
                <input
                    id="media-search"
                    type="search"
                    className="input"
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                />
            </div>

            {filtradas.length === 0 ? (
                <p className="hint mt-3">{t('interface.media.empty')}</p>
            ) : (
                <ul className="media-grid mt-3">
                    {filtradas.map((item) => (
                        <li key={item.ulid}>
                            <button
                                type="button"
                                className="media-tile"
                                // aria-pressed y no solo un borde: quien no ve
                                // la pantalla también tiene que saber cuál
                                // está elegida.
                                aria-pressed={selected === item.ulid}
                                onClick={() => {
                                    onSelect(item.ulid)
                                    onClose()
                                }}
                            >
                                {/* alt con el texto alternativo de la
                                    biblioteca. Si faltara, el nombre del
                                    archivo: peor, pero mejor que "imagen". */}
                                <img src={item.url} alt={item.alt_text ?? item.name} />
                                <span className="media-tile-name">{item.name}</span>

                                {item.is_system && (
                                    <span className="badge">{t('interface.media.system')}</span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <div className="actions mt-4">
                <button type="button" className="btn btn-ghost" onClick={onClose}>
                    {t('interface.confirm.cancel')}
                </button>

                {/* Quitar la imagen sin cerrar el diálogo: cambiar de opinión
                    no debería obligar a salir y volver a entrar. */}
                {selected !== null && (
                    <button
                        type="button"
                        className="btn btn-ghost btn-danger"
                        onClick={() => {
                            onSelect(null)
                            onClose()
                        }}
                    >
                        {t('interface.media.remove')}
                    </button>
                )}
            </div>
        </dialog>
    )
}
