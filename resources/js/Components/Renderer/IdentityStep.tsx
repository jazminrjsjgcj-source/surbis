import { useTranslate } from '@/lib/translate'

export interface IdentityData {
    name: string
    email: string
    phone: string
    consent: boolean
}

interface Props {
    mode: string
    commentMode: string
    comment: string
    identity: IdentityData
    onCommentChange: (value: string) => void
    onIdentityChange: (identity: IdentityData) => void
}

/**
 * Comentario e identificacion. RF-COL-021 a 024 · RNF-COL-015.
 *
 * La identidad SOLO se pide en modo identificado u opcional (RF-COL-022). En
 * anonimo no aparece, y el servidor ademas rechaza los datos si llegan
 * (RF-COL-023): que la pantalla no los pida no basta si alguien envia a mano.
 */
export default function IdentityStep({
    mode,
    commentMode,
    comment,
    identity,
    onCommentChange,
    onIdentityChange,
}: Props) {
    const t = useTranslate()

    const pideIdentidad = mode === 'identified' || mode === 'optional'
    const pideComentario = commentMode !== 'disabled'

    function cambiar(campo: keyof IdentityData, valor: string | boolean): void {
        onIdentityChange({ ...identity, [campo]: valor })
    }

    return (
        <div className="renderer">
            {pideComentario && (
                <div className="renderer-field">
                    <label htmlFor="comment" className="renderer-legend">
                        {t('interface.public.comment')}
                    </label>

                    <p className="hint">
                        {commentMode === 'required'
                            ? t('interface.public.comment_required')
                            : t('interface.public.comment_optional')}
                    </p>

                    <textarea
                        id="comment"
                        className="input input-grow"
                        value={comment}
                        maxLength={2000}
                        onChange={(e) => onCommentChange(e.target.value)}
                    />
                </div>
            )}

            {pideIdentidad && (
                <fieldset className="renderer-field">
                    <legend className="renderer-legend">
                        {mode === 'identified'
                            ? t('interface.public.identity_required')
                            : t('interface.public.identity_optional')}
                    </legend>

                    {/*
                        RNF-COL-015: la finalidad y la conservacion se informan
                        ANTES de pedir el consentimiento. Ponerlo despues seria
                        pedir permiso para algo que no se ha explicado.
                    */}
                    <p className="hint">{t('interface.public.identity_purpose')}</p>

                    <div className="field">
                        <label htmlFor="name">{t('interface.public.name')}</label>
                        <input
                            id="name"
                            type="text"
                            className="input"
                            value={identity.name}
                            autoComplete="name"
                            maxLength={160}
                            onChange={(e) => cambiar('name', e.target.value)}
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="email">{t('interface.public.email')}</label>
                        <input
                            id="email"
                            type="email"
                            className="input"
                            value={identity.email}
                            autoComplete="email"
                            inputMode="email"
                            onChange={(e) => cambiar('email', e.target.value)}
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="phone">{t('interface.public.phone')}</label>
                        <input
                            id="phone"
                            type="tel"
                            className="input"
                            value={identity.phone}
                            autoComplete="tel"
                            inputMode="tel"
                            maxLength={40}
                            onChange={(e) => cambiar('phone', e.target.value)}
                        />
                    </div>

                    {/*
                        RF-COL-024: consentimiento explicito. Sin marcarlo, el
                        servidor rechaza los datos aunque lleguen.
                    */}
                    <label className="flex items-start gap-2">
                        <input
                            type="checkbox"
                            checked={identity.consent}
                            onChange={(e) => cambiar('consent', e.target.checked)}
                        />
                        <span>{t('interface.public.consent')}</span>
                    </label>
                </fieldset>
            )}
        </div>
    )
}
