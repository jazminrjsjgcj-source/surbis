import { useEffect, useRef, useState } from 'react'

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
    onSelect: (ulid: string | null) => void
    onClose: () => void
}

/**
 * Elegir una imagen de la biblioteca. RF-AO-BLD-004.
 *
 * Cuadricula de miniaturas y no un desplegable con nombres: elegir una carita
 * por su nombre, sin verla, no tiene sentido. Para imagenes, ver es el punto.
 *
 * <dialog> nativo por lo mismo que ConfirmDialog: el navegador resuelve el
 * foco atrapado, Escape y la inercia del resto de la pagina.
 */
export default function MediaPicker({ open, media, selected, onSelect, onClose }: Props) {
    const t = useTranslate()
    const dialog = useRef<HTMLDialogElement>(null)
    const [busqueda, setBusqueda] = useState('')

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

    const filtradas = media.filter((item) =>
        `${item.name} ${item.alt_text ?? ''}`.toLowerCase().includes(busqueda.toLowerCase()),
    )

    return (
        <dialog ref={dialog} className="media-picker" onClose={onClose}>
            <h2 className="text-lg">{t('interface.media.pick_title')}</h2>

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
                                // la pantalla tambien tiene que saber cual
                                // esta elegida.
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

                {/* Quitar la imagen sin cerrar el dialogo: cambiar de opinion
                    no deberia obligar a salir y volver a entrar. */}
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
